<?php
/**
 * Модель ModerationLog — история действий модераторов (таблица moderation_logs).
 *
 * Назначение:
 *   Представляет запись о действии модератора с профилем пользователя (активация, блокировка и т.д.).
 *   Используется для аудита, анализа работы модерации, прозрачности.
 *
 * Ключевые поля:
 *   - id: уникальный идентификатор
 *   - user_id: FK на users (над кем действие)
 *   - moderator_id: FK на users (кто выполнил действие)
 *   - action: тип действия ('activate', 'block', ...)
 *   - reason: причина (если применимо)
 *   - created_at
 *
 * Связи:
 *   - User (user_id → users.id)
 *   - Moderator (moderator_id → users.id)
 *
 * Пример использования:
 *   $log = ModerationLog::findById(1);
 *   $newLog = ModerationLog::create([...]);
 */
class ModerationLog {
    public $id;
    public $user_id;
    public $moderator_id;
    public $action;
    public $reason;
    public $created_at;

    public function __construct($data) {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * Найти лог по id
     */
    public static function findById($id) {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM moderation_logs WHERE id = ?');
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        return $data ? new self($data) : null;
    }

    /**
     * Создать новый лог
     */
    public static function create($data) {
        // ... реализация вставки в БД
    }
} 