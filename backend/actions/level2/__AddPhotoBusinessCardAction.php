<?php
/**
 * __AddPhotoBusinessCardAction — L2 Action для сохранения фото к визитке
 * 
 * Назначение: Сохраняет фото к визитке (файл + запись в БД)
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
 *   - business_card_id (int) — ID визитки (обязательно)
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

class __AddPhotoBusinessCardAction {
    
    public static function handle($data) {
        try {
            $businessCardId = $data['business_card_id'] ?? null;
            $userId = $data['user_id'] ?? null;
            
            // Проверяем обязательные параметры
            if (!$businessCardId) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'BUSINESS_CARD_ID_REQUIRED',
                        'message' => 'ID визитки обязателен'
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
            
            Logger::info('L2 Action: Starting photo business card save', [
                'business_card_id' => $businessCardId,
                'user_id' => $userId
            ]);
            
            // Сохраняем фото через L1 Action
            $photoResult = _SavePhotoAction::handle([
                'entity_type' => 'business_card',
                'entity_id' => $businessCardId,
                'uploaded_by' => $userId,
                'photo' => $data['photo'] ?? null,
                'photo_file' => $_FILES['photo'] ?? null,
                'photo_type' => 'cover',
                'description' => 'Фото приглашенного автомобиля'
            ]);
            
            if ($photoResult['success']) {
                Logger::info("Photo business card saved successfully: business_card_id=$businessCardId, photo_id=" . $photoResult['data']['id']);
                
                return [
                    'success' => true,
                    'data' => $photoResult['data']
                ];
            } else {
                Logger::error("Failed to create photo business card record: " . json_encode($photoResult['error']));
                
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'PHOTO_SAVE_FAILED',
                        'message' => 'Ошибка сохранения фото: ' . ($photoResult['error']['message'] ?? 'Неизвестная ошибка')
                    ]
                ];
            }
            
        } catch (Exception $e) {
            Logger::error('__AddPhotoBusinessCardAction failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Ошибка сохранения фото к визитке: ' . $e->getMessage()
                ]
            ];
        }
    }
} 