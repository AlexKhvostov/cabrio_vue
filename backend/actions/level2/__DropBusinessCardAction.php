<?php
/**
 * __DropBusinessCardAction — L2 Action для добавления визитки в базу
 * 
 * Назначение: Добавляет визитку в базу с проверкой существования авто, и созданием авто если её нет с статусом визитка
 * Уровень: L2 (бизнес-операция)
 * Префикс: __ (два подчёркивания)
 * 
 * Логика работы:
 * 1. Проверяем существование автомобиля по номеру
 * 2. Если автомобиль найден:
 *    - Создаём визитку для существующего автомобиля
 *    - Если статус был "noticed" (ID = 1) → обновляем на "business_card" (ID = 2)
 *    - Если статус другой → оставляем без изменений
 *    - Возвращаем action: "card_created"
 * 3. Если автомобиль не найден:
 *    - Создаём новый автомобиль со статусом "business_card" (ID = 2)
 *    - Создаём визитку для нового автомобиля
 *    - Возвращаем action: "car_and_card_created"
 * 4. Создаём визитку для автомобиля
 * 
 * Входные данные:
 *   - plate_number (string) — номер автомобиля (обязательно)
 *   - car_id (int, опционально) — ID автомобиля (если известен)
 *   - model (string, опционально) — модель автомобиля
 *   - color (string, опционально) — цвет автомобиля
 *   - year (int, опционально) — год выпуска
 *   - photo (file, опционально) — фото автомобиля
 * 
 * Пользователь получается из глобального контекста (AppContext)
 * 
 * Выходные данные:
 *   - success (boolean) — результат операции
 *   - data (array) — данные визитки и автомобиля
 *   - error (array, опционально) — информация об ошибке
 * 
 * Использует L1 Actions:
 *   - _CheckCarInDbAction — проверка существования автомобиля
 *   - _CreateCarAction — создание нового автомобиля
 *   - _UpdateStatusAction — обновление статуса автомобиля
 *   - _CreateBusinessCardAction — создание визитки
 */

require_once __DIR__ . '/../level1/_CheckCarInDbAction.php';
require_once __DIR__ . '/../level1/_CreateCarAction.php';
require_once __DIR__ . '/../level1/_UpdateStatusAction.php';
require_once __DIR__ . '/../level1/_CreateBusinessCardAction.php';
require_once __DIR__ . '/../../utils/ValidationHelper.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../utils/AppContext.php';
require_once __DIR__ . '/../../models/Car.php';

class __DropBusinessCardAction {
    
    public static function handle($data) {
        try {
            // Получаем пользователя из глобального контекста
            $user = AppContext::getCurrentUser();
            if (!$user) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'NO_USER',
                        'message' => 'Пользователь не найден в контексте'
                    ]
                ];
            }
            
            $userId = $user['id'];
            
            // Валидация обязательных полей
            ValidationHelper::requireFields($data, ['plate_number']);
            
            $plateNumber = $data['plate_number'];
            $action = null;
            $carData = null;
            $cardData = null;
            
            // 1. Проверяем существование автомобиля
            $checkResult = _CheckCarInDbAction::handle(['plate_number' => $plateNumber]);
            
            if ($checkResult['success'] && $checkResult['data'] !== null) {
                // Автомобиль найден
                $carData = $checkResult['data'];
                $carId = $carData['id'];
                $action = 'card_created';
                
                // Обновляем статус только если он был "noticed" (ID = 1)
                $currentStatusId = $carData['status_id'] ?? $carData['status']['id'] ?? null;
                if ($currentStatusId == 1) {
                    $updateResult = _UpdateStatusAction::handle([
                        'entity_type' => 'car',
                        'entity_id' => $carId,
                        'status_id' => 2 // business_card
                    ]);
                    
                    if ($updateResult['success']) {
                        // Обновляем данные автомобиля с новым статусом
                        // Получаем обновленные данные автомобиля
                        $updatedCarResult = Car::findByIdWithDetails($carId);
                        // Исправлено: Car::findByIdWithDetails возвращает просто массив данных, а не массив с ключом 'success'
                        if ($updatedCarResult) {
                            $carData = $updatedCarResult;
                        }
                        Logger::info("Car status updated from noticed to business_card: car_id=$carId");
                    } else {
                        Logger::warning("Failed to update car status to business_card: car_id=$carId");
                        // Не прерываем выполнение, только логируем
                    }
                } else {
                    Logger::info("Car status not updated (was not 'noticed'): car_id=$carId, current_status_id=" . $currentStatusId);
                }
                
            } else {
                // Автомобиль не найден - создаём новый со статусом "business_card"
                $createData = [
                    'reg_number' => $plateNumber,
                    'create_user_id' => $userId,
                    'model' => $data['model'] ?? null,
                    'color' => $data['color'] ?? null,
                    'year' => $data['year'] ?? null,
                    'owner_user_id' => null, // Без владельца
                    'status_id' => 2 // "business_card" (ID = 2)
                ];
                
                $createResult = _CreateCarAction::handle($createData);
                
                if ($createResult['success']) {
                    $carData = $createResult['data'];
                    $carId = $carData['id'];
                    $action = 'car_and_card_created';
                } else {
                    return $createResult;
                }
            }
            
            // 2. Создаём визитку
            $cardResult = _CreateBusinessCardAction::handle([
                'car_id' => $carId,
                'user_id' => $userId
            ]);
            
            if ($cardResult['success']) {
                $cardData = $cardResult['data'];
            } else {
                return $cardResult;
            }
            
            // 3. Формируем ответ
            $response = [
                'success' => true,
                'data' => [
                    'action' => $action,
                    'car' => $carData, // Используем развернутые данные из L1 Actions
                    'business_card' => $cardData, // Используем развернутые данные из L1 Actions
                    'message' => self::getActionMessage($action)
                ]
            ];
            

            
            Logger::info("Business card dropped: plate_number=$plateNumber, user_id=$userId, action=$action");
            
            return $response;
            
        } catch (Exception $e) {
            Logger::error('__DropBusinessCardAction failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Ошибка добавления визитки: ' . $e->getMessage()
                ]
            ];
        }
    }
    
    /**
     * Получить сообщение для действия
     */
    private static function getActionMessage($action) {
        switch ($action) {
            case 'card_created':
                return 'Визитка добавлена для существующего автомобиля';
            case 'car_and_card_created':
                return 'Автомобиль создан и визитка добавлена';
            default:
                return 'Операция выполнена';
        }
    }
} 