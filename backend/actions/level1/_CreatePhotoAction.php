<?php
/**
 * _CreatePhotoAction — базовый L1 Action для создания фото.
 * 
 * Назначение: Создаёт новую запись о фото в базе данных с правильным именованием файлов.
 * Уровень: L1 (базовая операция)
 * Префикс: _ (одно подчёркивание)
 * 
 * Логика работы:
 * 1. Получаем следующий ID фото заранее
 * 2. Формируем правильное имя файла: {entity_type}_{entity_id}_{photo_id}.{ext}
 * 3. Создаём запись в БД с правильным именем сразу
 * 4. Получаем созданное фото
 * 
 * Входные данные:
 *   - entity_type (string) — тип сущности (user, car, event, business_card)
 *   - entity_id (int) — ID сущности
 *   - file_name (string) — исходное имя файла (для получения расширения)
 *   - url (string) — не используется (генерируется автоматически)
 *   - photo_type (string, опционально) — тип фото (avatar, cover, gallery)
 *   - description (string, опционально) — описание/подпись
 *   - uploaded_by (int) — ID пользователя, загрузившего фото
 * 
 * Выходные данные:
 *   - success (boolean) — результат операции
 *   - data (array) — данные созданного фото с правильным именем файла
 *   - error (array, опционально) — информация об ошибке
 */
require_once __DIR__ . '/../../utils/Database.php';
require_once __DIR__ . '/../../utils/ValidationHelper.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../../models/Photo.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../helpers/FileHelper.php';

class _CreatePhotoAction {
    
    public static function handle($data) {
        try {
            // Валидация обязательных полей
            ValidationHelper::requireFields($data, ['entity_type', 'entity_id', 'file_name', 'url', 'uploaded_by']);
            
            // Валидация entity_id и uploaded_by
            ValidationHelper::validateInt($data['entity_id'], 'entity_id');
            ValidationHelper::validateInt($data['uploaded_by'], 'uploaded_by');
            
            // Проверяем существование пользователя
            $user = User::findById($data['uploaded_by']);
            if (!$user) {
                return [
                    'success' => false,
                    'error' => [
                        'code' => 'USER_NOT_FOUND',
                        'message' => 'Пользователь не найден'
                    ]
                ];
            }
            
            // Получаем следующий ID заранее
            $nextPhotoId = Photo::getNextId();
            
            // Формируем правильное имя файла сразу
            $extension = pathinfo($data['file_name'], PATHINFO_EXTENSION);
            $correctFileName = FileHelper::generateCorrectFileName(
                $data['entity_type'], 
                $data['entity_id'], 
                $nextPhotoId, 
                $extension
            );
            
            // Формируем правильный URL сразу
            $correctUrl = "/uploads/{$data['entity_type']}/{$correctFileName}";
            
            // Подготавливаем данные для создания с правильным именем сразу
            $photoData = [
                'entity_type' => $data['entity_type'],
                'entity_id' => $data['entity_id'],
                'file_name' => $correctFileName, // правильное имя сразу
                'url' => $correctUrl, // правильный URL сразу
                'photo_type' => $data['photo_type'] ?? null,
                'description' => $data['description'] ?? null,
                'uploaded_by' => $data['uploaded_by']
            ];
            
            // Создаём фото через модель (получаем реальный photo_id)
            $photoId = Photo::create($photoData);
            
            // Получаем созданное фото
            $photo = Photo::findById($photoId);
            
            Logger::info("Photo created: ID=$photoId, entity_type={$data['entity_type']}, entity_id={$data['entity_id']}");
            
            return [
                'success' => true,
                'data' => $photo->toArray()
            ];
            
        } catch (Exception $e) {
            Logger::error('_CreatePhotoAction failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Ошибка создания фото: ' . $e->getMessage()
                ]
            ];
        }
    }
} 