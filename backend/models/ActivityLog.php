<?php
/**
 * Модель ActivityLog — работа с таблицей activity_logs.
 *
 * Назначение:
 *   Представляет запись о выдаче активности между пользователями.
 *   Используется для контроля лимитов, истории, аудита.
 *
 * Ключевые поля:
 *   - id: уникальный идентификатор
 *   - from_user_id: FK на users (кто поставил активность)
 *   - to_user_id: FK на users (кому поставлена активность)
 *   - date, created_at
 *
 * Связи:
 *   - FromUser (from_user_id → users.id)
 *   - ToUser (to_user_id → users.id)
 *
 * Пример использования:
 *   $log = ActivityLog::findById(1);
 *   $newLog = ActivityLog::create([...]);
 */
class ActivityLog {
    public $id;
    public $from_user_id;
    public $to_user_id;
    public $date;
    public $created_at;

    public function __construct($data) {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * Найти активность по id
     */
    public static function findById($id) {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM activity_logs WHERE id = ?');
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        return $data ? new self($data) : null;
    }

    /**
     * Создать новую активность
     */
    public static function create($data) {
        // ... реализация вставки в БД
    }
} 