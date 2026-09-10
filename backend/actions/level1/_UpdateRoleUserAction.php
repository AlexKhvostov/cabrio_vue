<?php
/**
 * _UpdateRoleUserAction — базовый L1 Action для обновления роли пользователя.
 * 
 * Назначение: Обновляет роль пользователя в базе данных.
 * Уровень: L1 (базовая операция)
 * Префикс: _ (одно подчёркивание)
 * 
 * Входные данные:
 *   - user_id (int) — ID пользователя
 *   - role_id (int) — ID новой роли
 * 
 * Выходные данные:
 *   - success (boolean) — результат операции
 *   - data (array) — обновленные данные пользователя
 *   - error (array, опционально) — информация об ошибке
 */
require_once __DIR__ . '/../../utils/Database.php';
require_once __DIR__ . '/../../utils/ValidationHelper.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/Role.php';

class _UpdateRoleUserAction {
    
    public static function handle($data) {
        try {
            // Валидация обязательных полей
            ValidationHelper::requireFields($data, ['user_id', 'role_id']);
            
            // Валидация user_id и role_id
            ValidationHelper::validateInt($data['user_id'], 'user_id');
            ValidationHelper::validateInt($data['role_id'], 'role_id');
            
            // Проверяем существование пользователя
            $user = User::findById($data['user_id']);
            if (!$user) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'USER_NOT_FOUND',
                        'message' => 'Пользователь не найден'
                    ]
                ];
            }
            
            // Проверяем существование роли
            $role = Role::findById($data['role_id']);
            if (!$role) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'ROLE_NOT_FOUND',
                        'message' => 'Роль не найдена'
                    ]
                ];
            }
            
            // Обновляем роль пользователя
            $result = User::updateRole($data['user_id'], $data['role_id']);
            
            if (!$result) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'UPDATE_FAILED',
                        'message' => 'Ошибка обновления роли пользователя'
                    ]
                ];
            }
            
            // Получаем обновленного пользователя
            $updatedUser = User::findById($data['user_id']);
            
            Logger::info("User role updated: ID={$data['user_id']}, role_id={$data['role_id']}");
            
            return [
                'success' => true,
                'data' => $updatedUser->toArray()
            ];
            
        } catch (Exception $e) {
            Logger::error('_UpdateRoleUserAction failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Ошибка обновления роли пользователя: ' . $e->getMessage()
                ]
            ];
        }
    }
} 