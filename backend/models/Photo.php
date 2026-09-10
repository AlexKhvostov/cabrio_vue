<?php
/**
 * Модель Photo — работа с таблицей photos.
 *
 * Назначение:
 *   Представляет фото, связанное с любой сущностью (user, car, event, review и т.д.).
 *   Используется для аватаров, галерей, обложек и других изображений.
 *
 * Ключевые поля:
 *   - id: уникальный идентификатор
 *   - entity_type: тип сущности (user, car, event, ...)
 *   - entity_id: ID сущности
 *   - file_name, url, photo_type, description, uploaded_at, uploaded_by
 *
 * Связи:
 *   - User, Car, Event, Review и др. (логическая связь по entity_type и entity_id)
 *   - UploadedBy (uploaded_by → users.id)
 *
 * Пример использования:
 *   $photo = Photo::findById(1);
 *   $newPhoto = Photo::create([...]);
 */
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/UrlHelper.php';

class Photo {
    public $id;
    public $entity_type;
    public $entity_id;
    public $file_name;
    public $url;
    public $photo_type;
    public $description;
    public $uploaded_at;
    public $uploaded_by;

    public function __construct($data) {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * Найти фото по id
     */
    public static function findById($id) {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM photos WHERE id = ?');
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        return $data ? new self($data) : null;
    }

    /**
     * Получить все фото (с фильтрацией по сущности)
     */
    public static function getAll($entityType = null, $entityId = null) {
        $pdo = Database::getInstance();
        
        $sql = 'SELECT * FROM photos';
        $params = [];
        
        if ($entityType && $entityId) {
            $sql .= ' WHERE entity_type = ? AND entity_id = ?';
            $params = [$entityType, $entityId];
        } elseif ($entityType) {
            $sql .= ' WHERE entity_type = ?';
            $params = [$entityType];
        }
        
        $sql .= ' ORDER BY uploaded_at DESC';
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetchAll();
        
        $photos = [];
        foreach ($data as $row) {
            // Приводим путь к абсолютному URL и добавляем размеры
            $absOrig = UrlHelper::buildUploadsUrlSized($row['url'] ?? '', 'orig');
            $absMed  = UrlHelper::buildUploadsUrlSized($row['url'] ?? '', 'medium');
            $absMin  = UrlHelper::buildUploadsUrlSized($row['url'] ?? '', 'mini');
            $item = [
                'id' => (int)$row['id'],
                'entity_type' => $row['entity_type'],
                'entity_id' => (int)$row['entity_id'],
                'file_name' => $row['file_name'],
                'url' => $absOrig,
                'urls' => [ 'medium' => $absMed, 'mini' => $absMin ],
                'photo_type' => $row['photo_type'],
                'description' => $row['description'],
                'uploaded_at' => $row['uploaded_at'],
                'uploaded_by' => $row['uploaded_by'] ? (int)$row['uploaded_by'] : null,
            ];
            $photos[] = $item;
        }
        
        return $photos;
    }

    /**
     * Последняя обложка сущности (авто, человек и т.д.) — как в плитках списка.
     * Фото лежат в таблице photos, а не в строке cars/users.
     *
     * @param string $entityType  Например car или user
     * @param int    $entityId
     * @param int|null $onlyByUserId  Если задан — только фото, загруженные этим человеком
     * @return array|null  url + urls.medium/mini
     */
    public static function latestFor($entityType, $entityId, $onlyByUserId = null)
    {
        $pdo = Database::getInstance();
        // Тот же смысл, что в списке авто: чужие загрузки не показываем как обложку
        if ($onlyByUserId) {
            $stmt = $pdo->prepare(
                'SELECT id, url, description FROM photos
                 WHERE entity_type = ? AND entity_id = ? AND uploaded_by = ?
                 ORDER BY id DESC LIMIT 1'
            );
            $stmt->execute([$entityType, (int)$entityId, (int)$onlyByUserId]);
        } else {
            $stmt = $pdo->prepare(
                'SELECT id, url, description FROM photos
                 WHERE entity_type = ? AND entity_id = ?
                 ORDER BY id DESC LIMIT 1'
            );
            $stmt->execute([$entityType, (int)$entityId]);
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        return [
            'id' => (int)$row['id'],
            'url' => UrlHelper::buildUploadsUrl($row['url']),
            'urls' => [
                'medium' => UrlHelper::buildUploadsUrlSized($row['url'], 'medium'),
                'mini' => UrlHelper::buildUploadsUrlSized($row['url'], 'mini'),
                'orig' => UrlHelper::buildUploadsUrl($row['url']),
            ],
            'description' => $row['description'],
        ];
    }

    /**
     * Получить следующий ID для фото
     */
    public static function getNextId() {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT MAX(id) as max_id FROM photos');
        $stmt->execute();
        $result = $stmt->fetch();
        return ($result['max_id'] ?? 0) + 1;
    }

    /**
     * Создать новое фото
     */
    public static function create($data) {
        $pdo = Database::getInstance();
        
        // Подготовка данных для вставки
        $fields = ['entity_type', 'entity_id', 'file_name', 'url', 'photo_type', 'description', 'uploaded_by'];
        $placeholders = implode(', ', array_fill(0, count($fields), '?'));
        $fieldNames = implode(', ', $fields);
        
        $stmt = $pdo->prepare("INSERT INTO photos ($fieldNames, uploaded_at) VALUES ($placeholders, NOW())");
        
        $values = [
            $data['entity_type'],
            $data['entity_id'],
            $data['file_name'],
            $data['url'],
            $data['photo_type'] ?? null,
            $data['description'] ?? null,
            $data['uploaded_by'] ?? null
        ];
        
        $stmt->execute($values);
        return $pdo->lastInsertId();
    }

    /**
     * Преобразовать объект фото в массив
     */
    public function toArray() {
        return [
            'id' => $this->id,
            'entity_type' => $this->entity_type,
            'entity_id' => $this->entity_id,
            'file_name' => $this->file_name,
            'url' => $this->url,
            'photo_type' => $this->photo_type,
            'description' => $this->description,
            'uploaded_at' => $this->uploaded_at,
            'uploaded_by' => $this->uploaded_by
        ];
    }

    /**
     * Обновить фото
     */
    public static function update($id, $data) {
        $pdo = Database::getInstance();
        
        // Подготовка данных для обновления
        $fields = [];
        $values = [];
        
        foreach ($data as $field => $value) {
            $fields[] = "$field = ?";
            $values[] = $value;
        }
        
        $values[] = $id; // для WHERE id = ?
        
        $fieldUpdates = implode(', ', $fields);
        $stmt = $pdo->prepare("UPDATE photos SET $fieldUpdates WHERE id = ?");
        
        $stmt->execute($values);
        return $stmt->rowCount() > 0;
    }

    /**
     * Удалить фото
     */
    public function delete() {
        // ... реализация удаления из БД
    }
} 