<?php
/**
 * Участие в событии: да / нет / возможно и флаг +1.
 * Это не роль пользователя, а отдельный ответ на встречу.
 */
require_once __DIR__ . '/../utils/Database.php';

class LinkEventParticipant {
    public $event_id;
    public $user_id;
    public $confidence;
    public $plus_one;
    public $created_at;

    public function __construct($data) {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    public static function find($event_id, $user_id) {
        $data = self::findArray($event_id, $user_id);
        return $data ? new self($data) : null;
    }

    public static function findArray($event_id, $user_id) {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM link_event_participants WHERE event_id = ? AND user_id = ?');
        $stmt->execute([(int)$event_id, (int)$user_id]);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$data) {
            return null;
        }
        $data['plus_one'] = !empty($data['plus_one']);
        return $data;
    }

    /**
     * Сохранить или обновить ответ (yes / maybe / no).
     */
    public static function upsert($eventId, $userId, $confidence, $plusOne) {
        $allowed = ['yes', 'maybe', 'no'];
        if (!in_array($confidence, $allowed, true)) {
            throw new InvalidArgumentException('Некорректный ответ участия');
        }
        $plus = ($confidence === 'yes' && $plusOne) ? 1 : 0;
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'INSERT INTO link_event_participants (event_id, user_id, confidence, plus_one, created_at)
             VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE confidence = VALUES(confidence), plus_one = VALUES(plus_one)'
        );
        $stmt->execute([(int)$eventId, (int)$userId, $confidence, $plus]);
        return self::findArray($eventId, $userId);
    }

    /** Сколько человек едет: «да» плюс гости +1 */
    public static function goingCount($eventId) {
        $tally = self::tallyByEventIds([(int)$eventId]);
        return (int)($tally[(int)$eventId]['going_count'] ?? 0);
    }

    /**
     * Счётчики по нескольким событиям сразу (для списка, без лишних запросов).
     * going_count — едут с учётом +1, maybe_count — «возможно», no_count — «не еду».
     */
    public static function tallyByEventIds(array $eventIds) {
        $ids = array_values(array_unique(array_filter(array_map('intval', $eventIds))));
        if (!$ids) {
            return [];
        }
        $pdo = Database::getInstance();
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare(
            "SELECT event_id,
                    COALESCE(SUM(CASE WHEN confidence = 'yes' THEN 1 + IF(plus_one, 1, 0) ELSE 0 END), 0) AS going_count,
                    COALESCE(SUM(CASE WHEN confidence = 'maybe' THEN 1 ELSE 0 END), 0) AS maybe_count,
                    COALESCE(SUM(CASE WHEN confidence = 'no' THEN 1 ELSE 0 END), 0) AS no_count
             FROM link_event_participants
             WHERE event_id IN ($placeholders)
             GROUP BY event_id"
        );
        $stmt->execute($ids);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[(int)$row['event_id']] = [
                'going_count' => (int)$row['going_count'],
                'maybe_count' => (int)$row['maybe_count'],
                'no_count' => (int)$row['no_count'],
            ];
        }
        return $out;
    }

    /**
     * Кто как ответил — для подробностей внутри карточки события.
     */
    public static function peopleForEvent($eventId) {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare(
            'SELECT lep.user_id, lep.confidence, lep.plus_one,
                    u.first_name_app, u.last_name_app, u.username
             FROM link_event_participants lep
             LEFT JOIN users u ON u.id = lep.user_id
             WHERE lep.event_id = ?
             ORDER BY FIELD(lep.confidence, "yes", "maybe", "no"), lep.created_at'
        );
        $stmt->execute([(int)$eventId]);
        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['plus_one'] = !empty($row['plus_one']);
            $rows[] = $row;
        }
        return $rows;
    }
}
