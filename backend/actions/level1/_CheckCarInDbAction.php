<?php
/**
 * _CheckCarInDbAction — базовый L1 Action для проверки существования авто в базе.
 * 
 * Назначение: Проверяет существование автомобиля в базе данных по номеру.
 * Уровень: L1 (базовая операция)
 * Префикс: _ (одно подчёркивание)
 * 
 * Входные данные:
 *   - plate_number (string) — номер автомобиля
 * 
 * Выходные данные:
 *   - success (boolean) — результат операции
 *   - data (array) — развернутые данные автомобиля или null
 *   - error (array, опционально) — информация об ошибке
 */
require_once __DIR__ . '/../../utils/Database.php';
require_once __DIR__ . '/../../utils/ValidationHelper.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../models/Car.php';

class _CheckCarInDbAction {
    
    public static function handle($data) {
        try {
            // Валидация обязательных полей
            ValidationHelper::requireFields($data, ['plate_number']);
            
            // Валидация plate_number (проверяем что не пустой)
            if (empty($data['plate_number'])) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_PLATE_NUMBER',
                        'message' => 'Номер автомобиля не может быть пустым'
                    ]
                ];
            }
            
            // Нормализуем номер в нижний регистр и ищем авто (сравнение регистронезависимо)
            $plate = strtolower(trim($data['plate_number']));
            $carData = Car::findByPlateNumberWithDetails($plate);
            
            Logger::info("Car check by plate_number: {$plate}, found: " . ($carData ? 'yes' : 'no'));
            
            return [
                'success' => true,
                'data' => $carData // Возвращаем развернутые данные
            ];
            
        } catch (Exception $e) {
            Logger::error('_CheckCarInDbAction failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Ошибка проверки автомобиля: ' . $e->getMessage()
                ]
            ];
        }
    }
} 