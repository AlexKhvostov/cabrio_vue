<?php
/**
 * Модель Review — работа с таблицей reviews.
 *
 * Назначение:
 *   Представляет отзыв пользователя о гид-объекте.
 *   Используется для отображения рейтинга, модерации, обратной связи.
 *
 * Ключевые поля:
 *   - id: уникальный идентификатор
 *   - guide_object_id: FK на guide_objects
 *   - quality_rating, speed_rating, price_rating, feedback, author_user_id, status_id, created_at, updated_at
 *
 * Связи:
 *   - GuideObject (guide_object_id → guide_objects.id)
 *   - Author (author_user_id → users.id)
 *   - Status (status_id → ref_statuses.id)
 *   - Photos (entity_type = 'review')
 *
 * Пример использования:
 *   $review = Review::findById(1);
 *   $newReview = Review::create([...]);
 */
require_once __DIR__ . '/../utils/Database.php';

class Review {
    public $id;
    public $guide_object_id;
    public $quality_rating;
    public $speed_rating;
    public $price_rating;
    public $feedback;
    public $author_user_id;
    public $status_id;
    public $created_at;
    public $updated_at;

    public function __construct($data) {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    /**
     * Найти отзыв по id
     */
    public static function findById($id) {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM reviews WHERE id = ?');
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        return $data ? new self($data) : null;
    }

    /**
     * Получить все отзывы
     */
    public static function getAll() {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM reviews ORDER BY created_at DESC');
        $stmt->execute();
        $data = $stmt->fetchAll();
        
        $reviews = [];
        foreach ($data as $row) {
            $reviews[] = (new self($row))->toArray();
        }
        
        return $reviews;
    }

    /**
     * Создать новый отзыв
     */
    public static function getByGuideObject($guideObjectId) {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'SELECT r.*, u.first_name_app AS author_first, u.last_name_app AS author_last
             FROM reviews r
             LEFT JOIN users u ON r.author_user_id = u.id
             WHERE r.guide_object_id = ?
             ORDER BY r.created_at DESC'
        );
        $stmt->execute([(int)$guideObjectId]);
        $out = [];
        foreach ($stmt->fetchAll() as $row) {
            $item = (new self($row))->toArray();
            $item['author'] = [
                'id' => (int)$row['author_user_id'],
                'first_name' => $row['author_first'],
                'last_name' => $row['author_last'],
            ];
            $out[] = $item;
        }
        return $out;
    }

    public static function findByAuthor($guideObjectId, $userId) {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM reviews WHERE guide_object_id = ? AND author_user_id = ? LIMIT 1');
        $stmt->execute([(int)$guideObjectId, (int)$userId]);
        $data = $stmt->fetch();
        return $data ? new self($data) : null;
    }

    public static function averages($guideObjectId) {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS cnt,
                    AVG(quality_rating) AS quality,
                    AVG(speed_rating) AS speed,
                    AVG(price_rating) AS price
             FROM reviews WHERE guide_object_id = ?'
        );
        $stmt->execute([(int)$guideObjectId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $cnt = (int)($row['cnt'] ?? 0);
        if ($cnt === 0) {
            return ['count' => 0, 'quality' => null, 'speed' => null, 'price' => null, 'overall' => null];
        }
        $q = round((float)$row['quality'], 1);
        $s = round((float)$row['speed'], 1);
        $p = round((float)$row['price'], 1);
        return [
            'count' => $cnt,
            'quality' => $q,
            'speed' => $s,
            'price' => $p,
            'overall' => round(($q + $s + $p) / 3, 1),
        ];
    }

    /**
     * Один отзыв на объект от одного человека: если уже есть — обновляем.
     * Оценки качества / скорости / цены — целые от 1 до 5.
     */
    public static function create($data) {
        $pdo = Database::getInstance();
        require_once __DIR__ . '/Status.php';
        $existing = self::findByAuthor($data['guide_object_id'], $data['author_user_id']);
        $q = max(1, min(5, (int)$data['quality_rating']));
        $s = max(1, min(5, (int)$data['speed_rating']));
        $p = max(1, min(5, (int)$data['price_rating']));
        $feedback = trim((string)($data['feedback'] ?? ''));
        if ($existing) {
            $stmt = $pdo->prepare(
                'UPDATE reviews SET quality_rating = ?, speed_rating = ?, price_rating = ?, feedback = ?, updated_at = NOW() WHERE id = ?'
            );
            $stmt->execute([$q, $s, $p, $feedback, $existing->id]);
            return (int)$existing->id;
        }
        $statusId = Status::idByCode('active', 1);
        $stmt = $pdo->prepare(
            'INSERT INTO reviews (guide_object_id, quality_rating, speed_rating, price_rating, feedback, author_user_id, status_id, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
        );
        $stmt->execute([
            (int)$data['guide_object_id'],
            $q, $s, $p, $feedback,
            (int)$data['author_user_id'],
            $statusId,
        ]);
        return (int)$pdo->lastInsertId();
    }

    /**
     * Преобразовать объект отзыва в массив
     */
    public function toArray() {
        return [
            'id' => $this->id,
            'guide_object_id' => $this->guide_object_id,
            'quality_rating' => $this->quality_rating,
            'speed_rating' => $this->speed_rating,
            'price_rating' => $this->price_rating,
            'feedback' => $this->feedback,
            'author_user_id' => $this->author_user_id,
            'status_id' => $this->status_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at
        ];
    }

    /**
     * Правка отзыва: свой — всегда; чужой — только если $asStaff (модератор/админ).
     */
    public static function updateByAuthor($id, $userId, $data, $asStaff = false) {
        $review = self::findById($id);
        if (!$review) {
            return null;
        }
        if (!$asStaff && (int)$review->author_user_id !== (int)$userId) {
            return null;
        }
        $q = max(1, min(5, (int)$data['quality_rating']));
        $s = max(1, min(5, (int)$data['speed_rating']));
        $p = max(1, min(5, (int)$data['price_rating']));
        $feedback = trim((string)($data['feedback'] ?? ''));
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'UPDATE reviews SET quality_rating = ?, speed_rating = ?, price_rating = ?, feedback = ?, updated_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$q, $s, $p, $feedback, (int)$id]);
        return (int)$review->guide_object_id;
    }

    /**
     * Удаление: свой отзыв или любое, если $asStaff.
     */
    public static function deleteByAuthor($id, $userId, $asStaff = false) {
        $review = self::findById($id);
        if (!$review) {
            return null;
        }
        if (!$asStaff && (int)$review->author_user_id !== (int)$userId) {
            return null;
        }
        $guideId = (int)$review->guide_object_id;
        $pdo = Database::getInstance();
        if ($asStaff) {
            $stmt = $pdo->prepare('DELETE FROM reviews WHERE id = ?');
            $stmt->execute([(int)$id]);
        } else {
            $stmt = $pdo->prepare('DELETE FROM reviews WHERE id = ? AND author_user_id = ?');
            $stmt->execute([(int)$id, (int)$userId]);
        }
        return $guideId;
    }
} 