<?php
/**
 * load_env.php — универсальный загрузчик переменных окружения из .env для CabrioRide.
 *
 * Подключайте этот файл в начале любого PHP-скрипта, где нужны переменные окружения.
 * После подключения все переменные из .env будут доступны через getenv('KEY'), $_ENV['KEY'] и $_SERVER['KEY'].
 */
$env_path = __DIR__ . '/../../.env';
if (file_exists($env_path)) {
    $lines = file($env_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key . '=' . $value);
        }
    }
} 

// Всегда храним время в БД в UTC
// Устанавливаем таймзону PHP-процесса явно на UTC, чтобы date()/time() возвращали время в UTC
if (function_exists('date_default_timezone_set')) {
	date_default_timezone_set('UTC');
}