<?php
/**
 * Logger — простой класс для логирования событий и ошибок в backend CabrioRide.
 * Логи пишутся в backend/logs/app.log и backend/logs/error.log.
 * Используйте Logger::info(), Logger::warning() и Logger::error() для записи информации.
 */
class Logger {
    /**
     * Включать ли информационное логирование (управляется DEBUG=true в .env)
     */
    private static function isLoggingEnabled() {
        // На боевом сервере подробные логи выключены, даже если в .env забыли DEBUG=true
        $env = strtolower((string)(getenv('APP_ENV') ?: ''));
        if ($env === 'production') {
            return false;
        }
        // Локально: пишем info/warning только при DEBUG=true или APP_DEBUG=true
        $debug = strtolower((string)(getenv('DEBUG') ?: getenv('APP_DEBUG') ?: ''));
        return $debug === 'true' || $debug === '1';
    }

    public static function info($message, $context = []) {
        if (!self::isLoggingEnabled()) return;
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        file_put_contents(__DIR__ . '/../logs/app.log', date('c') . " [INFO] $message$contextStr\n", FILE_APPEND);
    }
    
    public static function warning($message, $context = []) {
        if (!self::isLoggingEnabled()) return;
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        file_put_contents(__DIR__ . '/../logs/app.log', date('c') . " [WARNING] $message$contextStr\n", FILE_APPEND);
    }
    
    public static function error($message, $context = []) {
        $contextStr = !empty($context) ? ' | Context: ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
        file_put_contents(__DIR__ . '/../logs/error.log', date('c') . " [ERROR] $message$contextStr\n", FILE_APPEND);
    }
}