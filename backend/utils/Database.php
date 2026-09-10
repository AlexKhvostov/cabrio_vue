<?php
require_once __DIR__ . '/load_env.php';

/**
 * Database — Singleton для подключения к MySQL через PDO для backend CabrioRide.
 * Все параметры подключения берутся из .env (используйте getenv/$_ENV).
 * Используйте Database::getInstance() для получения PDO.
 *
 * Пример:
 * $pdo = Database::getInstance();
 * $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
 * $stmt->execute([$id]);
 * $user = $stmt->fetch();
 */
class Database {
    private static $instance = null;
    private function __construct() {}
    private function __clone() {}

    public static function getInstance() {
        if (self::$instance === null) {
            $dsn = 'mysql:host=' . (getenv('DB_HOST') ?: 'localhost') .
                ';port=' . (getenv('DB_PORT') ?: '3306') .
                ';dbname=' . (getenv('DB_NAME') ?: '') .
                ';charset=utf8mb4';
            $user = getenv('DB_USER') ?: '';
            $pass = getenv('DB_PASSWORD') ?: '';
            try {
                self::$instance = new \PDO($dsn, $user, $pass, [
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                ]);
                // ВАЖНО: уравниваем таймзону MySQL с PHP (UTC), чтобы сравнения DATETIME были корректны
                try { self::$instance->exec("SET time_zone = '+00:00'"); } catch (\Throwable $e) { /* ignore */ }
            } catch (\PDOException $e) {
                // Логируем ошибку и выбрасываем исключение
                if (class_exists('Logger')) {
                    Logger::error('DB connection failed: ' . $e->getMessage());
                }
                throw $e;
            }
        }
        return self::$instance;
    }
} 