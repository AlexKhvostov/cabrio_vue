<?php
/**
 * __SyncUserDataAction — L2 Action для синхронизации данных пользователя
 * 
 * Назначение: Проверяет существование пользователя по Telegram ID и создаёт/обновляет его данные
 * Уровень: L2 (бизнес-операция)
 * Префикс: __ (два подчёркивания)
 * 
 * Логика работы:
 * 1. Проверяем существование пользователя по Telegram ID
 * 2. Если пользователь найден:
 *    - Сравниваем данные с переданными
 *    - Обновляем только изменившиеся поля
 *    - Возвращаем action: "updated" или "no_changes"
 * 3. Если пользователь не найден:
 *    - Создаём нового пользователя с ролью guest
 *    - Возвращаем action: "created"
 * 4. Если передана фото:
 *    - Сохраняем файл на сервер как аватар пользователя
 *    - Создаём запись в БД через L1 Action
 * 
 * Входные данные:
 *   - telegram_id (int) — Telegram ID пользователя (обязательно)
 *   - first_name (string, опционально) — имя пользователя
 *   - last_name (string, опционально) — фамилия пользователя
 *   - username (string, опционально) — username в Telegram
 *   - photo (file, опционально) — аватар пользователя
 * 
 * Выходные данные:
 *   - success (boolean) — результат операции
 *   - data (array) — данные пользователя и информация о действии
 *   - error (array, опционально) — информация об ошибке
 * 
 * Использует L1 Actions:
 *   - _CheckUserByTelegramIdAction — проверка существования пользователя
 *   - _CreateUserAction — создание нового пользователя
 *   - _UpdateUserAction — обновление данных пользователя
 *   - _CreatePhotoAction — создание записи о фото (если передана фото)
 * 
 * Использует Helpers:
 *   - FileHelper — сохранение файла на сервер
 */

require_once __DIR__ . '/../../utils/load_env.php';
require_once __DIR__ . '/../level1/_CheckUserByTelegramIdAction.php';
require_once __DIR__ . '/../level1/_CreateUserAction.php';
require_once __DIR__ . '/../level1/_UpdateUserAction.php';
require_once __DIR__ . '/../level1/_CreatePhotoAction.php';
require_once __DIR__ . '/../helpers/FileHelper.php';
require_once __DIR__ . '/../helpers/TelegramAvatarHelper.php';
require_once __DIR__ . '/../../utils/ValidationHelper.php';
require_once __DIR__ . '/../../utils/Logger.php';

class __SyncUserDataAction {
    
    /**
     * @param array $data   Данные пользователя из Telegram
     * @param array $options Опции выполнения. Поддерживаемые ключи:
     *                       - allow_file_upload (bool) — разрешить обработку $_FILES['photo'] как аватара (по умолчанию false)
     */
    public static function handle($data, array $options = []) {
        try {
            // Валидация обязательных полей
            ValidationHelper::requireFields($data, ['telegram_id']);
            
            // Валидация telegram_id
            ValidationHelper::validateInt($data['telegram_id'], 'telegram_id');
            
            $telegramId = $data['telegram_id'];
            $action = null;
            $userData = null;
            
            // 1. Проверяем существование пользователя
            $checkResult = _CheckUserByTelegramIdAction::handle(['telegram_id' => $telegramId]);
            
            if ($checkResult['success'] && $checkResult['data'] !== null) {
                // Пользователь найден - обновляем данные
                $userData = $checkResult['data'];
                $userId = $userData['id'];
                
                // Подготавливаем данные для обновления
                $updateData = [];
                $changedFields = [];
                
                // Проверяем какие поля изменились
                if (isset($data['first_name']) && $data['first_name'] !== $userData['first_name']) {
                    $updateData['first_name'] = $data['first_name'];
                    $changedFields[] = 'first_name';
                }
                
                if (isset($data['last_name']) && $data['last_name'] !== $userData['last_name']) {
                    $updateData['last_name'] = $data['last_name'];
                    $changedFields[] = 'last_name';
                }
                
                if (isset($data['username']) && $data['username'] !== $userData['username']) {
                    $updateData['username'] = $data['username'];
                    $changedFields[] = 'username';
                }
                
                // Если есть изменения - обновляем
                if (!empty($updateData)) {
                    $updateResult = _UpdateUserAction::handle([
                        'user_id' => $userId,
                        'first_name' => $updateData['first_name'] ?? null,
                        'last_name' => $updateData['last_name'] ?? null,
                        'username' => $updateData['username'] ?? null
                    ]);
                    
                    if ($updateResult['success']) {
                        $action = 'updated';
                        $userData = $updateResult['data'];
                    } else {
                        return $updateResult;
                    }
                } else {
                    $action = 'no_changes';
                }
                
            } else {
                // Пользователь не найден - создаём нового
                $createData = [
                    'telegram_id' => $telegramId,
                    'first_name' => $data['first_name'] ?? null,
                    'last_name' => $data['last_name'] ?? null,
                    'username' => $data['username'] ?? null,
                    'role_id' => 2 // guest по умолчанию
                ];
                
                $createResult = _CreateUserAction::handle($createData);
                
                if ($createResult['success']) {
                    $action = 'created';
                    $userData = $createResult['data'];
                    $userId = $userData['id'];
                } else {
                    return $createResult;
                }
            }
            
            // 2. Обрабатываем фото если передана (ТОЛЬКО если явно разрешено через опции)
            $photoData = null;
            $allowFileUpload = (bool)($options['allow_file_upload'] ?? false);
            if ($allowFileUpload && isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
                try {
                    // Сохраняем файл на сервер
                    $photoId = Photo::getNextId(); // Получаем следующий ID заранее
                    $extension = pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION);
                    $fileName = FileHelper::generateCorrectFileName('user', $userId, $photoId, $extension);
                    
                    // Сохраняем файл
                    $savedPath = FileHelper::savePhoto($_FILES['photo'], 'user', $userId, $photoId);
                    
                    // Создаём запись в БД
                    $photoResult = _CreatePhotoAction::handle([
                        'entity_type' => 'user',
                        'entity_id' => $userId,
                        'file_name' => $fileName,
                        'url' => $savedPath,
                        'photo_type' => 'avatar',
                        'description' => 'Аватар пользователя',
                        'uploaded_by' => $userId
                    ]);
                    
                    if ($photoResult['success']) {
                        $photoData = $photoResult['data'];
                    }
                    
                } catch (Exception $e) {
                    Logger::error('Photo upload failed: ' . $e->getMessage());
                    // Не прерываем выполнение, только логируем ошибку
                }
            } else {
                // Если фото не передано, пробуем получить аватар из Telegram
                try {
                    $photoData = TelegramAvatarHelper::getUserAvatar($telegramId, $userId);
                    if ($photoData) {
                        Logger::info("TelegramAvatarHelper: Successfully got avatar for telegram_id=$telegramId");
                    }
                } catch (Exception $e) {
                    Logger::warning('TelegramAvatarHelper: Failed to get avatar from Telegram', [
                        'telegram_id' => $telegramId,
                        'error' => $e->getMessage()
                    ]);
                    // Не прерываем выполнение, только логируем ошибку
                }
            }
            
            // 3. Формируем ответ
            $response = [
                'success' => true,
                'data' => array_merge($userData, [ // Используем развернутые данные из L1 Actions
                    'action' => $action,
                    'message' => self::getActionMessage($action)
                ])
            ];
            
            // Добавляем информацию об изменённых полях если обновляли
            if ($action === 'updated') {
                $response['data']['updated_fields'] = $changedFields;
            }
            
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
                
                // Добавляем аватар в глобальный контекст
                AppContext::setUserAvatar($photoInfo);
                
                Logger::info("Avatar added to global context", [
                    'user_id' => $userId,
                    'avatar_id' => $photoData['id'] ?? null
                ]);
            }
            
            Logger::info("User sync completed: telegram_id=$telegramId, action=$action");
            
            return $response;
            
        } catch (Exception $e) {
            Logger::error('__SyncUserDataAction failed: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => 'Ошибка синхронизации пользователя: ' . $e->getMessage()
                ]
            ];
        }
    }
    
    /**
     * Получить сообщение для действия
     */
    private static function getActionMessage($action) {
        switch ($action) {
            case 'created':
                return 'Пользователь успешно создан';
            case 'updated':
                return 'Данные пользователя обновлены';
            case 'no_changes':
                return 'Данные пользователя актуальны';
            default:
                return 'Операция выполнена';
        }
    }
} 