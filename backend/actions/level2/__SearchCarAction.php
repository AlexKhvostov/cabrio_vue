<?php
/**
 * __SearchCarAction — L2 Action для поиска автомобиля в базе данных
 * 
 * Назначение: Ищет автомобиль по номеру и создаёт запись если не найден
 * Уровень: L2 (бизнес-операция)
 * Префикс: __ (два подчёркивания)
 * 
 * Логика работы:
 * 1. Проверяем существование автомобиля по номеру
 * 2. Если автомобиль найден:
 *    - Возвращаем данные автомобиля
 *    - Возвращаем action: "found"
 * 3. Если автомобиль не найден:
 *    - Создаём новый автомобиль со статусом "замечена"
 *    - Возвращаем action: "created"
 * 4. Если передана фото:
 *    - Сохраняем файл на сервер
 *    - Создаём запись в БД через L1 Action
 * 
 * Входные данные:
 *   - plate_number (string) — номер автомобиля (обязательно)
 *   - model (string, опционально) — модель автомобиля
 *   - color (string, опционально) — цвет автомобиля
 *   - year (int, опционально) — год выпуска
 *   - photo (file, опционально) — фото автомобиля
 * 
 * Пользователь получается из глобального контекста (AppContext)
 * 
 * Выходные данные:
 *   - success (boolean) — результат операции
 *   - data (array) — данные автомобиля и информация о действии
 *   - error (array, опционально) — информация об ошибке
 * 
 * Использует L1 Actions:
 *   - _CheckCarInDbAction — проверка существования автомобиля
 *   - _CreateCarAction — создание нового автомобиля
 *   - _UpdateStatusAction — обновление статуса автомобиля
 *   - _CreatePhotoAction — создание записи о фото (если передана фото)
 * 
 * Использует Helpers:
 *   - FileHelper — сохранение файла на сервер
 */

require_once __DIR__ . '/../level1/_CheckCarInDbAction.php';
require_once __DIR__ . '/../level1/_CreateCarAction.php';
require_once __DIR__ . '/../level1/_UpdateStatusAction.php';
require_once __DIR__ . '/../level1/_CreatePhotoAction.php';
require_once __DIR__ . '/../helpers/FileHelper.php';
require_once __DIR__ . '/../../utils/ValidationHelper.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../utils/AppContext.php';
require_once __DIR__ . '/../../models/Car.php';
require_once __DIR__ . '/../../models/Photo.php';

class __SearchCarAction {
    
    public static function handle($data) {
        Logger::info('__SearchCarAction: Starting', [
            'input_data' => $data,
            'files' => isset($_FILES) ? array_keys($_FILES) : 'no_files'
        ]);
        
        try {
            // Получаем пользователя из глобального контекста
            $user = AppContext::getCurrentUser();
            Logger::info('__SearchCarAction: User from context', [
                'user' => $user,
                'user_type' => gettype($user)
            ]);
            
            if (!$user) {
                Logger::warning('__SearchCarAction: No user in context');
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'NO_USER',
                        'message' => 'Пользователь не найден в контексте'
                    ]
                ];
            }
            
            $createUserId = $user['id'];
            
            Logger::info('__SearchCarAction: User found', [
                'user_id' => $createUserId,
                'user_data' => $user
            ]);
            
            // Валидация обязательных полей
            ValidationHelper::requireFields($data, ['plate_number']);
            
            $plateNumber = $data['plate_number'];
            $action = null;
            $carData = null;
            
            // 1. Проверяем существование автомобиля
            Logger::info('__SearchCarAction: Checking car in database', [
                'plate_number' => $plateNumber
            ]);
            
            $checkResult = _CheckCarInDbAction::handle(['plate_number' => $plateNumber]);
            
            Logger::info('__SearchCarAction: Check result', [
                'check_success' => $checkResult['success'] ?? false,
                'check_data' => $checkResult['data'] ?? null,
                'check_error' => $checkResult['error'] ?? null
            ]);
            
            if ($checkResult['success'] && $checkResult['data'] !== null) {
                // Автомобиль найден
                Logger::info('__SearchCarAction: Car found in database', [
                    'car_data' => $checkResult['data']
                ]);
                
                $carData = $checkResult['data'];
                $action = 'found';
                $carId = $carData['id'];
                
            } else {
                // Автомобиль не найден - создаём новый
                Logger::info('__SearchCarAction: Car not found, creating new one');
                
                $createData = [
                    'reg_number' => $plateNumber,
                    'create_user_id' => $createUserId,
                    'model' => $data['model'] ?? null,
                    'color' => $data['color'] ?? null,
                    'year' => $data['year'] ?? null,
                    'owner_user_id' => null, // Без владельца
                    'status_id' => 1 // "замечена" по умолчанию
                ];
                
                Logger::info('__SearchCarAction: Creating car with data', [
                    'create_data' => $createData
                ]);
                
                $createResult = _CreateCarAction::handle($createData);
                
                Logger::info('__SearchCarAction: Create result', [
                    'create_success' => $createResult['success'] ?? false,
                    'create_data' => $createResult['data'] ?? null,
                    'create_error' => $createResult['error'] ?? null
                ]);
                
                if ($createResult['success']) {
                    $action = 'created';
                    $carData = $createResult['data'];
                    $carId = $carData['id'];
                    
                    Logger::info('__SearchCarAction: Car created successfully', [
                        'car_id' => $carId,
                        'car_data' => $carData
                    ]);
                } else {
                    Logger::error('__SearchCarAction: Failed to create car', [
                        'create_error' => $createResult['error']
                    ]);
                    return $createResult;
                }
            }
            
            // 2. Обрабатываем фото если передана
            $photoData = null;
            if (isset($data['photo']) && !empty($data['photo'])) {
                try {
                    // Получаем следующий ID заранее
                    $photoId = Photo::getNextId();
                    $extension = 'jpg'; // Бот отправляет в формате JPEG
                    $fileName = FileHelper::generateCorrectFileName('car', $carId, $photoId, $extension);
                    
                    // Сохраняем файл на сервер используя новый метод для base64
                    $savedPath = FileHelper::savePhotoFromBase64($data['photo'], 'car', $carId, $photoId, 'car_photo.jpg');
                    
                    // Создаём запись в БД
                    $photoResult = _CreatePhotoAction::handle([
                        'entity_type' => 'car',
                        'entity_id' => $carId,
                        'file_name' => $fileName,
                        'url' => $savedPath,
                        'photo_type' => 'cover',
                        'description' => 'Фото автомобиля',
                        'uploaded_by' => $createUserId
                    ]);
                    
                    if ($photoResult['success']) {
                        $photoData = $photoResult['data'];
                        Logger::info("Photo saved successfully: car_id=$carId, photo_id=" . $photoData['id']);
                    } else {
                        Logger::error("Failed to create photo record: " . json_encode($photoResult['error']));
                    }
                    
                } catch (Exception $e) {
                    Logger::error('Photo upload failed: ' . $e->getMessage());
                    // Не прерываем выполнение, только логируем ошибку
                }
            } elseif (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                try {
                    // Сохраняем файл на сервер
                    $photoId = Photo::getNextId(); // Получаем следующий ID заранее
                    $extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                    $fileName = FileHelper::generateCorrectFileName('car', $carId, $photoId, $extension);
                    
                    // Сохраняем файл
                    $savedPath = FileHelper::savePhoto($_FILES['photo'], 'car', $carId, $photoId);
                    
                    // Создаём запись в БД
                    $photoResult = _CreatePhotoAction::handle([
                        'entity_type' => 'car',
                        'entity_id' => $carId,
                        'file_name' => $fileName,
                        'url' => $savedPath,
                        'photo_type' => 'cover',
                        'description' => 'Фото автомобиля',
                        'uploaded_by' => $createUserId
                    ]);
                    
                    if ($photoResult['success']) {
                        $photoData = $photoResult['data'];
                    }
                    
                } catch (Exception $e) {
                    Logger::error('Photo upload failed: ' . $e->getMessage());
                    // Не прерываем выполнение, только логируем ошибку
                }
            }
            
            // 3. Формируем ответ
            $response = [
                'success' => true,
                'data' => array_merge($carData, [ // Используем развернутые данные из L1 Actions
                    'action' => $action,
                    'message' => self::getActionMessage($action)
                ])
            ];
            
            // Добавляем информацию о фото если загружали
            if ($photoData) {
                // Исключаем base64 данные из ответа, оставляем только метаданные
                $photoInfo = [
                    'id' => $photoData['id'],
                    'entity_type' => $photoData['entity_type'],
                    'entity_id' => $photoData['entity_id'],
                    'file_name' => $photoData['file_name'],
                    'url' => $photoData['url'],
                    'photo_type' => $photoData['photo_type'],
                    'description' => $photoData['description'],
                    'uploaded_by' => $photoData['uploaded_by'],
                    'created_at' => $photoData['created_at'] ?? null,
                    'updated_at' => $photoData['updated_at'] ?? null
                ];
                $response['data']['photo'] = $photoInfo;
            }
            
            Logger::info("Car search completed: plate_number=$plateNumber, action=$action");
            
            return $response;
            
        } catch (Exception $e) {
            Logger::error('__SearchCarAction failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Ошибка поиска автомобиля: ' . $e->getMessage()
                ]
            ];
        }
    }
    
    /**
     * Получить сообщение для действия
     */
    private static function getActionMessage($action) {
        switch ($action) {
            case 'found':
                return 'Автомобиль найден в базе данных';
            case 'created':
                return 'Автомобиль добавлен в базу данных';
            default:
                return 'Операция выполнена';
        }
    }
} 