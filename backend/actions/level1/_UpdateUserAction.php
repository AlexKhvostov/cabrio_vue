<?php
/**
 * _UpdateUserAction — базовый L1 Action для обновления пользователя.
 * 
 * Назначение: Обновляет данные пользователя в базе данных.
 * Уровень: L1 (базовая операция)
 * Префикс: _ (одно подчёркивание)
 * 
 * Входные данные:
 *   - user_id (int) — ID пользователя
 *   - first_name (string, опционально) — имя пользователя
 *   - last_name (string, опционально) — фамилия пользователя
 *   - username (string, опционально) — username в Telegram
 *   - city (string, опционально) — город
 *   - email (string, опционально) — email
 * 
 * Выходные данные:
 *   - success (boolean) — результат операции
 *   - data (array) — развернутые данные обновленного пользователя
 *   - error (array, опционально) — информация об ошибке
 */
require_once __DIR__ . '/../../utils/Database.php';
require_once __DIR__ . '/../../utils/ValidationHelper.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../models/User.php';

class _UpdateUserAction {
    
    public static function handle($data) {
        try {
            // Валидация обязательных полей
            ValidationHelper::requireFields($data, ['user_id']);
            
            // Валидация user_id
            ValidationHelper::validateInt($data['user_id'], 'user_id');
            
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
            
            // Подготавливаем данные для обновления
            $updateData = [];
            $allowedFields = ['username', 'city', 'email'];
            
            // Маппинг полей из входных данных в поля БД
            $fieldMapping = [
                'first_name' => 'first_name_tg',
                'last_name' => 'last_name_tg',
                'username' => 'username',
                'city' => 'city',
                'email' => 'email',
                'join_date' => 'join_date',
                'left_date' => 'left_date',
                'telegram_photo_id' => 'telegram_photo_id'
            ];
            
            foreach ($fieldMapping as $inputField => $dbField) {
                if (isset($data[$inputField])) {
                    $updateData[$dbField] = $data[$inputField];
                }
            }
            
            // Если нет данных для обновления
            if (empty($updateData)) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'NO_DATA_TO_UPDATE',
                        'message' => 'Нет данных для обновления'
                    ]
                ];
            }
            
            // Валидация email если передан
            if (isset($updateData['email'])) {
                ValidationHelper::validateEmail($updateData['email']);
            }
            
            // Обновляем пользователя с развернутыми данными
            $updatedUserData = User::updateWithDetails($data['user_id'], $updateData);
            
            if (!$updatedUserData) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'UPDATE_FAILED',
                        'message' => 'Ошибка обновления пользователя'
                    ]
                ];
            }
            
            Logger::info("User updated: ID={$data['user_id']}");
            
            return [
                'success' => true,
                'data' => $updatedUserData // Возвращаем развернутые данные
            ];
            
        } catch (Exception $e) {
            Logger::error('_UpdateUserAction failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Ошибка обновления пользователя: ' . $e->getMessage()
                ]
            ];
        }
    }
} 