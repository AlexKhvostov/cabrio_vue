<?php
/**
 * _CreateUserAction — базовый L1 Action для создания пользователя.
 * 
 * Назначение: Создаёт нового пользователя в базе данных.
 * Уровень: L1 (базовая операция)
 * Префикс: _ (одно подчёркивание)
 * 
 * Входные данные:
 *   - telegram_id (int) — Telegram ID (обязательное)
 *   - first_name (string, опционально) — имя пользователя
 *   - last_name (string, опционально) — фамилия пользователя  
 *   - username (string, опционально) — username в Telegram
 *   - role_id (int, опционально) — ID роли (по умолчанию 2 - guest)
 *   - city (string, опционально) — город
 *   - email (string, опционально) — email
 * 
 * Выходные данные:
 *   - success (boolean) — результат операции
 *   - data (array) — развернутые данные созданного пользователя
 *   - error (array, опционально) — информация об ошибке
 */
require_once __DIR__ . '/../../utils/Database.php';
require_once __DIR__ . '/../../utils/ValidationHelper.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/Role.php';

class _CreateUserAction {
    
    public static function handle($data) {
        try {
            // Валидация обязательных полей
            ValidationHelper::requireFields($data, ['telegram_id']);
            
            // Валидация telegram_id
            ValidationHelper::validateInt($data['telegram_id'], 'telegram_id');
            
            // Проверяем, что пользователь с таким telegram_id не существует
            $existingUser = User::findByTelegramId($data['telegram_id']);
            if ($existingUser) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'USER_EXISTS',
                        'message' => 'Пользователь с таким Telegram ID уже существует'
                    ]
                ];
            }
            
            // Подготавливаем данные для создания
            $userData = [
                'telegram_id' => $data['telegram_id'],
                'first_name' => $data['first_name'] ?? null,  // Передаем как first_name для модели
                'last_name' => $data['last_name'] ?? null,    // Передаем как last_name для модели
                'username' => $data['username'] ?? null,
                'role_id' => $data['role_id'] ?? 2, // guest по умолчанию
                'city' => $data['city'] ?? null,
                'email' => $data['email'] ?? null
            ];
            
            // Валидация email если передан
            if (isset($userData['email'])) {
                ValidationHelper::validateEmail($userData['email']);
            }
            
            // Создаём пользователя с развернутыми данными
            $userData = User::createWithDetails($userData);
            
            if (!$userData) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'USER_CREATION_FAILED',
                        'message' => 'Не удалось создать пользователя'
                    ]
                ];
            }
            
            Logger::info("User created: ID={$userData['id']}, telegram_id={$userData['telegram_id']}");
            
            return [
                'success' => true,
                'data' => $userData // Возвращаем развернутые данные
            ];
            
        } catch (Exception $e) {
            Logger::error('_CreateUserAction failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Ошибка создания пользователя: ' . $e->getMessage()
                ]
            ];
        }
    }
} 