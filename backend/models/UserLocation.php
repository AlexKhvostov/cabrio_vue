<?php

require_once __DIR__ . '/../utils/Database.php';

/**
 * Модель для работы с координатами пользователей (user_locations)
 */
class UserLocation
{
    protected $table = 'user_locations';

    public static function create(array $data)
    {
        $db = Database::getInstance();
        $sql = "INSERT INTO user_locations (user_id, latitude, longitude, updated_at) VALUES (:user_id, :latitude, :longitude, :updated_at)";
        $stmt = $db->prepare($sql);
        $stmt->execute([
            ':user_id' => $data['user_id'],
            ':latitude' => $data['latitude'],
            ':longitude' => $data['longitude'],
            ':updated_at' => $data['updated_at']
        ]);
        return $db->lastInsertId();
    }

    public static function where($field, $operator, $value)
    {
        return new static($field, $operator, $value);
    }

    public function first()
    {
        $db = Database::getInstance();
        $sql = "SELECT * FROM {$this->table} WHERE {$this->field} {$this->operator} :value LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([':value' => $this->value]);
        $result = $stmt->fetch(PDO::FETCH_OBJ);
        if ($result) { $this->data = $result; return $this; }
        return null;
    }

    public function update(array $data)
    {
        $db = Database::getInstance();
        $sql = "UPDATE {$this->table} SET latitude = :latitude, longitude = :longitude, updated_at = :updated_at WHERE id = :id";
        $stmt = $db->prepare($sql);
        return $stmt->execute([
            ':latitude' => $data['latitude'],
            ':longitude' => $data['longitude'],
            ':updated_at' => $data['updated_at'],
            ':id' => $this->data->id
        ]);
    }

    /**
     * Убрать точку пользователя с карты (выключил «показывать меня»).
     */
    public static function deleteByUserId($userId)
    {
        $db = Database::getInstance();
        $sql = "DELETE FROM user_locations WHERE user_id = :user_id";
        $stmt = $db->prepare($sql);
        return $stmt->execute([':user_id' => $userId]);
    }

    public static function getActiveLocations($cutoffTime)
    {
        $db = Database::getInstance();
        // По схеме БД аватар пользователя — это запись с MAX(id) в photos по entity_type='user' и entity_id=u.id
        $sql = "SELECT ul.*, 
                       u.first_name_app, u.first_name_tg, u.username, u.telegram_photo_url,
                       (SELECT url FROM photos p 
                         WHERE p.entity_type = 'user' AND p.entity_id = u.id 
                         ORDER BY p.id DESC LIMIT 1) AS photo_url
                FROM user_locations ul
                LEFT JOIN users u ON ul.user_id = u.id
                WHERE ul.updated_at >= :cutoff_time";
        $stmt = $db->prepare($sql);
        $stmt->execute([':cutoff_time' => $cutoffTime]);
        return $stmt->fetchAll(PDO::FETCH_OBJ);
    }

    /** Сколько точек «живые» на карте — для цифры на главной. */
    public static function countLive($minutes = 60)
    {
        $minutes = max(1, (int)$minutes);
        $db = Database::getInstance();
        $sql = "SELECT COUNT(*) FROM user_locations
                WHERE updated_at >= DATE_SUB(UTC_TIMESTAMP(), INTERVAL {$minutes} MINUTE)";
        return (int)$db->query($sql)->fetchColumn();
    }

    private $field;
    private $operator;
    private $value;
    private $data;

    private function __construct($field, $operator, $value)
    {
        $this->field = $field;
        $this->operator = $operator;
        $this->value = $value;
    }

    public function __get($name)
    {
        return $this->data->$name ?? null;
    }
}
