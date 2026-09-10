<?php
/**
 * _SavePhotoAction — L1 Action для сохранения фото любой сущности
 * 
 * Назначение: Универсальное сохранение фото (файл + запись в БД)
 * Уровень: L1 (операция с данными)
 * Префикс: _ (одно подчёркивание)
 * 
 * Логика работы:
 * 1. Проверяем и подготавливаем фото через FileHelper
 * 2. Сохраняем файл на сервер
 * 3. Создаём запись в БД через _CreatePhotoAction
 * 4. Возвращаем данные сохраненного фото
 * 
 * Входные данные:
 *   - entity_type (string) — тип сущности (car, business_card, user, etc.) (обязательно)
 *   - entity_id (int) — ID сущности (обязательно)
 *   - uploaded_by (int) — ID пользователя, загружающего фото (обязательно)
 *   - photo (string, опционально) — фото в формате base64
 *   - photo_file (file, опционально) — фото как файл из $_FILES
 *   - photo_type (string, опционально) — тип фото (по умолчанию 'cover')
 *   - description (string, опционально) — описание фото
 * 
 * Выходные данные:
 *   - success (boolean) — результат операции
 *   - data (array, опционально) — данные сохраненного фото
 *   - error (array, опционально) — информация об ошибке
 * 
 * Использует L1 Actions:
 *   - _CreatePhotoAction — создание записи о фото в БД
 * 
 * Использует Helpers:
 *   - FileHelper — проверка, подготовка и сохранение файла
 */

require_once __DIR__ . '/_CreatePhotoAction.php';
require_once __DIR__ . '/../helpers/FileHelper.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../models/Photo.php';

class _SavePhotoAction {
    
    public static function handle($data) {
        try {
            $entityType = $data['entity_type'] ?? null;
            $entityId = $data['entity_id'] ?? null;
            $uploadedBy = $data['uploaded_by'] ?? null;
            $photoType = $data['photo_type'] ?? 'cover';
            $description = $data['description'] ?? 'Фото';
            
            // Проверяем обязательные параметры
            if (!$entityType) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'ENTITY_TYPE_REQUIRED',
                        'message' => 'Тип сущности обязателен'
                    ]
                ];
            }
            
            if (!$entityId) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'ENTITY_ID_REQUIRED',
                        'message' => 'ID сущности обязателен'
                    ]
                ];
            }
            
            if (!$uploadedBy) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'UPLOADED_BY_REQUIRED',
                        'message' => 'ID пользователя обязателен'
                    ]
                ];
            }
            
            Logger::info('L1 Action: Starting photo save', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'uploaded_by' => $uploadedBy
            ]);
            
            // Подготавливаем фото для сохранения
            $photoFile = FileHelper::preparePhotoForSaving($data, $_FILES);
            
            if (!$photoFile) {
                Logger::info("No photo provided for entity_type=$entityType, entity_id=$entityId");
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'PHOTO_REQUIRED',
                        'message' => 'Фото обязательно для сохранения'
                    ]
                ];
            }
            
            // Получаем следующий ID для фото
            $photoId = Photo::getNextId();
            $extension = 'jpg';
            $fileName = FileHelper::generateCorrectFileName($entityType, $entityId, $photoId, $extension);
            
            // Сохраняем фото на сервер
            $savedPath = FileHelper::savePhoto($photoFile, $entityType, $entityId, $photoId);
            
            // Создаём запись в БД
            $photoResult = _CreatePhotoAction::handle([
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'file_name' => $fileName,
                'url' => $savedPath,
                'photo_type' => $photoType,
                'description' => $description,
                'uploaded_by' => $uploadedBy
            ]);
            
            if ($photoResult['success']) {
                Logger::info("Photo saved successfully: entity_type=$entityType, entity_id=$entityId, photo_id=" . $photoResult['data']['id']);
                
                return [
                    'success' => true,
                    'data' => $photoResult['data']
                ];
            } else {
                Logger::error("Failed to create photo record: " . json_encode($photoResult['error']));
                
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'PHOTO_SAVE_FAILED',
                        'message' => 'Ошибка сохранения фото: ' . ($photoResult['error']['message'] ?? 'Неизвестная ошибка')
                    ]
                ];
            }
            
        } catch (Exception $e) {
            Logger::error('_SavePhotoAction failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Ошибка сохранения фото: ' . $e->getMessage()
                ]
            ];
        }
    }
} 