<?php
/**
 * Модель GuideObject — работа с таблицей guide_objects.
 *
 * Назначение:
 *   Представляет гид-объект (место, сервис, точку интереса).
 *   Используется для отображения на карте, фильтрации, отзывов и модерации.
 *
 * Ключевые поля:
 *   - id: уникальный идентификатор
 *   - guide_object_type_id: FK на ref_guide_object_types
 *   - guide_object_kind_id: FK на ref_guide_object_kinds
 *   - name, city, address, website, phone, description, add_user_id, status_id, created_at, updated_at
 *
 * Связи:
 *   - Type (guide_object_type_id → ref_guide_object_types.id)
 *   - Kind (guide_object_kind_id → ref_guide_object_kinds.id)
 *   - Author (add_user_id → users.id)
 *   - Status (status_id → ref_statuses.id)
 *   - Photos (entity_type = 'guide_object')
 *   - Reviews (reviews.guide_object_id)
 *
 * Пример использования:
 *   $obj = GuideObject::findById(1);
 *   $newObj = GuideObject::create([...]);
 */
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/UrlHelper.php';
require_once __DIR__ . '/User.php';
class GuideObject {
    public $id;
    public $guide_object_type_id;
    public $guide_object_kind_id;
    public $name;
    public $city;
    public $address;
    public $website;
    public $phone;
    public $description;
    public $add_user_id;
    public $status_id;
    public $created_at;
    public $updated_at;
    // ... другие поля по необходимости

    public function __construct($data) {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * Найти гид-объект по id
     */
    public static function findById($id) {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM guide_objects WHERE id = ?');
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        return $data ? new self($data) : null;
    }

    /**
     * Создать новый гид-объект
     */
    public static function create($data) {
        $pdo = Database::getInstance();
        require_once __DIR__ . '/Status.php';
        $statusId = Status::idByCode('active', 1);
        $stmt = $pdo->prepare(
            'INSERT INTO guide_objects (
                guide_object_type_id, guide_object_kind_id, name, city, address, website, phone,
                description, add_user_id, status_id, created_at, updated_at
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            $data['guide_object_type_id'] ?: null,
            $data['guide_object_kind_id'] ?: null,
            $data['name'],
            $data['city'] ?? null,
            $data['address'] ?? null,
            $data['website'] ?? null,
            $data['phone'] ?? null,
            $data['description'] ?? null,
            (int)$data['add_user_id'],
            $statusId,
        ]);
        return (int)$pdo->lastInsertId();
    }

    /**
     * Обновить поля места (название, тип, адрес и т.д.)
     */
    public static function updateById($id, $data) {
        $pdo = Database::getInstance();
        $allowed = [
            'guide_object_type_id', 'guide_object_kind_id', 'name', 'city', 'address',
            'website', 'phone', 'description', 'status_id',
        ];
        $sets = [];
        $vals = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $sets[] = "$key = ?";
                $vals[] = $data[$key] === '' ? null : $data[$key];
            }
        }
        if (!$sets) {
            return true;
        }
        $sets[] = 'updated_at = NOW()';
        $vals[] = (int)$id;
        $stmt = $pdo->prepare('UPDATE guide_objects SET ' . implode(', ', $sets) . ' WHERE id = ?');
        return $stmt->execute($vals);
    }

    /**
     * Обновить статус гид-объекта
     */
    public static function updateStatus($guideObjectId, $statusId) {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('UPDATE guide_objects SET status_id = ?, updated_at = NOW() WHERE id = ?');
        return $stmt->execute([$statusId, $guideObjectId]);
    }

    /**
     * Получить список гид-объектов с раскрытыми объектами type, kind, author и photo
     */
    public static function getAll()
    {
        return self::fetchExpanded(null, true, false);
    }

    public static function findExpanded($id)
    {
        $rows = self::fetchExpanded((int)$id, false, true);
        return $rows[0] ?? null;
    }

    private static function fetchExpanded($id, $hideDeleted, $withReviews = false)
    {
        $pdo = Database::getInstance();
        $sql = 'SELECT go.*,
                    got.code as _type_code, got.name as _type_name,
                    gok.code as _kind_code, gok.name as _kind_name,
                    u.first_name_app as author_first_name, u.last_name_app as author_last_name,
                    u.username as author_username, u.telegram_photo_url as author_tg,
                    up.id as author_photo_id, up.url as author_photo_url,
                    s.code as _st_code, s.name as _st_name,
                    p.id as photo_id, p.url as photo_url, p.description as photo_description
             FROM guide_objects go
             LEFT JOIN ref_guide_object_types got ON go.guide_object_type_id = got.id
             LEFT JOIN ref_guide_object_kinds gok ON go.guide_object_kind_id = gok.id
             LEFT JOIN users u ON go.add_user_id = u.id
             LEFT JOIN ref_statuses s ON go.status_id = s.id
             LEFT JOIN photos p ON p.id = (
                 SELECT id FROM photos
                 WHERE entity_type = "guide_object" AND entity_id = go.id
                 ORDER BY id DESC LIMIT 1
             )
             LEFT JOIN photos up ON up.id = (
                 SELECT id FROM photos
                 WHERE entity_type = "user" AND entity_id = u.id
                 ORDER BY id DESC LIMIT 1
             )';
        $params = [];
        $where = [];
        if ($id) {
            $where[] = 'go.id = ?';
            $params[] = $id;
        }
        if ($hideDeleted) {
            $where[] = '(s.code IS NULL OR LOWER(s.code) NOT IN ("deleted","removed","удалён","удален"))';
        }
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY go.id DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $guideObjects = [];
        foreach ($rows as $row) {
            $guideObjects[] = self::hydrate($row, $withReviews);
        }
        require_once __DIR__ . '/Label.php';
        Label::attachToList($guideObjects);
        return $guideObjects;
    }

    private static function hydrate($row, $withReviews = false)
    {
        require_once __DIR__ . '/Review.php';
        $guideObject = $row;
        $guideObject['guide_object_type'] = [
            'id' => $row['guide_object_type_id'],
            'code' => $row['_type_code'],
            'name' => $row['_type_name'],
        ];
        $guideObject['guide_object_kind'] = $row['guide_object_kind_id'] ? [
            'id' => (int)$row['guide_object_kind_id'],
            'code' => $row['_kind_code'] ?? null,
            'name' => $row['_kind_name'] ?? null,
        ] : null;
        $guideObject['author'] = $row['add_user_id'] ? [
            'id' => (int)$row['add_user_id'],
            'first_name' => $row['author_first_name'],
            'last_name' => $row['author_last_name'],
            'username' => $row['author_username'] ?? null,
            'photo' => User::photoFromJoin($row['author_photo_id'] ?? null, $row['author_photo_url'] ?? null, $row['author_tg'] ?? null),
        ] : null;
        $guideObject['status'] = [
            'id' => $row['status_id'],
            'code' => $row['_st_code'],
            'name' => $row['_st_name'],
        ];
        $guideObject['photo'] = $row['photo_id'] ? [
            'id' => (int)$row['photo_id'],
            'url' => UrlHelper::buildUploadsUrlSized($row['photo_url'], 'orig'),
            'urls' => [
                'medium' => UrlHelper::buildUploadsUrlSized($row['photo_url'], 'medium'),
                'mini' => UrlHelper::buildUploadsUrlSized($row['photo_url'], 'mini'),
            ],
            'description' => $row['photo_description'],
        ] : null;
        unset(
            $guideObject['_type_code'], $guideObject['_type_name'],
            $guideObject['_kind_code'], $guideObject['_kind_name'],
            $guideObject['author_first_name'], $guideObject['author_last_name'],
            $guideObject['author_username'], $guideObject['author_tg'],
            $guideObject['author_photo_id'], $guideObject['author_photo_url'],
            $guideObject['_st_code'], $guideObject['_st_name'],
            $guideObject['photo_id'], $guideObject['photo_url'], $guideObject['photo_description']
        );
        $guideObject['reviews'] = $withReviews ? Review::getByGuideObject((int)$row['id']) : [];
        $guideObject['rating'] = Review::averages((int)$row['id']);
        return $guideObject;
    }

    /** Сколько карточек в разделе «Отзывы» (без удалённых) */
    public static function countListed()
    {
        $pdo = Database::getInstance();
        $stmt = $pdo->query(
            "SELECT COUNT(*)
             FROM guide_objects go
             LEFT JOIN ref_statuses s ON go.status_id = s.id
             WHERE LOWER(COALESCE(s.code,'')) <> 'deleted'"
        );
        return (int)$stmt->fetchColumn();
    }
} 