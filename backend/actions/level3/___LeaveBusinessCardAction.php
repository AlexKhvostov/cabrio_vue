<?php
/**
 * ___LeaveBusinessCardAction — L3 Action для оставления визитки
 * 
 * Назначение: Полный сценарий оставления визитки с OCR распознаванием номера
 * Уровень: L3 (полный сценарий)
 * Префикс: ___ (три подчёркивания)
 * 
 * Логика работы:
 * 1. Получаем фото автомобиля (обязательно)
 * 2. OCR распознавание номера через platerecognizer.com
 * 3. Вызываем __DropBusinessCardAction с распознанным номером
 * 4. Возвращаем информацию о созданной визитке
 * 
 * Входные данные:
 *   - photo (file) — фото автомобиля (обязательно)
 *   - user_id (int) — ID пользователя, оставляющего визитку (обязательно)
 * 
 * Выходные данные:
 *   - success (boolean) — результат операции
 *   - data (array) — данные визитки и автомобиля
 *   - error (array, опционально) — информация об ошибке
 * 
 * Использует L2 Actions:
 *   - __DropBusinessCardAction — создание визитки
 *   - __AddPhotoBusinessCardAction — сохранение фото к визитке
 *   - __AddPhotoCarAction — сохранение фото к автомобилю (если у авто нет владельца)
 * 
 * Использует Helpers:
 *   - RecognizeCarNumberFromPhotoAction — OCR распознавание номера
 */
require_once __DIR__ . '/../level2/__DropBusinessCardAction.php';
require_once __DIR__ . '/../level2/__AddPhotoBusinessCardAction.php';
require_once __DIR__ . '/../level2/__AddPhotoCarAction.php';
require_once __DIR__ . '/../helpers/RecognizeCarNumberFromPhotoAction.php';
require_once __DIR__ . '/../../utils/ValidationHelper.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../utils/AppContext.php';

class ___LeaveBusinessCardAction {
    
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
            
            // Проверяем наличие фото (base64 или файл)
            if ((!isset($data['photo']) || empty($data['photo'])) && 
                (!isset($_FILES['photo']) || $_FILES['photo']['error'] !== UPLOAD_ERR_OK)) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'PHOTO_REQUIRED',
                        'message' => 'Фото автомобиля обязательно для оставления визитки'
                    ]
                ];
            }
            
            // 1. OCR распознавание номера
            try {
                // Проверяем, есть ли base64 данные в data
                if (isset($data['photo']) && !empty($data['photo'])) {
                    // Используем base64 данные
                    $plateNumber = RecognizeCarNumberFromPhotoAction::handleFromBase64($data['photo']);
                } elseif (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                    // Используем файл из $_FILES
                    $plateNumber = RecognizeCarNumberFromPhotoAction::handle($_FILES['photo']);
                } else {
                    throw new Exception('Фото не предоставлено ни в base64, ни в файле');
                }
            } catch (Exception $e) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'OCR_FAILED',
                        'message' => 'Не удалось распознать номер автомобиля: ' . $e->getMessage()
                    ]
                ];
            }
            
            // 2. Создание визитки
            // Если есть base64 данные, создаем временный файл для L2 Action
            if (isset($data['photo']) && !empty($data['photo'])) {
                try {
                    require_once __DIR__ . '/../helpers/FileHelper.php';
                    $_FILES['photo'] = FileHelper::createTempFileFromBase64($data['photo'], 'business_card_photo.jpg');
                } catch (Exception $e) {
                    Logger::error('Failed to create temp file from base64: ' . $e->getMessage());
                    // Продолжаем без фото
                }
            }
            
            $cardResult = __DropBusinessCardAction::handle([
                'plate_number' => $plateNumber
            ]);
            
            if (!$cardResult['success']) {
                return $cardResult;
            }
            
            // 3. Сохраняем фото если передано
            $photoData = null;
            $carPhotoData = null;
            
            if (isset($data['photo']) && !empty($data['photo'])) {
                // Сохраняем фото к визитке
                $photoResult = __AddPhotoBusinessCardAction::handle([
                    'business_card_id' => $cardResult['data']['business_card']['id'],
                    'user_id' => $userId,
                    'photo' => $data['photo']
                ]);
                
                if ($photoResult['success']) {
                    $photoData = $photoResult['data'];
                    Logger::info("Business card photo saved successfully: business_card_id=" . $cardResult['data']['business_card']['id']);
                }
                
                // Проверяем, нужно ли сохранить фото к автомобилю
                $carData = $cardResult['data']['car'];
                $ownerUserId = $carData['owner_user_id'] ?? $carData['owner']['id'] ?? null;
                
                if ($ownerUserId === null) {
                    Logger::info("Car has no owner, saving photo to car as well: car_id=" . $carData['id']);
                    
                    // Сохраняем фото к автомобилю
                    $carPhotoResult = __AddPhotoCarAction::handle([
                        'car_id' => $carData['id'],
                        'user_id' => $userId,
                        'photo' => $data['photo']
                    ]);
                    
                    if ($carPhotoResult['success']) {
                        $carPhotoData = $carPhotoResult['data'];
                        Logger::info("Car photo saved successfully: car_id=" . $carData['id']);
                    }
                } else {
                    Logger::info("Car has owner, not saving photo to car: car_id=" . $carData['id'] . ", owner_user_id=" . $ownerUserId);
                }
            } elseif (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                // Сохраняем фото к визитке
                $photoResult = __AddPhotoBusinessCardAction::handle([
                    'business_card_id' => $cardResult['data']['business_card']['id'],
                    'user_id' => $userId,
                    'photo_file' => $_FILES['photo']
                ]);
                
                if ($photoResult['success']) {
                    $photoData = $photoResult['data'];
                    Logger::info("Business card photo saved successfully: business_card_id=" . $cardResult['data']['business_card']['id']);
                }
                
                // Проверяем, нужно ли сохранить фото к автомобилю
                $carData = $cardResult['data']['car'];
                $ownerUserId = $carData['owner_user_id'] ?? $carData['owner']['id'] ?? null;
                
                if ($ownerUserId === null) {
                    Logger::info("Car has no owner, saving photo to car as well: car_id=" . $carData['id']);
                    
                    // Сохраняем фото к автомобилю
                    $carPhotoResult = __AddPhotoCarAction::handle([
                        'car_id' => $carData['id'],
                        'user_id' => $userId,
                        'photo_file' => $_FILES['photo']
                    ]);
                    
                    if ($carPhotoResult['success']) {
                        $carPhotoData = $carPhotoResult['data'];
                        Logger::info("Car photo saved successfully: car_id=" . $carData['id']);
                    }
                } else {
                    Logger::info("Car has owner, not saving photo to car: car_id=" . $carData['id'] . ", owner_user_id=" . $ownerUserId);
                }
            }
            
            // 4. Формируем ответ
            $response = [
                'success' => true,
                'data' => array_merge($cardResult['data'], [ // Используем развернутые данные из L2 Action
                    'plate_number' => $plateNumber,
                    'message' => self::getActionMessage($cardResult['data']['action'])
                ])
            ];
            
            // Добавляем информацию о фото визитки если загружали
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
            
            // Добавляем информацию о фото автомобиля если оно было сохранено
            if ($carPhotoData) {
                // Исключаем base64 данные из ответа, оставляем только метаданные
                $carPhotoInfo = [
                    'id' => $carPhotoData['id'],
                    'entity_type' => $carPhotoData['entity_type'],
                    'entity_id' => $carPhotoData['entity_id'],
                    'file_name' => $carPhotoData['file_name'],
                    'url' => $carPhotoData['url'],
                    'photo_type' => $carPhotoData['photo_type'],
                    'description' => $carPhotoData['description'],
                    'uploaded_by' => $carPhotoData['uploaded_by'],
                    'created_at' => $carPhotoData['created_at'] ?? null,
                    'updated_at' => $carPhotoData['updated_at'] ?? null
                ];
                $response['data']['car_photo'] = $carPhotoInfo;
            }
            
            Logger::info("Business card left: plate_number=$plateNumber, user_id=$userId, action=" . $cardResult['data']['action']);
            
            return $response;
            
        } catch (Exception $e) {
            Logger::error('___LeaveBusinessCardAction failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Ошибка оставления визитки: ' . $e->getMessage()
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
                return 'Визитка оставлена для существующего автомобиля';
            case 'car_and_card_created':
                return 'Автомобиль создан и визитка оставлена';
            default:
                return 'Визитка оставлена';
        }
    }
} 