<?php
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../utils/Database.php';

class RefController extends BaseController
{
    /**
     * GET /api/ref/car-brands
     * Минимальная роль: guest
     */
    public function getCarBrands()
    {
        try {
            if (!$this->requireAccess('api.ref.getCarBrands')) { return; }
            $pdo = Database::getInstance();
            $rows = $pdo->query('SELECT id, brand as name FROM ref_car_brands ORDER BY brand')->fetchAll(PDO::FETCH_ASSOC) ?: [];
            $this->json(['success' => true, 'data' => $rows, 'meta' => $this->getRequestInfo()]);
        } catch (Throwable $e) {
            $this->json(['success' => false, 'error' => ['code' => 'INTERNAL_ERROR', 'message' => $e->getMessage()]], 500);
        }
    }

    public function getEventTypes()
    {
        try {
            if (!$this->requireAccess('api.ref.getEventTypes')) { return; }
            require_once __DIR__ . '/../models/EventType.php';
            $this->json(['success' => true, 'data' => EventType::getAll(), 'meta' => $this->getRequestInfo()]);
        } catch (Throwable $e) {
            $this->json(['success' => false, 'error' => ['code' => 'INTERNAL_ERROR', 'message' => $e->getMessage()]], 500);
        }
    }

    public function getGuideObjectTypes()
    {
        try {
            if (!$this->requireAccess('api.ref.getGuideObjectTypes')) { return; }
            require_once __DIR__ . '/../models/GuideObjectType.php';
            $this->json(['success' => true, 'data' => GuideObjectType::getAll(), 'meta' => $this->getRequestInfo()]);
        } catch (Throwable $e) {
            $this->json(['success' => false, 'error' => ['code' => 'INTERNAL_ERROR', 'message' => $e->getMessage()]], 500);
        }
    }

    /**
     * GET /api/ref/labels — ярлыки (хештеги) для подсказок в форме места
     */
    public function getLabels()
    {
        try {
            if (!$this->requireAccess('api.ref.getLabels')) { return; }
            require_once __DIR__ . '/../models/Label.php';
            $this->json(['success' => true, 'data' => Label::getAll(), 'meta' => $this->getRequestInfo()]);
        } catch (Throwable $e) {
            $this->json(['success' => true, 'data' => [], 'meta' => $this->getRequestInfo()]);
        }
    }

    public function getGuideObjectKinds()
    {
        try {
            if (!$this->requireAccess('api.ref.getGuideObjectKinds')) { return; }
            require_once __DIR__ . '/../models/GuideObjectKind.php';
            $typeId = isset($_GET['type_id']) ? (int)$_GET['type_id'] : 0;
            $this->json(['success' => true, 'data' => GuideObjectKind::getAll($typeId ?: null), 'meta' => $this->getRequestInfo()]);
        } catch (Throwable $e) {
            $this->json(['success' => false, 'error' => ['code' => 'INTERNAL_ERROR', 'message' => $e->getMessage()]], 500);
        }
    }
}


