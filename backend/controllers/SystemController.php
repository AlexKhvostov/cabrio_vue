<?php
/**
 * SystemController — контроллер для системных операций
 *
 * Назначение:
 *   Обрабатывает системные запросы от бота и других внутренних сервисов
 *   Использует SYSTEM_TOKEN для авторизации
 *
 * Основные методы:
 *   - userSync() — синхронизация пользователя при входе в чат
 *   - userRole() — обновление роли пользователя при выходе из чата
 */

require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../actions/level2/__HandleUserJoinedAction.php';
require_once __DIR__ . '/../actions/level2/__HandleUserLeftAction.php';
require_once __DIR__ . '/../actions/level1/_UpdateStatusAction.php';

class SystemController extends BaseController
{
    /**
     * Синхронизация пользователя при входе в чат
     * 
     * Требует авторизации: Да (SYSTEM_TOKEN)
     * Минимальная роль: system
     */
    public function userSync()
    {
        try {
            // Проверяем системную авторизацию
            if (!$this->requireSystemAccess()) {
                return; // Ответ уже отправлен в requireSystemAccess
            }

            // Получаем данные из запроса
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Логируем действие
            $this->logSystemAction('user_sync', [
                'telegram_id' => $input['telegram_id'] ?? null,
                'input_data' => $input
            ]);

            // Вызываем L2 Action для обработки входа пользователя
            $result = __HandleUserJoinedAction::handle($input);
            
            $this->json($result);
            
        } catch (Throwable $e) {
            Logger::error('SystemController: userSync error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Обновление роли пользователя при выходе из чата
     * 
     * Требует авторизации: Да (SYSTEM_TOKEN)
     * Минимальная роль: system
     */
    public function userRole()
    {
        try {
            // Проверяем системную авторизацию
            if (!$this->requireSystemAccess()) {
                return; // Ответ уже отправлен в requireSystemAccess
            }

            // Получаем данные из запроса
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Логируем действие
            $this->logSystemAction('user_role_update', [
                'telegram_id' => $input['telegram_id'] ?? null,
                'role_id' => $input['role_id'] ?? null,
                'input_data' => $input
            ]);

            // Вызываем L2 Action для обработки выхода пользователя
            $result = __HandleUserLeftAction::handle($input);
            
            $this->json($result);
            
        } catch (Throwable $e) {
            Logger::error('SystemController: userRole error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Обновление статуса сущности через системный эндпоинт
     * 
     * Требует авторизации: Да (SYSTEM_TOKEN)
     * Минимальная роль: system
     */
    public function entityStatus()
    {
        try {
            // Проверяем системную авторизацию
            if (!$this->requireSystemAccess()) {
                return; // Ответ уже отправлен в requireSystemAccess
            }

            // Получаем данные из запроса
            $input = json_decode(file_get_contents('php://input'), true);
            
            // Логируем действие
            $this->logSystemAction('entity_status_update', [
                'entity_type' => $input['entity_type'] ?? null,
                'entity_id' => $input['entity_id'] ?? null,
                'status_id' => $input['status_id'] ?? null,
                'input_data' => $input
            ]);

            // Вызываем L1 Action для обновления статуса сущности
            $result = _UpdateStatusAction::handle($input);
            
            $this->json($result);
            
        } catch (Throwable $e) {
            Logger::error('SystemController: entityStatus error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            $this->json([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Проверяет системную авторизацию через SYSTEM_TOKEN
     * 
     * @return bool true если авторизация успешна
     */
    private function requireSystemAccess()
    {
        // Пытаемся получить Authorization из разных источников (под прокси/CGI может отсутствовать в $_SERVER)
        $headers = function_exists('getallheaders') ? @getallheaders() : [];
        $authHeader = '';
        if (is_array($headers) && isset($headers['Authorization'])) {
            $authHeader = $headers['Authorization'];
        } elseif (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
        }
        
        if (empty($authHeader) || strpos($authHeader, 'Bearer ') !== 0) {
            $this->json([
                'success' => false,
                'error' => [
                    'code' => 'NO_TOKEN',
                    'message' => 'Требуется системная авторизация'
                ]
            ], 401);
            return false;
        }
        
        $token = trim(substr($authHeader, 7));
        $systemToken = $_ENV['SYSTEM_TOKEN'] ?? '';
        
        if ($token !== $systemToken) {
            $this->json([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_TOKEN',
                    'message' => 'Недействительный системный токен'
                ]
            ], 401);
            return false;
        }
        
        return true;
    }

    /**
     * Логирует системное действие
     * 
     * @param string $action Название действия
     * @param array $data Данные для логирования
     */
    private function logSystemAction($action, $data = [])
    {
        Logger::info("System action: $action", array_merge($data, [
            'controller' => 'SystemController',
            'timestamp' => date('Y-m-d H:i:s')
        ]));
    }
} 