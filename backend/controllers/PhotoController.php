<?php
/**
 * PhotoController — контроллер для работы с фото (photos).
 *
 * Назначение:
 *   Обрабатывает HTTP-запросы, связанные с фото: загрузка, получение, удаление.
 *
 * Зависимости:
 *   - Photo (модель)
 *   - User, Car, Event, Review, GuideObject (модели)
 *   - AuthHelper, ResponseHelper
 *
 * Основные методы:
 *   - getList($entity_type, $entity_id) — получить фото для сущности
 *   - upload($entity_type, $entity_id, $file, $meta) — загрузить фото
 *   - delete($photo_id) — удалить фото
 */
require_once __DIR__ . '/BaseController.php';
require_once __DIR__ . '/../models/Photo.php';

class PhotoController extends BaseController
{
    /**
     * Получить список фото для сущности
     * 
     * Требует авторизации: Да
     * Минимальная роль: member
     */
    public function getList($entityType = null, $entityId = null)
    {
        try {
            // Проверяем авторизацию и права доступа через централизованную конфигурацию
            if (!$this->requireAccess('api.photos.getList')) {
                return; // Ответ уже отправлен в requireAccess
            }
            
            $entityType = $entityType ?? ($_GET['entity_type'] ?? null);
            $entityId = $entityId ?? ($_GET['entity_id'] ?? null);
            $photos = Photo::getAll($entityType, $entityId);
            $this->logUserAction('get_photos_list', ['entity_type' => $entityType, 'entity_id' => $entityId, 'count' => count($photos)]);
            $this->json(['success' => true, 'data' => $photos, 'meta' => $this->getRequestInfo()]);
        } catch (Throwable $e) {
            Logger::error('PhotoController: getList error', ['error' => $e->getMessage(), 'user_id' => $this->getCurrentUserId(), 'entity_type' => $entityType, 'entity_id' => $entityId]);
            $this->json(['success' => false, 'error' => ['code' => 'DB_ERROR', 'message' => $e->getMessage()]], 500);
        }
    }

    /**
     * Загрузить новое фото
     * 
     * Требует авторизации: Да
     * Минимальная роль: member
     */
    public function upload()
    {
        try {
            // Проверяем авторизацию и права доступа через централизованную конфигурацию
            if (!$this->requireAccess('api.photos.upload')) {
                return; // Ответ уже отправлен в requireAccess
            }
            
            $entityType = isset($_POST['entity_type']) ? strtolower(trim((string)$_POST['entity_type'])) : null;
            $entityId = $_POST['entity_id'] ?? null;
            $this->logUserAction('upload_photo', ['entity_type' => $entityType, 'entity_id' => $entityId]);

            if (!$entityType || !$entityId) {
                return $this->json(['success'=>false,'error'=>['code'=>'VALIDATION_ERROR','message'=>'entity_type и entity_id обязательны']], 400);
            }

            $allowedTypes = ['user','car','event','guide_object','business_card'];
            if (!in_array($entityType, $allowedTypes, true)) {
                return $this->json(['success'=>false,'error'=>['code'=>'VALIDATION_ERROR','message'=>'Недопустимый entity_type']], 400);
            }

            // Дополнительные проверки доступа: модератор и выше всегда может; иначе владелец сущности
            $currentUser = $this->getCurrentUser();
            $currentUserId = (int)($currentUser['id'] ?? 0);
            $currentUserRoleCode = $currentUser['role']['code'] ?? ($currentUser['role_code'] ?? null);
            $isModeratorOrAbove = in_array($currentUserRoleCode, ['moderator','admin'], true);

            if (!$isModeratorOrAbove) {
                if ($entityType === 'user') {
                    if ($currentUserId !== (int)$entityId) {
                        return $this->json(['success'=>false,'error'=>['code'=>'FORBIDDEN','message'=>'Можно загружать фото только для своего профиля']], 403);
                    }
                } elseif ($entityType === 'car') {
                    require_once __DIR__ . '/../models/Car.php';
                    $car = Car::findById((int)$entityId);
                    if (!$car || (int)$car->owner_user_id !== $currentUserId) {
                        return $this->json(['success'=>false,'error'=>['code'=>'FORBIDDEN','message'=>'Можно загружать фото только для своих автомобилей']], 403);
                    }
                } elseif ($entityType === 'event') {
                    require_once __DIR__ . '/../models/Event.php';
                    $event = Event::findById((int)$entityId);
                    if (!$event || (int)$event->org_user_id !== $currentUserId) {
                        return $this->json(['success'=>false,'error'=>['code'=>'FORBIDDEN','message'=>'Фото события может загрузить организатор']], 403);
                    }
                } elseif ($entityType === 'guide_object') {
                    require_once __DIR__ . '/../models/GuideObject.php';
                    $obj = GuideObject::findById((int)$entityId);
                    if (!$obj || (int)$obj->add_user_id !== $currentUserId) {
                        return $this->json(['success'=>false,'error'=>['code'=>'FORBIDDEN','message'=>'Фото места может загрузить тот, кто его добавил']], 403);
                    }
                } else {
                    return $this->json(['success'=>false,'error'=>['code'=>'FORBIDDEN','message'=>'Недостаточно прав на загрузку для этой сущности']], 403);
                }
            }

            // Подготовка файла
            require_once __DIR__ . '/../actions/helpers/FileHelper.php';
            require_once __DIR__ . '/../utils/UrlHelper.php';
            require_once __DIR__ . '/../models/Photo.php';

            // Диагностика содержимого $_FILES
            try { Logger::info('PhotoController: incoming $_FILES', ['keys' => array_keys($_FILES), 'photo' => $_FILES['photo'] ?? null]); } catch (Throwable $e) {}
            $prepared = FileHelper::preparePhotoForSaving($_POST, $_FILES);
            if (!$prepared) {
                return $this->json(['success'=>false,'error'=>['code'=>'VALIDATION_ERROR','message'=>'Файл фото не найден']], 400);
            }
            try { Logger::info('PhotoController: prepared file', ['name'=>$prepared['name']??null,'tmp_name'=>$prepared['tmp_name']??null,'size'=>$prepared['size']??null,'type'=>$prepared['type']??null]); } catch (Throwable $e) {}

            // Резервируем ID фото и формируем имя файла
            $photoId = Photo::getNextId();
            $fileName = FileHelper::generateCorrectFileName($entityType, (int)$entityId, (int)$photoId, strtolower(pathinfo($prepared['name'], PATHINFO_EXTENSION)) ?: 'jpg');

            // Сохранить оригинал (в uploads/orig/{entity}) и сгенерировать превью
            $relativePath = FileHelper::savePhoto($prepared, $entityType, (int)$entityId, (int)$photoId);

            // Создать запись в БД
            $newId = Photo::create([
                'entity_type' => $entityType,
                'entity_id' => (int)$entityId,
                'file_name' => $fileName,
                'url' => $relativePath, // в БД храним канонический путь без префикса размера
                'photo_type' => $_POST['photo_type'] ?? ($entityType === 'user' ? 'avatar' : 'cover'),
                'description' => $_POST['description'] ?? ($entityType === 'user' ? 'Аватар пользователя' : 'Фото автомобиля'),
                'uploaded_by' => $currentUserId,
            ]);

            // Формируем ответ с абсолютными URL
            $resp = [
                'id' => (int)$newId,
                'entity_type' => $entityType,
                'entity_id' => (int)$entityId,
                'file_name' => $fileName,
                'url' => UrlHelper::buildUploadsUrlSized($relativePath, 'orig'),
                'urls' => [
                    'medium' => UrlHelper::buildUploadsUrlSized($relativePath, 'medium'),
                    'mini'   => UrlHelper::buildUploadsUrlSized($relativePath, 'mini'),
                ],
                'uploaded_by' => $currentUserId,
            ];

            $typeRu = ['user' => 'профиля', 'car' => 'авто', 'event' => 'встречи', 'guide_object' => 'места', 'business_card' => 'визитки'][$entityType] ?? $entityType;
            $photoSection = ['user' => 'me', 'car' => 'cars', 'event' => 'events', 'guide_object' => 'guide'][$entityType] ?? '';
            $this->audit('create', 'photo', (int)$newId, 'Загрузил фото ' . $typeRu . ' (id ' . (int)$entityId . ')', $photoSection);
            return $this->json(['success'=>true,'data'=>$resp,'meta'=>$this->getRequestInfo()], 201);
        } catch (Throwable $e) {
            Logger::error('PhotoController: upload error', ['error' => $e->getMessage(), 'user_id' => $this->getCurrentUserId()]);
            $this->json(['success' => false, 'error' => ['code' => 'INTERNAL_ERROR', 'message' => $e->getMessage()]], 500);
        }
    }
} 