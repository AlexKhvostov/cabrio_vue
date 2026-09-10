<?php
/**
 * Места клуба (в коде ещё guide_objects). Удаление — модератор/админ (статус «удалён»).
 */
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/GuideObject.php';
require_once __DIR__ . '/../models/Status.php';

class GuideObjectController extends BaseController
{
    public function getList()
    {
        try {
            if (!$this->requireAccess('api.guide-objects.getList')) {
                return;
            }
            $guideObjects = GuideObject::getAll();
            $uid = (int)$this->getCurrentUserId();
            foreach ($guideObjects as &$g) {
                $g['permissions'] = $this->guidePermissions($g, $uid);
            }
            unset($g);
            $this->logUserAction('get_guide_objects_list', ['count' => count($guideObjects)]);
            $this->json(['success' => true, 'data' => $guideObjects, 'meta' => $this->getRequestInfo()]);
        } catch (Throwable $e) {
            Logger::error('GuideObjectController: getList error', ['error' => $e->getMessage()]);
            $this->json(['success' => false, 'error' => ['code' => 'DB_ERROR', 'message' => $e->getMessage()]], 500);
        }
    }

    public function getById($id)
    {
        try {
            if (!$this->requireAccess('api.guide-objects.getById')) {
                return;
            }
            $item = GuideObject::findExpanded((int)$id);
            if (!$item) {
                $this->json(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Место не найдено']], 404);
                return;
            }
            $item['permissions'] = $this->guidePermissions($item, (int)$this->getCurrentUserId());
            $this->json(['success' => true, 'data' => $item, 'meta' => $this->getRequestInfo()]);
        } catch (Throwable $e) {
            Logger::error('GuideObjectController: getById error', ['error' => $e->getMessage()]);
            $this->json(['success' => false, 'error' => ['code' => 'DB_ERROR', 'message' => $e->getMessage()]], 500);
        }
    }

    public function create()
    {
        try {
            if (!$this->requireAccess('api.guide-objects.create')) {
                return;
            }
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $name = trim((string)($input['name'] ?? ''));
            if ($name === '') {
                $this->json(['success' => false, 'error' => ['code' => 'VALIDATION', 'message' => 'Нужно название места']], 400);
                return;
            }
            $id = GuideObject::create([
                'name' => $name,
                'guide_object_type_id' => null,
                'guide_object_kind_id' => null,
                'city' => trim((string)($input['city'] ?? '')),
                'address' => trim((string)($input['address'] ?? '')),
                'website' => trim((string)($input['website'] ?? '')),
                'phone' => trim((string)($input['phone'] ?? '')),
                'description' => trim((string)($input['description'] ?? '')),
                'add_user_id' => (int)$this->getCurrentUserId(),
            ]);
            require_once __DIR__ . '/../models/Label.php';
            if (array_key_exists('labels', $input)) {
                Label::syncForGuideObject($id, $input['labels']);
            }
            $this->logUserAction('create_guide_object', ['id' => $id]);
            $this->audit('create', 'place', $id, 'Добавил место «' . mb_substr($name, 0, 80) . '»', 'guide');
            $item = GuideObject::findExpanded($id);
            $item['permissions'] = $this->guidePermissions($item, (int)$this->getCurrentUserId());
            $this->json(['success' => true, 'data' => $item, 'meta' => $this->getRequestInfo()], 201);
        } catch (Throwable $e) {
            Logger::error('GuideObjectController: create error', ['error' => $e->getMessage()]);
            $this->json(['success' => false, 'error' => ['code' => 'INTERNAL_ERROR', 'message' => $e->getMessage()]], 500);
        }
    }

    /**
     * Править место может тот, кто его добавил, либо модератор/админ.
     */
    public function update($id)
    {
        try {
            if (!$this->requireAccess('api.guide-objects.update')) {
                return;
            }
            $item = GuideObject::findExpanded((int)$id);
            if (!$item) {
                $this->json(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Место не найдено']], 404);
                return;
            }
            if (!$this->guidePermissions($item, (int)$this->getCurrentUserId())['canEdit']) {
                $this->json(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Править может автор или модератор']], 403);
                return;
            }
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $payload = [];
            foreach (['name', 'city', 'address', 'website', 'phone', 'description'] as $k) {
                if (array_key_exists($k, $input)) {
                    $payload[$k] = trim((string)$input[$k]);
                }
            }
            if (isset($payload['name']) && $payload['name'] === '') {
                $this->json(['success' => false, 'error' => ['code' => 'VALIDATION', 'message' => 'Нужно название']], 400);
                return;
            }
            GuideObject::updateById((int)$id, $payload);
            if (array_key_exists('labels', $input)) {
                require_once __DIR__ . '/../models/Label.php';
                Label::syncForGuideObject((int)$id, $input['labels']);
            }
            $fresh = GuideObject::findExpanded((int)$id);
            $fresh['permissions'] = $this->guidePermissions($fresh, (int)$this->getCurrentUserId());
            $this->audit('update', 'place', (int)$id, 'Изменил место «' . mb_substr((string)($fresh['name'] ?? $item['name'] ?? ''), 0, 80) . '»', 'guide');
            $this->json(['success' => true, 'data' => $fresh, 'meta' => $this->getRequestInfo()]);
        } catch (Throwable $e) {
            Logger::error('GuideObjectController: update error', ['error' => $e->getMessage()]);
            $this->json(['success' => false, 'error' => ['code' => 'INTERNAL_ERROR', 'message' => $e->getMessage()]], 500);
        }
    }

    public function delete($id)
    {
        try {
            if (!$this->requireAccess('api.guide-objects.delete')) {
                return;
            }
            if (!$this->isModerator() && !$this->isAdmin()) {
                $this->json(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Удалить место может модератор или админ']], 403);
                return;
            }
            $item = GuideObject::findExpanded((int)$id);
            if (!$item) {
                $this->json(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Место не найдено']], 404);
                return;
            }
            GuideObject::updateStatus((int)$id, Status::idByCode('deleted', 3));
            $this->audit('delete', 'place', (int)$id, 'Удалил место «' . mb_substr((string)($item['name'] ?? ''), 0, 80) . '»', 'guide');
            $this->json(['success' => true, 'data' => ['id' => (int)$id, 'status' => 'deleted'], 'meta' => $this->getRequestInfo()]);
        } catch (Throwable $e) {
            Logger::error('GuideObjectController: delete error', ['error' => $e->getMessage()]);
            $this->json(['success' => false, 'error' => ['code' => 'INTERNAL_ERROR', 'message' => $e->getMessage()]], 500);
        }
    }

    private function guidePermissions($item, $uid)
    {
        $authorId = (int)($item['add_user_id'] ?? $item['author']['id'] ?? 0);
        $canEdit = ($authorId && $authorId === $uid) || $this->isModerator() || $this->isAdmin();
        return [
            'canEdit' => $canEdit,
            'canDelete' => $this->isModerator() || $this->isAdmin(),
            'canReview' => $uid > 0,
        ];
    }
}
