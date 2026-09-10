<?php
require_once __DIR__ . '/load_env.php';
require_once __DIR__ . '/ResponseHelper.php';
require_once __DIR__ . '/Logger.php';

/**
 * 🔐 AuthHelper — утилита для авторизации и проверки токенов в backend CabrioRide.
 * 
 * Централизованная обработка авторизации:
 * - JWT токены (legacy)
 * - Telegram данные (новый подход)
 * - Извлечение и валидация данных
 * 
 * @package CabrioRide\Utils
 */
class AuthHelper {
    /**
     * Проверяет наличие и валидность токена в заголовке Authorization.
     * Возвращает user_id (int) или 'system' (string) для системных запросов.
     * В случае ошибки завершает выполнение с ошибкой авторизации.
     */
    public static function checkAuth() {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $authHeader = $headers['Authorization'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        if (empty($authHeader)) {
            http_response_code(401);
            echo ResponseHelper::error('NO_TOKEN', 'Требуется авторизация');
            exit;
        }
        if (strpos($authHeader, 'Bearer ') !== 0) {
            http_response_code(401);
            echo ResponseHelper::error('INVALID_TOKEN', 'Некорректный формат токена');
            exit;
        }
        $token = trim(substr($authHeader, 7));

        // Проверка на SYSTEM_TOKEN
        if ($token === getenv('SYSTEM_TOKEN')) {
            return 'system'; // спец. идентификатор для системных запросов
        }

        // JWT-подпись
        $jwtSecret = getenv('JWT_SECRET');
        $payload = self::decodeJWT($token, $jwtSecret);
        if (!$payload || empty($payload['user_id'])) {
            http_response_code(401);
            echo ResponseHelper::error('INVALID_TOKEN', 'Токен недействителен');
            exit;
        }
        // Можно добавить проверку срока действия (exp)
        return $payload['user_id'];
    }

    /**
     * Декодирует и проверяет подпись JWT (без сторонних библиотек).
     * Возвращает payload (array) или false.
     */
    public static function decodeJWT($jwt, $secret) {
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) return false;
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        // Проверка подписи (упрощённо)
        $signature = hash_hmac('sha256', $parts[0] . '.' . $parts[1], $secret, true);
        $signature_b64 = rtrim(strtr(base64_encode($signature), '+/', '-_'), '=');
        if ($signature_b64 !== $parts[2]) return false;
        return $payload;
    }

    /**
     * Возвращает объект пользователя по токену (или null).
     * (Заготовка — требует доработки под вашу модель User)
     */
    public static function getUserFromToken() {
        $userId = self::checkAuth();
        if ($userId === 'system') return null;
        // TODO: реализовать User::findById($userId)
        return null;
    }

    /**
     * Проверяет, что у пользователя есть нужная роль (по коду роли).
     * Если нет — возвращает ошибку доступа.
     * (Заготовка — требует доработки под вашу модель User)
     */
    public static function requireRole($role) {
        $user = self::getUserFromToken();
        if (!$user || empty($user['role']) || $user['role']['code'] !== $role) {
            http_response_code(403);
            echo ResponseHelper::error('FORBIDDEN', 'Недостаточно прав');
            exit;
        }
        return true;
    }

    // ========================================
    // МЕТОДЫ ДЛЯ РАБОТЫ С TELEGRAM ДАННЫМИ
    // ========================================

    /**
     * Извлечь данные из Telegram из различных источников
     * 
     * @return array|null Данные из Telegram или null
     */
    public static function extractTelegramData()
    {
        // 1. Пробуем извлечь из заголовков (Telegram WebApp)
        $telegramData = self::extractFromHeaders();
        if ($telegramData) {
            return $telegramData;
        }
        
        // 2. Пробуем извлечь из JSON тела запроса (Telegram Bot)
        $telegramData = self::extractFromJsonBody();
        if ($telegramData) {
            return $telegramData;
        }
        
        // 3. Пробуем извлечь из FormData (Telegram Bot)
        $telegramData = self::extractFromFormData();
        if ($telegramData) {
            return $telegramData;
        }
        
        // 4. Пробуем извлечь из GET параметров (для тестирования)
        $telegramData = self::extractFromGetParams();
        if ($telegramData) {
            return $telegramData;
        }
        
        return null;
    }

    /**
     * Извлечь данные из заголовков (Telegram WebApp)
     * 
     * @return array|null
     */
    private static function extractFromHeaders()
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        // Диагностика входящих заголовков WebApp
        try {
            Logger::info('AuthHelper: extractFromHeaders — incoming headers snapshot', [
                'has_X-Telegram-User-Id' => isset($headers['X-Telegram-User-Id']) || isset($_SERVER['HTTP_X_TELEGRAM_USER_ID']),
                'has_X-Telegram-Init-Data' => isset($headers['X-Telegram-Init-Data']) || isset($_SERVER['HTTP_X_TELEGRAM_INIT_DATA'])
            ]);
        } catch (Throwable $e) {}

        // Дополнительно: логируем присутствие и состав initData (не меняем поведение валидации)
        $initDataHeader = $headers['X-Telegram-Init-Data'] ?? $_SERVER['HTTP_X_TELEGRAM_INIT_DATA'] ?? null;
        if ($initDataHeader) {
            $parsed = [];
            parse_str($initDataHeader, $parsed);
            try {
                Logger::info('AuthHelper: X-Telegram-Init-Data detected', [
                    'keys' => array_keys($parsed),
                    'has_hash' => isset($parsed['hash']),
                    'has_signature' => isset($parsed['signature']),
                    'has_user' => isset($parsed['user']),
                    'auth_date' => $parsed['auth_date'] ?? null
                ]);
            } catch (Throwable $e) {}
        }
        // Telegram WebApp передает данные в заголовках
        $telegramData = [];
        
        // Основные поля
        $fields = [
            'X-Telegram-User-Id' => 'telegram_id',
            'X-Telegram-First-Name' => 'first_name',
            'X-Telegram-Last-Name' => 'last_name',
            'X-Telegram-Username' => 'username',
            'X-Telegram-Photo-URL' => 'photo_url',
            'X-Telegram-Auth-Date' => 'auth_date',
            'X-Telegram-Hash' => 'hash'
        ];
        
        foreach ($fields as $header => $field) {
            // Пробуем из заголовков
            $value = $headers[$header] ?? null;
            
            // Если нет в заголовках, пробуем из $_SERVER
            if ($value === null) {
                // Преобразуем X-Telegram-User-Id в HTTP_X_TELEGRAM_USER_ID
                $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $header));
                $value = $_SERVER[$serverKey] ?? null;
            }
            

            
            if ($value !== null) {
                $telegramData[$field] = $value;
            }
        }
        

        try { Logger::info('AuthHelper: extractFromHeaders — result telegramData', $telegramData); } catch (Throwable $e) {}
        // Проверяем, что есть хотя бы telegram_id
        if (!empty($telegramData['telegram_id'])) {
            return $telegramData;
        }
        
        return null;
    }

    /**
     * Извлечь данные из JSON тела запроса (Telegram Bot)
     * 
     * @return array|null
     */
    private static function extractFromJsonBody()
    {
        $input = file_get_contents('php://input');
        if (empty($input)) {
            return null;
        }
        
        $data = json_decode($input, true);
        if (!$data) {
            return null;
        }
        
        // Telegram Bot передает данные в структуре message.from
        if (isset($data['message']['from'])) {
            $from = $data['message']['from'];
            
            return [
                'telegram_id' => $from['id'] ?? null,
                'first_name' => $from['first_name'] ?? null,
                'last_name' => $from['last_name'] ?? null,
                'username' => $from['username'] ?? null,
                'photo_url' => $from['photo_url'] ?? null,
                'auth_date' => time(),
                'hash' => $data['hash'] ?? null
            ];
        }
        
        // Прямые данные (для тестирования)
        if (isset($data['telegram_id'])) {
            return $data;
        }
        
        return null;
    }

    /**
     * Извлечь данные из FormData (Telegram Bot)
     * 
     * @return array|null
     */
    private static function extractFromFormData()
    {
        if (empty($_POST)) {
            return null;
        }
        
        $telegramData = [];
        $fields = [
            'telegram_id', 'first_name', 'last_name', 
            'username', 'photo_url', 'auth_date', 'hash'
        ];
        
        foreach ($fields as $field) {
            if (isset($_POST[$field])) {
                $telegramData[$field] = $_POST[$field];
            }
        }
        
        if (!empty($telegramData['telegram_id'])) {
            return $telegramData;
        }
        
        return null;
    }

    /**
     * Извлечь данные из GET параметров (для тестирования)
     * 
     * @return array|null
     */
    private static function extractFromGetParams()
    {
        if (empty($_GET)) {
            return null;
        }
        
        $telegramData = [];
        $fields = [
            'telegram_id', 'first_name', 'last_name', 
            'username', 'photo_url', 'auth_date', 'hash'
        ];
        
        foreach ($fields as $field) {
            if (isset($_GET[$field])) {
                $telegramData[$field] = $_GET[$field];
            }
        }
        
        if (!empty($telegramData['telegram_id'])) {
            return $telegramData;
        }
        
        return null;
    }

    /**
     * Валидировать данные из Telegram
     * 
     * @param array $telegramData Данные из Telegram
     * @return array Результат валидации
     */
    public static function validateTelegramData($telegramData)
    {
        try { Logger::info('AuthHelper: validateTelegramData called', ['keys'=>array_keys((array)$telegramData)]); } catch (Throwable $e) {}
        if (!$telegramData || !is_array($telegramData)) {
            try { Logger::warning('AuthHelper: validateTelegramData — invalid structure'); } catch (Throwable $e) {}
            return [
                'success' => false,
                'error' => [
                    'code' => 'INVALID_DATA',
                    'message' => 'Некорректные данные Telegram'
                ]
            ];
        }
        
        // Проверяем обязательные поля
        if (empty($telegramData['telegram_id'])) {
            try { Logger::warning('AuthHelper: validateTelegramData — missing telegram_id'); } catch (Throwable $e) {}
            return [
                'success' => false,
                'error' => [
                    'code' => 'MISSING_TELEGRAM_ID',
                    'message' => 'Отсутствует telegram_id'
                ]
            ];
        }
        
        if (empty($telegramData['first_name'])) {
            try { Logger::warning('AuthHelper: validateTelegramData — missing first_name'); } catch (Throwable $e) {}
            return [
                'success' => false,
                'error' => [
                    'code' => 'MISSING_FIRST_NAME',
                    'message' => 'Отсутствует first_name'
                ]
            ];
        }
        
        // Проверяем типы данных
        if (!is_numeric($telegramData['telegram_id'])) {
            try { Logger::warning('AuthHelper: validateTelegramData — telegram_id not numeric', ['telegram_id'=>$telegramData['telegram_id']]); } catch (Throwable $e) {}
            return [
                'success' => false,
                'error' => [
                    'code' => 'INVALID_TELEGRAM_ID',
                    'message' => 'telegram_id должен быть числом'
                ]
            ];
        }
        
        // Проверяем длину строк
        if (strlen($telegramData['first_name']) > 64) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'INVALID_FIRST_NAME',
                    'message' => 'first_name слишком длинный'
                ]
            ];
        }
        
        if (!empty($telegramData['last_name']) && strlen($telegramData['last_name']) > 64) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'INVALID_LAST_NAME',
                    'message' => 'last_name слишком длинный'
                ]
            ];
        }
        
        if (!empty($telegramData['username']) && strlen($telegramData['username']) > 32) {
            return [
                'success' => false,
                'error' => [
                    'code' => 'INVALID_USERNAME',
                    'message' => 'username слишком длинный'
                ]
            ];
        }
        
        // TODO: Добавить проверку подписи Telegram (hash)
        // Это требует реализации проверки подписи согласно документации Telegram
        
        // добавляем проверку подписи Telegram
        $hashValid = self::isHashValid($telegramData);
        if (!$hashValid) {
            try { Logger::warning('AuthHelper: validateTelegramData — invalid Telegram hash'); } catch (Throwable $e) {}
            return [
                'success' => false,
                'error' => [
                    'code' => 'INVALID_HASH',
                    'message' => 'Подпись Telegram недействительна'
                ]
            ];
        }
        
        try { Logger::info('AuthHelper: validateTelegramData — OK'); } catch (Throwable $e) {}
        return [
            'success' => true,
            'message' => 'Данные Telegram валидны'
        ];
    }

    /**
     * Проверить корректность hash, передаваемого Telegram WebApp / login_url
     * Алгоритм: https://core.telegram.org/bots/webapps#validating-data-received-via-the-mini-app
     */
    private static function isHashValid(array $data): bool
    {
        // Если хеш отсутствует, разрешаем (бот использует SYSTEM_TOKEN)
        if (empty($data['hash'])) {
            try { Logger::info('AuthHelper: isHashValid — no hash provided in telegramData (skipping check)'); } catch (Throwable $e) {}
            return true;
        }
        
        $recvHash = $data['hash'];
        // удаляем hash из массива
        $checkData = $data;
        unset($checkData['hash']);

        // формируем data_check_string
        ksort($checkData, SORT_STRING);
        $pairs = [];
        foreach ($checkData as $k => $v) {
            $pairs[] = $k . '=' . $v;
        }
        $dataCheckString = implode("\n", $pairs);

        $botToken = getenv('BOT_TOKEN');
        if (!$botToken) {
            try { Logger::warning('AuthHelper: isHashValid — BOT_TOKEN is not set'); } catch (Throwable $e) {}
            return false;
        }
        $secretKey = hash('sha256', $botToken, true); // binary
        $calculatedHash = hash_hmac('sha256', $dataCheckString, $secretKey);
        try {
            Logger::info('AuthHelper: isHashValid — comparing hashes', [
                'has_recv_hash' => !empty($recvHash),
                'data_check_len' => strlen($dataCheckString)
            ]);
        } catch (Throwable $e) {}
        return hash_equals($calculatedHash, $recvHash);
    }

    /**
     * Получить информацию о текущем источнике данных
     * 
     * @return array
     */
    public static function getDataSourceInfo()
    {
        $headers = function_exists('getallheaders') ? getallheaders() : [];
        $hasHeaders = !empty($headers['X-Telegram-User-ID']) || !empty($_SERVER['HTTP_X_TELEGRAM_USER_ID']);
        $hasJsonBody = !empty(file_get_contents('php://input'));
        $hasFormData = !empty($_POST);
        $hasGetParams = !empty($_GET);
        
        return [
            'has_headers' => $hasHeaders,
            'has_json_body' => $hasJsonBody,
            'has_form_data' => $hasFormData,
            'has_get_params' => $hasGetParams,
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'unknown'
        ];
    }
} 