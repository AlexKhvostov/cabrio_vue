<?php
/**
 * События клуба: список, карточка и «еду» с роли user; создать / править — member.
 */
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Event.php';
require_once __DIR__ . '/../models/Status.php';
require_once __DIR__ . '/../models/LinkEventParticipant.php';

class EventController extends BaseController
{
    public function getList()
    {
        try {
            if (!$this->requireAccess('api.events.getList')) {
                return;
            }
            $events = Event::getAll($this->getCurrentUserId());
            $uid = (int)$this->getCurrentUserId();
            foreach ($events as &$e) {
                $e['permissions'] = $this->eventPermissions($e, $uid);
                $this->hidePeopleIfNeeded($e);
            }
            unset($e);
            $this->logUserAction('get_events_list', ['count' => count($events)]);
            $this->json(['success' => true, 'data' => $events, 'meta' => $this->getRequestInfo()]);
        } catch (Throwable $e) {
            Logger::error('EventController: getList error', ['error' => $e->getMessage(), 'user_id' => $this->getCurrentUserId()]);
            $this->json(['success' => false, 'error' => ['code' => 'DB_ERROR', 'message' => $e->getMessage()]], 500);
        }
    }

    public function getById($id)
    {
        try {
            if (!$this->requireAccess('api.events.getById')) {
                return;
            }
            $event = Event::findExpanded((int)$id, $this->getCurrentUserId());
            if (!$event) {
                $this->json(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Событие не найдено']], 404);
                return;
            }
            $event['permissions'] = $this->eventPermissions($event, (int)$this->getCurrentUserId());
            $this->hidePeopleIfNeeded($event);
            $this->json(['success' => true, 'data' => $event, 'meta' => $this->getRequestInfo()]);
        } catch (Throwable $e) {
            Logger::error('EventController: getById error', ['error' => $e->getMessage()]);
            $this->json(['success' => false, 'error' => ['code' => 'DB_ERROR', 'message' => $e->getMessage()]], 500);
        }
    }

    public function create()
    {
        try {
            if (!$this->requireAccess('api.events.create')) {
                return;
            }
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $title = trim((string)($input['title'] ?? ''));
            $date = trim((string)($input['event_date'] ?? ''));
            $time = trim((string)($input['event_time'] ?? ''));
            $location = trim((string)($input['location'] ?? ''));
            if ($title === '' || $date === '' || $time === '' || $location === '') {
                $this->json(['success' => false, 'error' => ['code' => 'VALIDATION', 'message' => 'Нужны название, дата, время и место']], 400);
                return;
            }
            if (strtotime($date . ' ' . $time) < time() - 60) {
                $this->json(['success' => false, 'error' => ['code' => 'VALIDATION', 'message' => 'Дата и время не должны быть в прошлом']], 400);
                return;
            }
            $private = !empty($input['is_private']);
            $id = Event::create([
                'title' => $title,
                'event_date' => $date,
                'event_time' => $time,
                'location' => $location,
                'city' => trim((string)($input['city'] ?? '')),
                'description' => trim((string)($input['description'] ?? '')),
                'event_type_id' => $input['event_type_id'] ? (int)$input['event_type_id'] : null,
                'max_participants' => $input['max_participants'] !== '' && isset($input['max_participants']) ? (int)$input['max_participants'] : null,
                'org_user_id' => (int)$this->getCurrentUserId(),
                'registration_type' => $private ? 'invitation' : 'free',
            ]);
            $this->logUserAction('create_event', ['event_id' => $id]);
            $this->audit('create', 'event', $id, 'Создал встречу «' . mb_substr($title, 0, 80) . '»', 'events');
            $event = Event::findExpanded($id, $this->getCurrentUserId());
            $event['permissions'] = $this->eventPermissions($event, (int)$this->getCurrentUserId());
            $this->json(['success' => true, 'data' => $event, 'meta' => $this->getRequestInfo()], 201);
        } catch (Throwable $e) {
            Logger::error('EventController: create error', ['error' => $e->getMessage(), 'user_id' => $this->getCurrentUserId()]);
            $this->json(['success' => false, 'error' => ['code' => 'INTERNAL_ERROR', 'message' => $e->getMessage()]], 500);
        }
    }

    public function update($id)
    {
        try {
            if (!$this->requireAccess('api.events.update')) {
                return;
            }
            $event = Event::findExpanded((int)$id, $this->getCurrentUserId());
            if (!$event) {
                $this->json(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Событие не найдено']], 404);
                return;
            }
            if (!$this->eventPermissions($event, (int)$this->getCurrentUserId())['canEdit']) {
                $this->json(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Править может организатор или модератор']], 403);
                return;
            }
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $payload = [];
            foreach (['title', 'event_date', 'event_time', 'location', 'city', 'description'] as $k) {
                if (array_key_exists($k, $input)) {
                    $payload[$k] = trim((string)$input[$k]);
                }
            }
            if (array_key_exists('event_type_id', $input)) {
                $payload['event_type_id'] = $input['event_type_id'] ? (int)$input['event_type_id'] : null;
            }
            if (array_key_exists('max_participants', $input)) {
                $payload['max_participants'] = $input['max_participants'] === '' || $input['max_participants'] === null
                    ? null : (int)$input['max_participants'];
            }
            if (array_key_exists('is_private', $input)) {
                $payload['registration_type'] = !empty($input['is_private']) ? 'invitation' : 'free';
            }
            Event::updateById((int)$id, $payload);
            $fresh = Event::findExpanded((int)$id, $this->getCurrentUserId());
            $this->audit('update', 'event', (int)$id, 'Изменил встречу «' . mb_substr((string)($fresh['title'] ?? $event['title'] ?? ''), 0, 80) . '»', 'events');
            $fresh['permissions'] = $this->eventPermissions($fresh, (int)$this->getCurrentUserId());
            $this->json(['success' => true, 'data' => $fresh, 'meta' => $this->getRequestInfo()]);
        } catch (Throwable $e) {
            Logger::error('EventController: update error', ['error' => $e->getMessage()]);
            $this->json(['success' => false, 'error' => ['code' => 'INTERNAL_ERROR', 'message' => $e->getMessage()]], 500);
        }
    }

    public function delete($id)
    {
        try {
            if (!$this->requireAccess('api.events.delete')) {
                return;
            }
            $event = Event::findExpanded((int)$id, $this->getCurrentUserId());
            if (!$event) {
                $this->json(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Событие не найдено']], 404);
                return;
            }
            if (!$this->eventPermissions($event, (int)$this->getCurrentUserId())['canEdit']) {
                $this->json(['success' => false, 'error' => ['code' => 'FORBIDDEN', 'message' => 'Удалить может организатор или модератор']], 403);
                return;
            }
            Event::updateStatus((int)$id, Status::idByCode('deleted', 3));
            $this->logUserAction('delete_event', ['event_id' => (int)$id]);
            $this->audit('delete', 'event', (int)$id, 'Удалил встречу «' . mb_substr((string)($event['title'] ?? ''), 0, 80) . '»', 'events');
            $this->json(['success' => true, 'data' => ['id' => (int)$id, 'status' => 'deleted'], 'meta' => $this->getRequestInfo()]);
        } catch (Throwable $e) {
            Logger::error('EventController: delete error', ['error' => $e->getMessage()]);
            $this->json(['success' => false, 'error' => ['code' => 'INTERNAL_ERROR', 'message' => $e->getMessage()]], 500);
        }
    }

    public function rsvp($id)
    {
        try {
            if (!$this->requireAccess('api.events.rsvp')) {
                return;
            }
            $event = Event::findExpanded((int)$id, $this->getCurrentUserId());
            if (!$event) {
                $this->json(['success' => false, 'error' => ['code' => 'NOT_FOUND', 'message' => 'Событие не найдено']], 404);
                return;
            }
            $input = json_decode(file_get_contents('php://input'), true) ?: [];
            $confidence = strtolower(trim((string)($input['confidence'] ?? '')));
            LinkEventParticipant::upsert((int)$id, (int)$this->getCurrentUserId(), $confidence, !empty($input['plus_one']));
            $fresh = Event::findExpanded((int)$id, $this->getCurrentUserId());
            $rsvpRu = ['yes' => 'еду', 'going' => 'еду', 'maybe' => 'может быть', 'no' => 'не еду'][$confidence] ?? $confidence;
            $this->audit('update', 'event', (int)$id, 'Отметил участие («' . $rsvpRu . '») во встрече «' . mb_substr((string)($fresh['title'] ?? $event['title'] ?? ''), 0, 80) . '»', 'events');
            $fresh['permissions'] = $this->eventPermissions($fresh, (int)$this->getCurrentUserId());
            $this->json(['success' => true, 'data' => $fresh, 'meta' => $this->getRequestInfo()]);
        } catch (InvalidArgumentException $e) {
            $this->json(['success' => false, 'error' => ['code' => 'VALIDATION', 'message' => $e->getMessage()]], 400);
        } catch (Throwable $e) {
            Logger::error('EventController: rsvp error', ['error' => $e->getMessage()]);
            $this->json(['success' => false, 'error' => ['code' => 'INTERNAL_ERROR', 'message' => $e->getMessage()]], 500);
        }
    }

    private function eventPermissions($event, $uid)
    {
        $orgId = (int)($event['org_user_id'] ?? $event['organizer']['id'] ?? 0);
        $canEdit = ($orgId && $orgId === $uid) || $this->isModerator() || $this->isAdmin();
        return [
            'canEdit' => $canEdit,
            'canRsvp' => $this->checkAccess('api.events.rsvp'),
            'canSeeRsvpNames' => $this->checkAccess('api.users.getList'),
        ];
    }

    // Роль user видит когда и где, но не список людей (это раздел «Участники»).
    private function hidePeopleIfNeeded(&$event)
    {
        if (!empty($event['permissions']['canSeeRsvpNames'])) {
            return;
        }
        $event['rsvp_going'] = [];
        $event['rsvp_maybe'] = [];
        $event['organizer'] = null;
    }
}
