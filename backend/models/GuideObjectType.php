<?php
/**
 * Модель GuideObjectType — справочник типов гид-объектов (таблица ref_guide_object_types).
 *
 * Назначение:
 *   Представляет тип гид-объекта (service, cafe и т.д.).
 *   Используется для фильтрации, создания и отображения гид-объектов.
 *
 * Ключевые поля:
 *   - id: уникальный идентификатор
 *   - code: строковый код типа
 *   - name: название типа
 *   - description: описание
 *   - color: цвет для UI
 *
 * Связи:
 *   - GuideObjects (guide_objects.guide_object_type_id → ref_guide_object_types.id)
 *   - GuideObjectKinds (ref_guide_object_kinds.type_id → ref_guide_object_types.id)
 *
 * Пример использования:
 *   $type = GuideObjectType::findById(1);
 *   $type = GuideObjectType::findByCode('service');
 */
require_once __DIR__ . '/../utils/Database.php';

class GuideObjectType {
    public $id;
    public $code;
    public $name;
    public $description;
    public $color;

    public function __construct($data) {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * Найти тип по id
     */
    public static function findById($id) {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM ref_guide_object_types WHERE id = ?');
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        return $data ? new self($data) : null;
    }

    /**
     * Найти тип по code
     */
    public static function findByCode($code) {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM ref_guide_object_types WHERE code = ?');
        $stmt->execute([$code]);
        $data = $stmt->fetch();
        return $data ? new self($data) : null;
    }

    public static function getAll() {
        $pdo = Database::getInstance();
        $stmt = $pdo->query('SELECT id, code, name FROM ref_guide_object_types ORDER BY id');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} 