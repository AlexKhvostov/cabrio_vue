<?php
/**
 * ___AddCarToGarageAction — L3 Action для добавления автомобиля в гараж
 * 
 * Назначение: Полный сценарий добавления авто в гараж с OCR распознаванием номера
 * Уровень: L3 (полный сценарий)
 * Префикс: ___ (три подчёркивания)
 * 
 * Логика работы:
 * 1. Получаем фото автомобиля (обязательно)
 * 2. OCR распознавание номера через platerecognizer.com
 * 3. Вызываем __AddCarToUserAction с распознанным номером
 * 4. Возвращаем информацию о добавленном в гараж авто
 * 
 * Входные данные:
 *   - photo (file) — фото автомобиля (обязательно)
 *   - user_id (int) — ID пользователя, добавляющего авто в гараж (обязательно)
 * 
 * Выходные данные:
 *   - success (boolean) — результат операции
 *   - data (array) — данные автомобиля и информация о действии
 *   - error (array, опционально) — информация об ошибке
 * 
 * Использует L2 Actions:
 *   - __AddCarToUserAction — добавление автомобиля пользователю
 *   - __AddPhotoCarAction — сохранение фото к автомобилю (если пользователь владелец)
 * 
 * Использует Helpers:
 *   - RecognizeCarNumberFromPhotoAction — OCR распознавание номера
 */
require_once __DIR__ . '/../level2/__AddCarToUserAction.php';
require_once __DIR__ . '/../level2/__AddPhotoCarAction.php';
require_once __DIR__ . '/../helpers/RecognizeCarNumberFromPhotoAction.php';
require_once __DIR__ . '/../../utils/ValidationHelper.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../utils/AppContext.php';

class ___AddCarToGarageAction {
    
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
            

            
            // 1. OCR распознавание номера
            $plateNumber = null;
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
                Logger::warning('OCR failed, creating car without plate number: ' . $e->getMessage());
                // Продолжаем без номера - создаём авто без номера
                $plateNumber = null;
            }
            
            // 2. Добавление автомобиля в гараж
            // Если есть base64 данные, создаем временный файл для L2 Action
            if (isset($data['photo']) && !empty($data['photo'])) {
                try {
                    require_once __DIR__ . '/../helpers/FileHelper.php';
                    $_FILES['photo'] = FileHelper::createTempFileFromBase64($data['photo'], 'car_photo.jpg');
                } catch (Exception $e) {
                    Logger::error('Failed to create temp file from base64: ' . $e->getMessage());
                    // Продолжаем без фото
                }
            }
            
            Logger::info('L3 Action: Calling L2 Action', [
                'plate_number' => $plateNumber,
                'has_photo' => isset($data['photo']) && !empty($data['photo']),
                'user_id' => $userId
            ]);
            
            $addResult = __AddCarToUserAction::handle([
                'plate_number' => $plateNumber // может быть null
            ]);
            
            Logger::info('L3 Action: L2 Action result', [
                'success' => $addResult['success'],
                'error' => $addResult['error'] ?? null,
                'data' => $addResult['data'] ?? null
            ]);
            
            if (!$addResult['success']) {
                // Логируем ошибку от L2 Action
                Logger::warning('L2 Action failed: ' . json_encode($addResult['error']));
                return $addResult;
            }
            
            // 3. Сохраняем фото если передано и пользователь владелец
            $photoData = null;
            
            // Проверяем, является ли пользователь владельцем
            $isOwner = ($addResult['data']['owner_user_id'] === $userId);
            
            if ($isOwner) {
                Logger::info('L3 Action: User is owner, saving photo to car', [
                    'user_id' => $userId,
                    'car_id' => $addResult['data']['car_id']
                ]);
                
                // Сохраняем фото к автомобилю
                $photoResult = __AddPhotoCarAction::handle([
                    'car_id' => $addResult['data']['car_id'],
                    'user_id' => $userId,
                    'photo' => $data['photo'] ?? null
                ]);
                
                if ($photoResult['success']) {
                    $photoData = $photoResult['data'];
                    Logger::info("Photo saved successfully: car_id=" . $addResult['data']['car_id']);
                } else {
                    Logger::error("Failed to save photo: " . json_encode($photoResult['error']));
                }
            } else {
                Logger::info('L3 Action: User is not owner, not saving photo', [
                    'user_id' => $userId,
                    'car_id' => $addResult['data']['car_id'],
                    'owner_user_id' => $addResult['data']['owner_user_id']
                ]);
            }
            
            // 4. Формируем ответ
            $response = [
                'success' => true,
                'data' => array_merge($addResult['data'], [ // Используем развернутые данные из L2 Action
                    'plate_number' => $plateNumber,
                    'message' => self::getActionMessage($addResult['data']['action'], $plateNumber)
                ])
            ];
            
            // Добавляем информацию о фото если оно было сохранено
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
            
            $plateInfo = $plateNumber ? "plate_number=$plateNumber" : "without plate number";
            Logger::info("Car added to garage: $plateInfo, user_id=$userId, action=" . $addResult['data']['action']);
            
            return $response;
            
        } catch (Exception $e) {
            Logger::error('___AddCarToGarageAction failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Ошибка добавления автомобиля в гараж: ' . $e->getMessage()
                ]
            ];
        }
    }
    
    /**
     * Получить сообщение для действия
     */
    private static function getActionMessage($action, $plateNumber = null) {
        $plateInfo = $plateNumber ? "с номером $plateNumber" : "без номера";
        
        switch ($action) {
            case 'assigned':
                return "Автомобиль $plateInfo назначен вам и добавлен в гараж";
            case 'created':
                return "Автомобиль $plateInfo создан и добавлен в ваш гараж";
            default:
                return "Автомобиль $plateInfo добавлен в гараж";
        }
    }
} 