<?php
/**
 * 🔧 BaseController — базовый класс для всех контроллеров CabrioRide
 * 
 * Назначение: Общие методы для всех контроллеров
 * Использование: Наследуется всеми контроллерами
 * 
 * Основные возможности:
 * - Формирование JSON ответов
 * - Работа с глобальным контекстом (AppContext)
 * - Получение текущего пользователя
 * - Проверка авторизации
 * - Логирование
 */

require_once __DIR__ . '/../utils/AppContext.php';
require_once __DIR__ . '/../utils/Logger.php';
require_once __DIR__ . '/../utils/AppAudit.php';
require_once __DIR__ . '/../../config/sectionGroups.php';

class BaseController
{
    /**
     * Быстро вернуть JSON-ответ с нужным статусом
     * 
     * @param array $data Данные для ответа
     * @param int $status HTTP статус код
     * @return void
     */
    protected function json($data, $status = 200)
    {
        // Логируем отправляемый ответ
        Logger::info('BaseController: Sending JSON response', [
            'http_code' => $status,
            'response' => $data,
            'endpoint' => $_SERVER['REQUEST_URI'] ?? 'unknown'
        ]);
        
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

        /**
     * Получить текущего пользователя из глобального контекста
     *
     * @return array|null Данные пользователя или null
     */
    public function getCurrentUser()
    {
        return AppContext::getCurrentUser();
    }

        /**
     * Получить текущего пользователя с проверкой существования
     *
     * @return array Данные пользователя
     * @throws Exception Если пользователь не найден
     */
    public function requireUser()
    {
        $user = AppContext::getCurrentUser();
        
        if (!$user) {
            Logger::warning('BaseController: User not found in context');
            throw new Exception('Пользователь не найден в контексте');
        }
        
        return $user;
    }

        /**
     * Проверить, авторизован ли пользователь
     *
     * @return bool
     */
    public function isAuthenticated()
    {
        return AppContext::hasCurrentUser();
    }

        /**
     * Получить ID текущего пользователя
     *
     * @return int|null ID пользователя или null
     */
    public function getCurrentUserId()
    {
        $user = AppContext::getCurrentUser();
        return $user ? $user['id'] : null;
    }

        /**
     * Получить роль текущего пользователя
     *
     * @return string|null Роль пользователя или null
     */
    public function getCurrentUserRole()
    {
        $user = AppContext::getCurrentUser();
        if (!$user) {
            return null;
        }
        
        Logger::info('BaseController: getCurrentUserRole debugging', [
            'user' => $user,
            'has_role' => isset($user['role']),
            'has_role_id' => isset($user['role_id']),
            'role_type' => isset($user['role']) ? gettype($user['role']) : 'not_set',
            'role_id_type' => isset($user['role_id']) ? gettype($user['role_id']) : 'not_set'
        ]);
        
        // Если роль развернута (объект), берем код роли
        if (isset($user['role']) && is_array($user['role']) && isset($user['role']['code'])) {
            $roleCode = $user['role']['code'];
            Logger::info('BaseController: getCurrentUserRole - using expanded role', [
                'role_code' => $roleCode
            ]);
            return $roleCode;
        }
        
        // Если роль не развернута (число), возвращаем как есть
        if (isset($user['role_id'])) {
            $roleId = $user['role_id'];
            Logger::info('BaseController: getCurrentUserRole - using role_id', [
                'role_id' => $roleId
            ]);
            return $roleId;
        }
        
        Logger::warning('BaseController: getCurrentUserRole - no role found');
        return null;
    }

        /**
     * Проверить, имеет ли пользователь указанную роль
     *
     * @param string $role Роль для проверки
     * @return bool
     */
    public function hasRole($role)
    {
        $userRole = $this->getCurrentUserRole();
        return $userRole === $role;
    }

            /**
     * Проверить, является ли пользователь администратором
     *
     * @return bool
     */
    public function isAdmin()
    {
        $userRole = $this->getCurrentUserRole();
        // Поддержка как числовых ID, так и строковых кодов роли
        if (is_int($userRole) || ctype_digit((string)$userRole)) {
            return (int)$userRole === Roles::getRoleId(Roles::ADMIN);
        }
        if (is_string($userRole)) {
            return $userRole === Roles::ADMIN;
        }
        return false;
    }

    /**
     * Проверить, является ли пользователь модератором
     *
     * @return bool
     */
    public function isModerator()
    {
        $userRole = $this->getCurrentUserRole();
        // Числовые ID: moderator (5) и admin (6)
        if (is_int($userRole) || ctype_digit((string)$userRole)) {
            $roleId = (int)$userRole;
            return $roleId >= Roles::getRoleId(Roles::MODERATOR);
        }
        // Строковые коды: 'moderator' и 'admin'
        if (is_string($userRole)) {
            return $userRole === Roles::MODERATOR || $userRole === Roles::ADMIN;
        }
        return false;
    }

    /**
     * Проверить, является ли пользователь участником или выше
     *
     * @return bool
     */
    public function isMember()
    {
        $userRole = $this->getCurrentUserRole();
        // Числовые ID: member (4) и выше
        if (is_int($userRole) || ctype_digit((string)$userRole)) {
            $roleId = (int)$userRole;
            return $roleId >= Roles::getRoleId(Roles::MEMBER);
        }
        // Строковые коды: 'member', 'moderator', 'admin'
        if (is_string($userRole)) {
            return in_array($userRole, [Roles::MEMBER, Roles::MODERATOR, Roles::ADMIN], true);
        }
        return false;
    }

        /**
     * Получить информацию о запросе из контекста
     *
     * @return array Информация о запросе
     */
    public function getRequestInfo()
    {
        return [
            'request_id' => AppContext::getRequestId(),
            'session_id' => AppContext::getSessionId(),
            'execution_time' => AppContext::getExecutionTime(),
            'user_id' => $this->getCurrentUserId()
        ];
    }

    /**
     * Логировать действие пользователя
     * 
     * @param string $action Действие
     * @param array $data Дополнительные данные
     * @return void
     */
    protected function logUserAction($action, $data = [])
    {
        $user = $this->getCurrentUser();
        $userId = $user ? $user['id'] : 'unknown';
        
        Logger::info("User action: {$action}", array_merge([
            'user_id' => $userId,
            'request_id' => AppContext::getRequestId()
        ], $data));
    }

    /** Журнал для админки: кто, когда, что. Не ломает запрос при сбое. */
    protected function audit(string $action, string $entityType, $entityId, string $summary, string $section = ''): void
    {
        AppAudit::write([
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'section' => $section,
            'summary' => $summary,
        ]);
    }
    
    /**
     * Проверить доступ к функции через централизованную конфигурацию
     * 
     * @param string $function Имя функции (например, 'api.users.getList')
     * @return bool
     */
    protected function checkAccess($function)
    {
        return AccessUtils::checkApiAccess($function);
    }
    
    /**
     * Проверить доступ и вернуть ошибку если доступ запрещен
     * 
     * @param string $function Имя функции
     * @return bool true если доступ разрешен, false если запрещен (ответ уже отправлен)
     */
    protected function requireAccess($function)
    {
        Logger::info('BaseController: requireAccess called', [
            'function' => $function,
            'user_id' => $this->getCurrentUserId(),
            'user_role' => $this->getCurrentUserRole(),
            'is_authenticated' => $this->isAuthenticated()
        ]);
        
        if (!$this->checkAccess($function)) {
            $requiredRole = AccessUtils::getRequiredRoleForApi($function);
            $userRole = $this->getCurrentUserRole();
            
            Logger::warning("Access denied", [
                'function' => $function,
                'user_role' => $userRole,
                'required_role' => $requiredRole,
                'user_id' => $this->getCurrentUserId()
            ]);
            
            $this->json([
                'success' => false,
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => "Недостаточно прав для выполнения действия. Требуется роль: {$requiredRole}"
                ]
            ], 403);
            
            return false;
        }
        
        Logger::info('BaseController: Access granted', [
            'function' => $function,
            'user_id' => $this->getCurrentUserId()
        ]);
        
        return true;
    }
    
    /**
     * Проверить доступ по числовому ID роли (для работы с БД)
     * 
     * @param int $userRoleId ID роли пользователя
     * @param string $function Имя функции
     * @return bool
     */
    protected function checkAccessById($userRoleId, $function)
    {
        return AccessUtils::checkAccessById($userRoleId, $function);
    }
    
    /**
     * Получить строковый код роли по числовому ID
     * 
     * @param int $roleId ID роли
     * @return string Код роли
     */
    protected function getRoleCode($roleId)
    {
        return Roles::getRoleById($roleId);
    }
    
    /**
     * Получить числовой ID роли по строковому коду
     * 
     * @param string $roleCode Код роли
     * @return int ID роли
     */
    protected function getRoleId($roleCode)
    {
        return Roles::getRoleId($roleCode);
    }
} 