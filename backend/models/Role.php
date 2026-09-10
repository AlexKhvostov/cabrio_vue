<?php
/**
 * Модель Role — справочник ролей пользователей (таблица ref_roles).
 *
 * Назначение:
 *   Представляет роль пользователя (member, moderator, admin и т.д.).
 *   Используется для контроля доступа, отображения статуса, фильтрации.
 *
 * Ключевые поля:
 *   - id: уникальный идентификатор
 *   - code: строковый код роли
 *   - name: название роли
 *   - description: описание
 *   - color: цвет для UI
 *
 * Связи:
 *   - Users (users.role_id → ref_roles.id)
 *   - LinkUserCar (role_id → ref_roles.id)
 *
 * Пример использования:
 *   $role = Role::findById(1);
 *   $role = Role::findByCode('member');
 */
class Role {
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
     * Найти роль по id
     */
    public static function findById($id) {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM ref_roles WHERE id = ?');
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        return $data ? new self($data) : null;
    }

    /**
     * Найти роль по code
     */
    public static function findByCode($code) {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM ref_roles WHERE code = ?');
        $stmt->execute([$code]);
        $data = $stmt->fetch();
        return $data ? new self($data) : null;
    }
} 