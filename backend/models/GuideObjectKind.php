<?php
/**
 * Модель GuideObjectKind — справочник видов гид-объектов (таблица ref_guide_object_kinds).
 *
 * Назначение:
 *   Представляет вид гид-объекта (например, "breakfast" для кафе).
 *   Используется для фильтрации, создания и отображения гид-объектов.
 *
 * Ключевые поля:
 *   - id: уникальный идентификатор
 *   - type_id: FK на ref_guide_object_types
 *   - code: строковый код вида
 *   - name: название вида
 *   - description: описание
 *
 * Связи:
 *   - GuideObjectType (type_id → ref_guide_object_types.id)
 *   - GuideObjects (guide_objects.guide_object_kind_id → ref_guide_object_kinds.id)
 *
 * Пример использования:
 *   $kind = GuideObjectKind::findById(1);
 *   $kind = GuideObjectKind::findByCode('breakfast');
 */
require_once __DIR__ . '/../utils/Database.php';

class GuideObjectKind {
    public $id;
    public $type_id;
    public $code;
    public $name;
    public $description;

    public function __construct($data) {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * Найти вид по id
     */
    public static function findById($id) {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM ref_guide_object_kinds WHERE id = ?');
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        return $data ? new self($data) : null;
    }

    /**
     * Найти вид по code
     */
    public static function findByCode($code) {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM ref_guide_object_kinds WHERE code = ?');
        $stmt->execute([$code]);
        $data = $stmt->fetch();
        return $data ? new self($data) : null;
    }

    /**
     * Все виды, при необходимости только для выбранного типа.
     */
    public static function getAll($typeId = null) {
        $pdo = Database::getInstance();
        if ($typeId) {
            $stmt = $pdo->prepare('SELECT id, type_id, code, name, description FROM ref_guide_object_kinds WHERE type_id = ? ORDER BY name');
            $stmt->execute([(int)$typeId]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        $stmt = $pdo->query('SELECT id, type_id, code, name, description FROM ref_guide_object_kinds ORDER BY type_id, name');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} 