<?php
/**
 * Модель Status — справочник статусов (таблица ref_statuses).
 *
 * Назначение:
 *   Представляет статус сущности (active, pending, blocked и т.д.).
 *   Используется для фильтрации, отображения, контроля доступа к сущностям.
 *
 * Ключевые поля:
 *   - id: уникальный идентификатор
 *   - code: строковый код статуса
 *   - name: название статуса
 *   - description: описание
 *   - color: цвет для UI
 *   - entity_type: тип сущности (user, car, event и т.д.)
 *
 * Связи:
 *   - Cars, Events, GuideObjects, Reviews (status_id → ref_statuses.id)
 *
 * Пример использования:
 *   $status = Status::findById(1);
 *   $status = Status::findByCode('active');
 */
require_once __DIR__ . '/../utils/Database.php';

class Status {
    public $id;
    public $code;
    public $name;
    public $description;
    public $color;
    public $entity_type;

    public function __construct($data) {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * Найти статус по id
     */
    public static function findById($id) {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM ref_statuses WHERE id = ?');
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        return $data ? new self($data) : null;
    }

    /**
     * Найти статус по code
     */
    public static function findByCode($code) {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM ref_statuses WHERE code = ?');
        $stmt->execute([$code]);
        $data = $stmt->fetch();
        return $data ? new self($data) : null;
    }

    /**
     * Числовой id статуса по коду (active, deleted…). Если нет — запасной id из справочника.
     */
    public static function idByCode($code, $fallback = 1) {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT id FROM ref_statuses WHERE LOWER(code) = LOWER(?) LIMIT 1');
        $stmt->execute([$code]);
        $id = $stmt->fetchColumn();
        return $id ? (int)$id : (int)$fallback;
    }
} 