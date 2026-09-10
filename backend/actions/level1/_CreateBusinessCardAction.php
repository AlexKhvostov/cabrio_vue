<?php
/**
 * _CreateBusinessCardAction — базовый L1 Action для создания визитки.
 * 
 * Назначение: Создаёт новую визитку в базе данных.
 * Уровень: L1 (базовая операция)
 * Префикс: _ (одно подчёркивание)
 * 
 * Входные данные:
 *   - car_id (int) — ID автомобиля
 *   - user_id (int) — ID пользователя, оставившего визитку
 *   - location (string, опционально) — место оставления визитки
 *   - notes (string, опционально) — заметки/сообщение
 * 
 * Выходные данные:
 *   - success (boolean) — результат операции
 *   - data (array) — развернутые данные созданной визитки
 *   - error (array, опционально) — информация об ошибке
 */
require_once __DIR__ . '/../../utils/Database.php';
require_once __DIR__ . '/../../utils/ValidationHelper.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../models/BusinessCard.php';
require_once __DIR__ . '/../../models/Car.php';
require_once __DIR__ . '/../../models/User.php';

class _CreateBusinessCardAction {
    
    public static function handle($data) {
        try {
            // Валидация обязательных полей
            ValidationHelper::requireFields($data, ['car_id', 'user_id']);
            
            // Валидация car_id и user_id
            ValidationHelper::validateInt($data['car_id'], 'car_id');
            ValidationHelper::validateInt($data['user_id'], 'user_id');
            
            // Проверяем существование автомобиля
            $car = Car::findById($data['car_id']);
            if (!$car) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'CAR_NOT_FOUND',
                        'message' => 'Автомобиль не найден'
                    ]
                ];
            }
            
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
            
            // Подготавливаем данные для создания
            $cardData = [
                'car_id' => $data['car_id'],
                'location' => $data['location'] ?? null,
                'notes' => $data['notes'] ?? $data['message'] ?? null,
                'inviter_user_id' => $data['user_id']
            ];
            
            // Создаём визитку с развернутыми данными
            $cardData = BusinessCard::createWithDetails($cardData);
            
            if (!$cardData) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'CARD_CREATION_FAILED',
                        'message' => 'Не удалось создать визитку'
                    ]
                ];
            }
            
            Logger::info("Business card created: ID={$cardData['id']}, car_id={$data['car_id']}, user_id={$data['user_id']}");
            
            return [
                'success' => true,
                'data' => $cardData // Возвращаем развернутые данные
            ];
            
        } catch (Exception $e) {
            Logger::error('_CreateBusinessCardAction failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Ошибка создания визитки: ' . $e->getMessage()
                ]
            ];
        }
    }
} 