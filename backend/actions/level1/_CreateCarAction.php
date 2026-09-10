<?php
/**
 * _CreateCarAction — базовый L1 Action для создания автомобиля.
 * 
 * Назначение: Создаёт новый автомобиль в базе данных.
 * Уровень: L1 (базовая операция)
 * Префикс: _ (одно подчёркивание)
 * 
 * Входные данные:
 *   - create_user_id (int) — ID создателя записи (обязательное)
 *   - model (string, опционально) — модель автомобиля
 *   - color (string, опционально) — цвет
 *   - year (int, опционально) — год выпуска
 *   - reg_number (string, опционально) — регистрационный номер
 *   - owner_user_id (int|null, опционально) — ID владельца (null для замеченных машин)
 *   - status_id (int, опционально) — ID статуса (по умолчанию 1 - noticed)
 * 
 * Выходные данные:
 *   - success (boolean) — результат операции
 *   - data (array) — развернутые данные созданного автомобиля
 *   - error (array, опционально) — информация об ошибке
 */
require_once __DIR__ . '/../../utils/Database.php';
require_once __DIR__ . '/../../utils/ValidationHelper.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../models/Car.php';
require_once __DIR__ . '/../../models/CarBrand.php';
require_once __DIR__ . '/../../models/Status.php';

class _CreateCarAction {
    
    public static function handle($data) {
        Logger::info('_CreateCarAction: Starting', [
            'input_data' => $data
        ]);
        
        try {
            // Валидация обязательных полей
            ValidationHelper::requireFields($data, ['create_user_id']);
            
            // Валидация owner_user_id если передан
            if (isset($data['owner_user_id']) && $data['owner_user_id'] !== null) {
                ValidationHelper::validateInt($data['owner_user_id'], 'owner_user_id');
            }
            
            // Подготавливаем данные для создания
            $carData = [
                'car_brand_id' => $data['car_brand_id'] ?? null, // не обязательное поле
                'model' => $data['model'] ?? null,
                'color' => $data['color'] ?? null,
                'year' => $data['year'] ?? null,
                'reg_number' => $data['reg_number'] ?? null,
                'create_user_id' => $data['create_user_id'],
                'owner_user_id' => $data['owner_user_id'] ?? null,
                'status_id' => $data['status_id'] ?? 1 // noticed по умолчанию
            ];
            
            // Валидация года если передан
            if (isset($carData['year'])) {
                ValidationHelper::validateInt($carData['year'], 'year');
                if ($carData['year'] < 1900 || $carData['year'] > date('Y') + 1) {
                    return [
                        'success' => false,
                        'error' => [
                            'code' => 'INVALID_YEAR',
                            'message' => 'Некорректный год выпуска: ' . $carData['year']
                        ]
                    ];
                }
            }
            
            // Создаём автомобиль с развернутыми данными
            Logger::info('_CreateCarAction: Calling Car::createWithDetails', [
                'car_data' => $carData
            ]);
            
            $carData = Car::createWithDetails($carData);
            
            Logger::info('_CreateCarAction: Car::createWithDetails result', [
                'result' => $carData,
                'result_type' => gettype($carData)
            ]);
            
            if (!$carData) {
                Logger::error('_CreateCarAction: Car creation failed - null result');
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'CAR_CREATION_FAILED',
                        'message' => 'Не удалось создать автомобиль'
                    ]
                ];
            }
            
            Logger::info("Car created: ID={$carData['id']}, model={$carData['model']}");
            
            return [
                'success' => true,
                'data' => $carData // Возвращаем развернутые данные
            ];
            
        } catch (Exception $e) {
            Logger::error('_CreateCarAction failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Ошибка создания автомобиля: ' . $e->getMessage()
                ]
            ];
        }
    }
} 