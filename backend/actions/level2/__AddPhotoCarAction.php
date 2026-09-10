<?php
/**
 * __AddPhotoCarAction — L2 Action для сохранения фото к автомобилю
 * 
 * Назначение: Сохраняет фото к автомобилю (файл + запись в БД)
 * Уровень: L2 (бизнес-операция)
 * Префикс: __ (два подчёркивания)
 * 
 * Логика работы:
 * 1. Проверяем наличие фото (base64 или файл)
 * 2. Если фото есть - сохраняем файл на сервер
 * 3. Создаём запись в БД через _CreatePhotoAction
 * 4. Возвращаем данные сохраненного фото
 * 
 * Входные данные:
 *   - car_id (int) — ID автомобиля (обязательно)
 *   - user_id (int) — ID пользователя, загружающего фото (обязательно)
 *   - photo (string, опционально) — фото в формате base64
 *   - photo_file (file, опционально) — фото как файл из $_FILES
 * 
 * Выходные данные:
 *   - success (boolean) — результат операции
 *   - data (array, опционально) — данные сохраненного фото
 *   - error (array, опционально) — информация об ошибке
 * 
 * Использует L1 Actions:
 *   - _SavePhotoAction — сохранение фото (файл + запись в БД)
 */

require_once __DIR__ . '/../level1/_SavePhotoAction.php';
require_once __DIR__ . '/../../utils/Logger.php';

class __AddPhotoCarAction {
    
    public static function handle($data) {
        try {
            $carId = $data['car_id'] ?? null;
            $userId = $data['user_id'] ?? null;
            
            // Проверяем обязательные параметры
            if (!$carId) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'CAR_ID_REQUIRED',
                        'message' => 'ID автомобиля обязателен'
                    ]
                ];
            }
            
            if (!$userId) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'USER_ID_REQUIRED',
                        'message' => 'ID пользователя обязателен'
                    ]
                ];
            }
            
            Logger::info('L2 Action: Starting photo car save', [
                'car_id' => $carId,
                'user_id' => $userId
            ]);
            
            // Сохраняем фото через L1 Action
            $photoResult = _SavePhotoAction::handle([
                'entity_type' => 'car',
                'entity_id' => $carId,
                'uploaded_by' => $userId,
                'photo' => $data['photo'] ?? null,
                'photo_file' => $_FILES['photo'] ?? null,
                'photo_type' => 'cover',
                'description' => 'Фото автомобиля'
            ]);
            
            if ($photoResult['success']) {
                Logger::info("Photo car saved successfully: car_id=$carId, photo_id=" . $photoResult['data']['id']);
                
                return [
                    'success' => true,
                    'data' => $photoResult['data']
                ];
            } else {
                Logger::error("Failed to create photo car record: " . json_encode($photoResult['error']));
                
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'PHOTO_SAVE_FAILED',
                        'message' => 'Ошибка сохранения фото: ' . ($photoResult['error']['message'] ?? 'Неизвестная ошибка')
                    ]
                ];
            }
            
        } catch (Exception $e) {
            Logger::error('__AddPhotoCarAction failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Ошибка сохранения фото к автомобилю: ' . $e->getMessage()
                ]
            ];
        }
    }
} 