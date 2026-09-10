<?php
/**
 * _UpdateOwnerToCarAction — базовый L1 Action для установки владельца авто.
 * 
 * Назначение: Устанавливает владельца автомобиля в базе данных.
 * Уровень: L1 (базовая операция)
 * Префикс: _ (одно подчёркивание)
 * 
 * Входные данные:
 *   - car_id (int) — ID автомобиля
 *   - user_id (int) — ID пользователя-владельца
 * 
 * Выходные данные:
 *   - success (boolean) — результат операции
 *   - data (array) — обновленные данные автомобиля
 *   - error (array, опционально) — информация об ошибке
 */
require_once __DIR__ . '/../../utils/Database.php';
require_once __DIR__ . '/../../utils/ValidationHelper.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../models/Car.php';
require_once __DIR__ . '/../../models/User.php';

class _UpdateOwnerToCarAction {
    
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
            
            // Проверяем, есть ли уже владелец у автомобиля
            if ($car->owner_user_id !== null) {
                Logger::info('_UpdateOwnerToCarAction: Car already has owner', [
                    'car_id' => $data['car_id'],
                    'owner_user_id' => $car->owner_user_id,
                    'requested_user_id' => $data['user_id']
                ]);
                
                // Проверяем, является ли текущий пользователь владельцем
                if ($car->owner_user_id == $data['user_id']) {
                    return [
                        'success' => false,
                        'error' => [
                            'code' => 'CAR_ALREADY_IN_GARAGE',
                            'message' => 'Автомобиль уже принадлежит вам'
                        ]
                    ];
                } else {
                return [
                    'success' => false,
                    'error' => [
                            'code' => 'CAR_OWNED_BY_OTHER',
                            'message' => 'Автомобиль принадлежит другому участнику клуба'
                    ]
                ];
                }
            }
            
            // Обновляем владельца автомобиля и устанавливаем статус "Активный"
            $result = Car::updateOwner($data['car_id'], $data['user_id']);
            
            if (!$result) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'UPDATE_FAILED',
                        'message' => 'Ошибка обновления владельца автомобиля'
                    ]
                ];
            }
            
            // Устанавливаем статус "Активный" (status_id = 7)
            require_once __DIR__ . '/_UpdateStatusAction.php';
            $statusResult = _UpdateStatusAction::handle([
                'entity_type' => 'car',
                'entity_id' => $data['car_id'],
                'status_id' => 7 // "Активный"
            ]);
            
            if (!$statusResult['success']) {
                Logger::warning("Failed to update car status to active", [
                    'car_id' => $data['car_id'],
                    'error' => $statusResult['error']
                ]);
                // Не прерываем выполнение, только логируем
            }
            
            // Получаем обновленный автомобиль с развернутыми данными
            $updatedCar = Car::findById($data['car_id']);
            
            // Развертываем данные автомобиля
            require_once __DIR__ . '/../../utils/ExpandHelper.php';
            $carData = ExpandHelper::expandCarData($updatedCar->toArray());
            
            Logger::info("Car owner updated: car_id={$data['car_id']}, user_id={$data['user_id']}");
            
            return [
                'success' => true,
                'data' => $carData
            ];
            
        } catch (Exception $e) {
            Logger::error('_UpdateOwnerToCarAction failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Ошибка обновления владельца автомобиля: ' . $e->getMessage()
                ]
            ];
        }
    }
} 