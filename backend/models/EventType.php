<?php
/**
 * Модель EventType — справочник типов событий (таблица ref_event_types).
 *
 * Назначение:
 *   Представляет тип события (trip, meetup и т.д.).
 *   Используется для фильтрации, отображения, создания событий.
 *
 * Ключевые поля:
 *   - id: уникальный идентификатор
 *   - code: строковый код типа
 *   - name: название типа
 *   - description: описание
 *   - color: цвет для UI
 *
 * Связи:
 *   - Events (events.event_type_id → ref_event_types.id)
 *
 * Пример использования:
 *   $type = EventType::findById(1);
 *   $type = EventType::findByCode('meetup');
 */
require_once __DIR__ . '/../utils/Database.php';

class EventType {
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
     * Найти тип события по id
     */
    public static function findById($id) {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM ref_event_types WHERE id = ?');
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        return $data ? new self($data) : null;
    }

    /**
     * Найти тип события по code
     */
    public static function findByCode($code) {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM ref_event_types WHERE code = ?');
        $stmt->execute([$code]);
        $data = $stmt->fetch();
        return $data ? new self($data) : null;
    }

    public static function getAll() {
        $pdo = Database::getInstance();
        $stmt = $pdo->query('SELECT id, code, name FROM ref_event_types ORDER BY id');
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
} 