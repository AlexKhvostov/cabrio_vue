<?php
require_once __DIR__ . '/../utils/Database.php';

/**
 * Модель Session — работа с таблицей sessions.
 *
 * Назначение:
 *   Представляет сессию пользователя (авторизация, хранение токена).
 *   Используется для проверки авторизации, управления сессиями, интеграции с Telegram.
 *
 * Ключевые поля:
 *   - id: уникальный идентификатор
 *   - user_id: FK на users (пользователь)
 *   - session_token: токен сессии
 *   - telegram_data: JSON-данные Telegram (если применимо)
 *   - created_at, expires_at, is_active
 *
 * Связи:
 *   - User (user_id → users.id)
 *
 * Пример использования:
 *   $session = Session::findById(1);
 *   $session = Session::findByToken('...');
 *   $session = Session::findByTelegramId(123456789);
 *   $newSession = Session::create([...]);
 */
class Session {
    public $id;
    public $user_id;
    public $session_token;
    public $telegram_data;
    public $created_at;
    public $expires_at;
    public $is_active;

    public function __construct($data) {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * Найти сессию по id
     */
    public static function findById($id) {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM sessions WHERE id = ?');
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        return $data ? new self($data) : null;
    }

    /**
     * Найти сессию по токену
     */
    public static function findByToken($token) {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM sessions WHERE session_token = ?');
        $stmt->execute([$token]);
        $data = $stmt->fetch();
        return $data ? new self($data) : null;
    }

    /**
     * Найти сессию по telegram_id пользователя
     * (telegram_id ищется через users, предполагается связь sessions.user_id = users.id)
     */
    public static function findByTelegramId($telegram_id) {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('
            SELECT s.* FROM sessions s
            JOIN users u ON s.user_id = u.id
            WHERE u.telegram_id = ?
            ORDER BY s.created_at DESC
            LIMIT 1
        ');
        $stmt->execute([$telegram_id]);
        $data = $stmt->fetch();
        return $data ? new self($data) : null;
    }

    /**
     * Найти сессию по user_id
     */
    public static function findByUserId($user_id) {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('
            SELECT * FROM sessions 
            WHERE user_id = ? AND is_active = 1
            ORDER BY created_at DESC
            LIMIT 1
        ');
        $stmt->execute([$user_id]);
        $data = $stmt->fetch();
        return $data ? new self($data) : null;
    }

    /**
     * Создать новую сессию
     */
    public static function create($data) {
        $pdo = Database::getInstance();
        
        $sql = 'INSERT INTO sessions (user_id, session_token, created_at, expires_at, is_active, telegram_data) VALUES (?, ?, ?, ?, ?, ?)';
        $stmt = $pdo->prepare($sql);
        
        $result = $stmt->execute([
            $data['user_id'],
            $data['session_token'],
            $data['created_at'],
            $data['expires_at'],
            $data['is_active'],
            $data['telegram_data'] ?? null
        ]);
        
        if ($result) {
            return $data['session_token']; // Возвращаем токен сессии
        }
        
        return false;
    }

    /**
     * Обновить сессию
     */
    public static function update($id, $data) {
        $pdo = Database::getInstance();
        
        $sql = 'UPDATE sessions SET session_token = ?, expires_at = ?';
        $params = [$data['session_token'], $data['expires_at']];
        
        // Добавляем telegram_data если предоставлено
        if (isset($data['telegram_data'])) {
            $sql .= ', telegram_data = ?';
            $params[] = $data['telegram_data'];
        }
        
        $sql .= ' WHERE id = ?';
        $params[] = $id;
        
        $stmt = $pdo->prepare($sql);
        return $stmt->execute($params);
    }
} 