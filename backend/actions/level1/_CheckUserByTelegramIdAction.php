<?php
/**
 * _CheckUserByTelegramIdAction — базовый L1 Action для проверки пользователя по Telegram ID.
 * 
 * Назначение: Проверяет существование пользователя в базе данных по Telegram ID.
 * Уровень: L1 (базовая операция)
 * Префикс: _ (одно подчёркивание)
 * 
 * Входные данные:
 *   - telegram_id (int) — Telegram ID пользователя
 * 
 * Выходные данные:
 *   - success (boolean) — результат операции
 *   - data (array) — данные пользователя или null
 *   - error (array, опционально) — информация об ошибке
 */
require_once __DIR__ . '/../../utils/Database.php';
require_once __DIR__ . '/../../utils/ValidationHelper.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../models/User.php';

class _CheckUserByTelegramIdAction {
    
    public static function handle($data) {
        try {
            // Валидация обязательных полей
            ValidationHelper::requireFields($data, ['telegram_id']);
            
            // Валидация telegram_id
            ValidationHelper::validateInt($data['telegram_id'], 'telegram_id');
            
            // Ищем пользователя по Telegram ID
            $user = User::findByTelegramId($data['telegram_id']);
            
            Logger::info("User check by telegram_id: {$data['telegram_id']}, found: " . ($user ? 'yes' : 'no'));
            
            return [
                'success' => true,
                'data' => $user ? $user->toArray() : null
            ];
            
        } catch (Exception $e) {
            Logger::error('_CheckUserByTelegramIdAction failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Ошибка проверки пользователя: ' . $e->getMessage()
                ]
            ];
        }
    }
} 