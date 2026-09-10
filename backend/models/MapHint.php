<?php
/**
 * Модель MapHint — работа с таблицей map_hints.
 *
 * Назначение:
 *   Представляет подсказку/метку на карте (ГАИ, ремонт, пробка и т.д.).
 *   Используется для отображения на карте, фильтрации, управления актуальностью.
 *
 * Ключевые поля:
 *   - id: уникальный идентификатор
 *   - user_id: FK на users (кто поставил)
 *   - type, latitude, longitude, created_at, expires_at, active, removed_by, removed_at
 *
 * Связи:
 *   - User (user_id → users.id)
 *   - RemovedBy (removed_by → users.id)
 *
 * Пример использования:
 *   $hint = MapHint::findById(1);
 *   $newHint = MapHint::create([...]);
 */
class MapHint {
    public $id;
    public $user_id;
    public $type;
    public $latitude;
    public $longitude;
    public $created_at;
    public $expires_at;
    public $active;
    public $removed_by;
    public $removed_at;

    public function __construct($data) {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * Найти подсказку по id
     */
    public static function findById($id) {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM map_hints WHERE id = ?');
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        return $data ? new self($data) : null;
    }

    /**
     * Создать новую подсказку
     */
    public static function create($data) {
        // ... реализация вставки в БД
    }

    /**
     * Удалить подсказку
     */
    public function delete() {
        // ... реализация удаления из БД
    }
} 