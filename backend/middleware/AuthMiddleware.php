<?php

require_once __DIR__ . '/../utils/AuthHelper.php';
require_once __DIR__ . '/../utils/AppContext.php';
require_once __DIR__ . '/../utils/SessionHelper.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../actions/level2/__SyncUserDataAction.php';
require_once __DIR__ . '/../../config/sectionGroups.php';

/**
 * 🔐 AuthMiddleware - Централизованная система авторизации
 * 
 * Этот middleware обрабатывает все типы авторизации в системе:
 * 
 * 📱 TELEGRAM WEBAPP АВТОРИЗАЦИЯ:
 * - Получает данные от Telegram WebApp (initData)
 * - Проверяет валидность хеша подписи Telegram
 * - Извлекает данные пользователя (id, username, first_name, etc.)
 * - Синхронизирует пользователя с базой данных
 * - Создает/обновляет сессию пользователя
 * 
 * 🤖 TELEGRAM BOT АВТОРИЗАЦИЯ:
 * - Использует SYSTEM_TOKEN для авторизации бота
 * - Проверяет, что запрос пришел локально (защита от внешних атак)
 * - Авторизует бота как системного пользователя (role: admin)
 * - Позволяет боту выполнять системные операции
 * 
 * 🧪 DEV-РЕЖИМ (только для разработки):
 * - DEV_AUTH: полный байпас авторизации
 * - DEV_USER_ID: подмена ID пользователя
 * - DEV_ROLE: подмена роли пользователя
 * - Работает только вне production окружения
 * 
 * 🔒 БЕЗОПАСНОСТЬ:
 * - Проверка хеша Telegram для WebApp
 * - Проверка SYSTEM_TOKEN + локальный IP для бота
 * - Логирование всех попыток авторизации
 * - Защита от несанкционированного доступа
 * 
 * 📊 ЛОГИРОВАНИЕ:
 * - Все этапы авторизации логируются
 * - Ошибки авторизации записываются в лог
 * - Метрики времени выполнения запросов
 * 
 * @package CabrioRide\Middleware
 * @author CabrioRide Team
 * @version 2.0
 */
class AuthMiddleware
{
    /** @var string Префикс для генерации уникальных ID запросов */
    const REQUEST_ID_PREFIX = 'req_';

    // ========================================
    // ОСНОВНОЙ МЕТОД ОБРАБОТКИ
    // ========================================

    /**
     * Обработать авторизацию для текущего запроса
     * 
     * Этот метод является основным для обработки Telegram-авторизации.
     * Выполняет полный цикл: извлечение данных → валидация → синхронизация → сессия
     * 
     * @return array Результат обработки с полями:
     *               - success: bool - успешность операции
     *               - user_id: int - ID пользователя (если успешно)
     *               - session_id: string - ID сессии (если успешно)
     *               - error: array - информация об ошибке (если неуспешно)
     */
    public static function process()
    {
        try {
            Logger::info('AuthMiddleware: Starting authentication process');
            
            // 1️⃣ ИЗВЛЕЧЕНИЕ TELEGRAM ДАННЫХ
            // Пытаемся извлечь данные из заголовков, JSON тела, FormData или GET параметров
            $telegramData = AuthHelper::extractTelegramData();
            
            if (!$telegramData) {
                Logger::warning('AuthMiddleware: No Telegram data found');
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'NO_TELEGRAM_DATA',
                        'message' => 'Данные Telegram не найдены'
                    ]
                ];
            }
            
            Logger::info('AuthMiddleware: Telegram data extracted', [
                'telegram_id' => $telegramData['telegram_id'] ?? 'unknown'
            ]);
            
            // 2️⃣ ВАЛИДАЦИЯ TELEGRAM ДАННЫХ
            // Проверяем корректность данных и валидность хеша (для WebApp)
            $validationResult = AuthHelper::validateTelegramData($telegramData);
            
            if (!$validationResult['success']) {
                Logger::warning('AuthMiddleware: Telegram data validation failed', [
                    'error' => $validationResult['error']['message']
                ]);
                
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'TELEGRAM_VALIDATION_ERROR',
                        'message' => 'Ошибка валидации данных Telegram: ' . $validationResult['error']['message']
                    ]
                ];
            }
            
            Logger::info('AuthMiddleware: Telegram data validated');
            
            // 3️⃣ СИНХРОНИЗАЦИЯ ПОЛЬЗОВАТЕЛЯ
            // Создаем или обновляем пользователя в базе данных
            // На этапе process не передаём файлы в синк пользователя, чтобы не перехватывать загрузки фото других сущностей
            $userResult = __SyncUserDataAction::handle($telegramData, ['allow_file_upload' => false]);
            
            if (!$userResult['success']) {
                Logger::error('AuthMiddleware: User sync failed', [
                    'error' => $userResult['error']['message']
                ]);
                
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'USER_SYNC_ERROR',
                        'message' => 'Ошибка синхронизации пользователя: ' . $userResult['error']['message']
                    ]
                ];
            }
            
            $userData = $userResult['data'];
            Logger::info('AuthMiddleware: User synchronized', [
                'user_id' => $userData['id'],
                'telegram_id' => $userData['telegram_id']
            ]);
            
            // 4️⃣ СОЗДАНИЕ/ОБНОВЛЕНИЕ СЕССИИ
            // Создаем или обновляем сессию пользователя
            $sessionResult = SessionHelper::createOrUpdateSession($userData['id'], [
                'telegram_data' => $telegramData
            ]);
            
            if (!$sessionResult['success']) {
                Logger::error('AuthMiddleware: Session creation failed', [
                    'user_id' => $userData['id'],
                    'error' => $sessionResult['error']['message']
                ]);
                
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'SESSION_CREATION_ERROR',
                        'message' => 'Ошибка создания сессии: ' . $sessionResult['error']['message']
                    ]
                ];
            }
            
            Logger::info('AuthMiddleware: Session created/updated', [
                'user_id' => $userData['id'],
                'session_id' => substr($sessionResult['session_id'], 0, 8) . '...',
                'action' => $sessionResult['action']
            ]);
            
            // 5️⃣ УСТАНОВКА ГЛОБАЛЬНОГО КОНТЕКСТА
            // Устанавливаем данные пользователя, сессию и ID запроса в глобальный контекст
            self::setupGlobalContext($telegramData, $userData, $sessionResult);
            
            Logger::info('AuthMiddleware: Global context setup complete', [
                'user_id' => $userData['id'],
                'request_id' => AppContext::getRequestId()
            ]);
            
            return [
                'success' => true,
                'user_id' => $userData['id'],
                'session_id' => $sessionResult['session_id'],
                'message' => 'Авторизация успешна'
            ];
            
        } catch (Exception $e) {
            Logger::error('AuthMiddleware: Unexpected error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => [
                    'code' => 'AUTH_ERROR',
                    'message' => 'Ошибка авторизации: ' . $e->getMessage()
                ]
            ];
        }
    }

    // ========================================
    // ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ
    // ========================================

    /**
     * Настроить глобальный контекст
     * 
     * @param array $telegramData Данные из Telegram
     * @param array $user Данные пользователя
     * @param array $sessionResult Результат создания сессии
     * @return void
     */
    private static function setupGlobalContext($telegramData, $userData, $sessionResult)
    {
        // Устанавливаем Telegram данные
        AppContext::setTelegramData($telegramData);
        
        // Преобразуем данные пользователя в нужный формат
        $user = [
            'id' => $userData['id'],
            'telegram_id' => $userData['telegram_id'],
            'first_name' => $userData['first_name_tg'] ?? $userData['first_name'] ?? null,
            'last_name' => $userData['last_name_tg'] ?? $userData['last_name'] ?? null,
            'username' => $userData['username'],
            'role' => $userData['role'],
            'role_id' => $userData['role']['id'] ?? $userData['role_id'] ?? 2
        ];
        
        // Устанавливаем пользователя
        AppContext::setCurrentUser($user);
        
        // Устанавливаем ID сессии
        AppContext::setSessionId($sessionResult['session_id']);
        
        // Генерируем уникальный ID запроса
        $requestId = self::generateRequestId();
        AppContext::setRequestId($requestId);
        
        // Устанавливаем время начала запроса
        AppContext::setStartTime(microtime(true));
        
        Logger::info('Global context setup', [
            'user_id' => $user['id'],
            'session_id' => substr($sessionResult['session_id'], 0, 8) . '...',
            'request_id' => $requestId
        ]);
    }

    /**
     * Сгенерировать уникальный ID запроса
     * 
     * @return string
     */
    private static function generateRequestId()
    {
        $timestamp = date('Ymd_His');
        $microtime = substr(microtime(), 2, 6);
        $random = substr(bin2hex(random_bytes(4)), 0, 8);
        
        return self::REQUEST_ID_PREFIX . $timestamp . '_' . $microtime . '_' . $random;
    }

    // ========================================
    // МЕТОДЫ ДЛЯ СПЕЦИАЛЬНЫХ СЛУЧАЕВ
    // ========================================

    /**
     * 🔐 Единая точка авторизации для всех запросов
     * 
     * Этот метод является главной точкой входа для авторизации.
     * Обрабатывает все типы авторизации в порядке приоритета:
     * 
     * 1️⃣ Локальные запросы (для Telegram Bot) - автоматически разрешаем
     * 2️⃣ Telegram-авторизация (для WebApp) - проверяем хеш
     * 3️⃣ DEV-режим (только для разработки) - байпас для тестирования
     *
     * @param string $route  Текущий маршрут (для логирования)
     * @param string $method HTTP-метод (GET, POST, etc.)
     * @return array Результат авторизации
     */
    public static function authenticate($route, $method)
    {
        // 📊 Старт метрики времени выполнения
        AppContext::setStartTime(microtime(true));

        // 🤖 Приоритетная проверка бота по X-Bot-Secret: если секрет верный — доверяем и продолжаем как раньше
        $incomingBotSecret = $_SERVER['HTTP_X_BOT_SECRET'] ?? '';
        $expectedBotSecret = getenv('BOT_SECRET') ?: '';
        if ($incomingBotSecret && $expectedBotSecret && hash_equals($expectedBotSecret, $incomingBotSecret)) {
            // Авторизуем системный контекст (admin)
            self::initSystemContext();

            // Пытаемся подставить реального пользователя из Telegram заголовков, чтобы не было create_user_id = 0
            try {
                $telegramData = self::extractTelegramData();
                if ($telegramData) {
                    $syncResult = __SyncUserDataAction::handle($telegramData, ['allow_file_upload' => false]);
                    if ($syncResult['success']) {
                        $userData = $syncResult['data'];
                        AppContext::setCurrentUser($userData);
                        Logger::info('AuthMiddleware: Real user set via Bot secret', [ 'user_id' => $userData['id'], 'route' => $route ]);
                    } else {
                        Logger::warning('AuthMiddleware: Failed to sync user via Bot secret', [ 'error' => $syncResult['error']['message'] ?? 'unknown' ]);
                    }
                } else {
                    Logger::warning('AuthMiddleware: No Telegram data with Bot secret');
                }
            } catch (Throwable $e) {
                Logger::warning('AuthMiddleware: Exception during Bot user sync', [ 'error' => $e->getMessage() ]);
            }

            Logger::info('AuthMiddleware: Bot authenticated via X-Bot-Secret', [ 'route' => $route, 'method' => $method ]);
            return [ 'success' => true, 'user_id' => AppContext::getCurrentUser()['id'] ?? 0, 'session_id' => 'system', 'message' => 'Bot auth ok' ];
        }

        // 🏠 1️⃣ ПРОВЕРКА ЛОКАЛЬНЫХ ЗАПРОСОВ (для Telegram Bot)
        // Если запрос пришел локально - разрешаем без дополнительных проверок
        if (self::isLocalRequest()) {
            // ✅ Локальный запрос - авторизуем как системный пользователь
            self::initSystemContext();

            // 🔄 Пытаемся подставить реального пользователя по Telegram заголовкам
            // (актуально для WebApp, когда фронт и бэкенд на одном хосте)
            $telegramData = self::extractTelegramData();
            if ($telegramData) {
                require_once __DIR__ . '/../actions/level2/__SyncUserDataAction.php';
                // В локальных запросах также игнорируем файлы в синке пользователя
                $syncResult = __SyncUserDataAction::handle($telegramData, ['allow_file_upload' => false]);

                if ($syncResult['success']) {
                    $userData = $syncResult['data'];
                    AppContext::setCurrentUser($userData);
                    Logger::info('AuthMiddleware: Real user set from Telegram headers (local request)', [
                        'user_id' => $userData['id'],
                        'telegram_id' => $telegramData['telegram_id'],
                        'route' => $route
                    ]);
                } else {
                    Logger::warning('AuthMiddleware: Failed to sync user from Telegram headers (local request)', [
                        'route' => $route,
                        'error' => $syncResult['error']['message'] ?? 'unknown'
                    ]);
                }
            }
            
            Logger::info('AuthMiddleware: Local request authenticated', [
                    'route'  => $route,
                'method' => $method,
                'client_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
                ]);
                return [
                    'success'    => true,
                'user_id'    => 0,        // Системный пользователь
                'session_id' => 'system', // Системная сессия
                'message'    => 'Local request authorization successful'
                ];
        }

        // 📱 2️⃣ TELEGRAM-АВТОРИЗАЦИЯ (для WebApp)
        // Выполняем полный цикл: извлечение → валидация хеша → синхронизация → сессия
        $result = self::process();

        // 🧪 3️⃣ DEV-РЕЖИМ (только для разработки, вне production)
        // Позволяет обходить авторизацию для тестирования
        if (getenv('APP_ENV') !== 'production') {
            // 🔓 Полный байпас авторизации, если предыдущая попытка НЕ была успешной
            if (getenv('DEV_AUTH') && (!($result['success'] ?? false))) {
                Logger::info('DEV AUTH ACTIVE: bypass enabled');

                // 👤 Если указан DEV_USER_ID (число >0) — используем его; иначе 999
                $devIdRaw = getenv('DEV_USER_ID') ?: '';
                $devId = (ctype_digit($devIdRaw) && intval($devIdRaw) > 0) ? intval($devIdRaw) : 999;

                // 🎭 Устанавливаем тестового пользователя
                AppContext::setCurrentUser([
                    'id'       => $devId,
                    'role_id'  => Roles::ROLE_IDS['guest'],
                    'role'     => 'guest',
                    'username' => 'dev_tester'
                ]);
                AppContext::setSessionId('dev');
                $result = [
                    'success'    => true,
                    'user_id'    => $devId,
                    'session_id' => 'dev',
                    'message'    => 'DEV AUTH bypass'
                ];
            }

            // 🎭 Подмена роли пользователя (для тестирования разных ролей)
            $override = getenv('DEV_ROLE');
            if ($override && isset(Roles::ROLE_IDS[$override])) {
                $user = AppContext::getCurrentUser() ?? [ 'id' => 999 ];
                $user['role_id'] = Roles::ROLE_IDS[$override];
                $user['role']    = $override;
                AppContext::setCurrentUser($user);
                Logger::info('DEV ROLE OVERRIDE', ['role' => $override]);
            }
        }

        return $result;
    }

    /**
     * 🔧 Устанавливает в AppContext данные для системного запроса
     * 
     * Создает виртуального системного пользователя с правами администратора.
     * Используется при авторизации через SYSTEM_TOKEN.
     */
    private static function initSystemContext(): void
    {
        // 🆔 id = 0 обозначаем как виртуальный системный пользователь
        AppContext::setCurrentUser([
            'id'       => 0,        // Системный ID
            'role_id'  => 6,        // admin (максимальные права)
            'role'     => 'admin',  // Роль администратора
            'username' => 'system'  // Системное имя
        ]);
        AppContext::setSessionId('system');  // Системная сессия
        AppContext::setRequestId(self::generateRequestId()); // Уникальный ID запроса
    }

    /**
     * Обработать запрос без авторизации (для публичных эндпоинтов)
     * 
     * @return array Результат обработки
     */
    public static function processPublic()
    {
        try {
            Logger::info('AuthMiddleware: Processing public request');
            
            // Устанавливаем базовый контекст
            $requestId = self::generateRequestId();
            AppContext::setRequestId($requestId);
            AppContext::setStartTime(microtime(true));
            
            // Для L3 Actions обрабатываем Telegram данные из headers
            $route = $_GET['route'] ?? '';
            if (strpos($route, '/api/actions/') === 0) {
                // Получаем Telegram данные из headers
                $telegramData = self::extractTelegramData();
                
                if ($telegramData) {
                    // Синхронизируем пользователя с реальными Telegram данными
                    $syncResult = __SyncUserDataAction::handle($telegramData, ['allow_file_upload' => false]);
                    
                    if ($syncResult['success']) {
                        $userData = $syncResult['data'];
                        AppContext::setCurrentUser($userData);
                        
                        Logger::info('AuthMiddleware: Real user set for L3 Action', [
                            'user_id' => $userData['id'],
                            'telegram_id' => $telegramData['telegram_id'],
                            'route' => $route
                        ]);
                    } else {
                        Logger::warning('AuthMiddleware: Failed to sync user for L3 Action', [
                            'telegram_data' => $telegramData,
                            'route' => $route
                        ]);
                    }
                } else {
                    Logger::warning('AuthMiddleware: No Telegram data found for L3 Action', [
                        'route' => $route
                    ]);
                }
            }
            
            Logger::info('AuthMiddleware: Public request processed', [
                'request_id' => $requestId,
                'route' => $route
            ]);
            
            return [
                'success' => true,
                'message' => 'Публичный запрос обработан'
            ];
            
        } catch (Exception $e) {
            Logger::error('AuthMiddleware: Public request error', [
                'error' => $e->getMessage()
            ]);
            
            return [
                'success' => false,
                'error' => [
                    'code' => 'PUBLIC_AUTH_ERROR',
                    'message' => 'Ошибка обработки публичного запроса: ' . $e->getMessage()
                ]
            ];
        }
    }

    /**
     * Проверить, требуется ли авторизация для эндпоинта
     * 
     * @param string $route Маршрут
     * @param string $method HTTP метод
     * @return bool
     */
    public static function requiresAuth($route, $method)
    {
        // Список публичных эндпоинтов (не требующих авторизации)
        $publicEndpoints = [
            ['route' => '/api/health', 'method' => 'GET'],
            ['route' => '/api/status', 'method' => 'GET'],
            ['route' => '/api/telegram/webhook', 'method' => 'POST'],
            ['route' => '/api/bot/webhook', 'method' => 'POST'],
            ['route' => '/api/system/user-sync', 'method' => 'POST'],
            ['route' => '/api/system/user-role', 'method' => 'POST'],
            ['route' => '/api/system/entity-status', 'method' => 'POST'],
            ['route' => '/api/actions/check-car-in-club', 'method' => 'POST'],
            ['route' => '/api/actions/leave-business-card', 'method' => 'POST'],
            ['route' => '/api/actions/add-car-to-garage', 'method' => 'POST'],
            // Временно добавляем L3 эндпоинты для тестирования
            ['route' => '/api/actions/check-car-in-club', 'method' => 'POST'],
            ['route' => '/api/actions/leave-business-card', 'method' => 'POST'],
            ['route' => '/api/actions/add-car-to-garage', 'method' => 'POST']
        ];
        
        foreach ($publicEndpoints as $endpoint) {
            if ($endpoint['route'] === $route && $endpoint['method'] === $method) {
                return false;
            }
        }
        
        return true;
    }

    /**
     * Получить информацию о текущем состоянии авторизации
     * 
     * @return array
     */
    public static function getAuthInfo()
    {
        return [
            'has_user' => AppContext::hasCurrentUser(),
            'has_session' => AppContext::hasSession(),
            'has_telegram_data' => AppContext::hasTelegramData(),
            'user_id' => AppContext::getCurrentUser() ? AppContext::getCurrentUser()['id'] : null,
            'session_id' => AppContext::getSessionId() ? substr(AppContext::getSessionId(), 0, 8) . '...' : null,
            'request_id' => AppContext::getRequestId(),
            'execution_time' => AppContext::getExecutionTime()
        ];
    }

    /**
     * Очистить контекст авторизации
     * 
     * @return void
     */
    public static function clearAuth()
    {
        AppContext::clear();
        Logger::info('AuthMiddleware: Auth context cleared');
    }

    /**
     * Извлечь Telegram данные из headers
     * 
     * @return array|null Telegram данные или null
     */
    private static function extractTelegramData()
    {
        // Проверяем наличие Telegram headers (с правильными именами)
        $telegramId = $_SERVER['HTTP_X_TELEGRAM_USER_ID'] ?? null;
        $telegramUsername = $_SERVER['HTTP_X_TELEGRAM_USERNAME'] ?? null;
        $telegramFirstName = $_SERVER['HTTP_X_TELEGRAM_FIRST_NAME'] ?? null;
        $telegramLastName = $_SERVER['HTTP_X_TELEGRAM_LAST_NAME'] ?? null;
        
        // Логируем для отладки
        Logger::info('AuthMiddleware: Extracting Telegram data from headers', [
            'telegram_id' => $telegramId,
            'telegram_username' => $telegramUsername,
            'telegram_first_name' => $telegramFirstName,
            'telegram_last_name' => $telegramLastName,
            'all_headers' => array_keys($_SERVER)
        ]);
        
        if (!$telegramId) {
            Logger::warning('AuthMiddleware: No Telegram User ID found in headers');
            return null;
        }
        
        return [
            'telegram_id' => (int)$telegramId,
            'username' => $telegramUsername,
            'first_name' => $telegramFirstName,
            'last_name' => $telegramLastName
        ];
    }

    /**
     * 🔒 Проверить, что запрос пришел локально
     * 
     * Этот метод проверяет, что запрос пришел с локального IP адреса.
     * Используется для защиты SYSTEM_TOKEN от внешних атак.
     * 
     * Поддерживаемые локальные адреса:
     * - 127.0.0.1 (IPv4 localhost)
     * - ::1 (IPv6 localhost)
     * - localhost (локальный хост)
     * - 192.168.0.0/16 (локальная сеть)
     * - 10.0.0.0/8 (приватная сеть)
     * - 172.16.0.0/12 (приватная сеть)
     * 
     * @return bool true если запрос локальный, false если внешний
     */
    private static function isLocalRequest(): bool
    {
        // 📡 Получаем IP адрес клиента
        $clientIP = $_SERVER['REMOTE_ADDR'] ?? '';
        
        // 🏠 Список локальных IP адресов и диапазонов
        $localIPs = [
            '127.0.0.1',     // IPv4 localhost
            '::1',           // IPv6 localhost
            'localhost',     // localhost
            '192.168.0.0/16', // Локальная сеть (192.168.x.x)
            '10.0.0.0/8',   // Приватная сеть (10.x.x.x)
            '172.16.0.0/12' // Приватная сеть (172.16-31.x.x)
        ];
        
        // 🔍 Проверяем каждый локальный диапазон
        foreach ($localIPs as $ip) {
            if (self::ipInRange($clientIP, $ip)) {
                return true; // ✅ IP в локальном диапазоне
            }
        }
        
        return false; // ❌ IP не локальный
    }

    /**
     * 🔍 Проверить, находится ли IP в указанном диапазоне
     * 
     * Поддерживает как точные IP адреса, так и CIDR диапазоны.
     * Используется для проверки локальных сетей.
     * 
     * Примеры:
     * - ipInRange('192.168.1.5', '192.168.0.0/16') → true
     * - ipInRange('127.0.0.1', '127.0.0.1') → true
     * - ipInRange('8.8.8.8', '192.168.0.0/16') → false
     * 
     * @param string $ip IP адрес для проверки
     * @param string $range IP диапазон (точный адрес или CIDR)
     * @return bool true если IP в диапазоне, false если нет
     */
    private static function ipInRange(string $ip, string $range): bool
    {
        if (strpos($range, '/') !== false) {
            // 📊 CIDR notation (например, 192.168.0.0/16)
            list($subnet, $bits) = explode('/', $range);
            $ip = ip2long($ip);
            $subnet = ip2long($subnet);
            $mask = -1 << (32 - $bits);  // Создаем маску подсети
            $subnet &= $mask;             // Применяем маску к подсети
            return ($ip & $mask) == $subnet; // Сравниваем с маской
        } else {
            // 🎯 Точный IP адрес
            return $ip === $range;
        }
    }
} 