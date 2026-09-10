<?php
/**
 * Модель Event — таблица events (встречи и поездки клуба).
 * Фото берём из photos (entity_type = event, последнее по id).
 */
require_once __DIR__ . '/../utils/Database.php';
require_once __DIR__ . '/../utils/UrlHelper.php';
require_once __DIR__ . '/Status.php';
require_once __DIR__ . '/LinkEventParticipant.php';
require_once __DIR__ . '/User.php';

class Event {
    public $id;
    public $event_type_id;
    public $title;
    public $description;
    public $event_date;
    public $event_time;
    public $city;
    public $org_user_id;
    public $status_id;
    public $created_at;
    public $updated_at;

    public function __construct($data) {
        foreach ($data as $key => $value) {
            $this->$key = $value;
        }
    }

    public static function findById($id) {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT * FROM events WHERE id = ?');
        $stmt->execute([$id]);
        $data = $stmt->fetch();
        return $data ? new self($data) : null;
    }

    /**
     * Создать событие. Организатор — текущий пользователь.
     */
    public static function create($data) {
        $pdo = Database::getInstance();
        $statusId = Status::idByCode('active', 1);
        $sql = 'INSERT INTO events (
                    event_date, event_time, event_type_id, title, description, location, city,
                    price, max_participants, org_user_id, registration_type, status_id, created_at, updated_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $data['event_date'],
            $data['event_time'] ?: '12:00:00',
            $data['event_type_id'] ?: null,
            $data['title'],
            $data['description'] ?? null,
            $data['location'] ?? null,
            $data['city'] ?? null,
            isset($data['price']) && $data['price'] !== '' ? $data['price'] : 0,
            isset($data['max_participants']) && $data['max_participants'] !== '' ? (int)$data['max_participants'] : null,
            (int)$data['org_user_id'],
            $data['registration_type'] ?? 'free',
            $statusId,
        ]);
        return (int)$pdo->lastInsertId();
    }

    public static function updateById($id, $data) {
        $pdo = Database::getInstance();
        $allowed = [
            'event_date', 'event_time', 'event_type_id', 'title', 'description',
            'location', 'city', 'price', 'max_participants', 'registration_type', 'status_id',
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
        $stmt = $pdo->prepare('UPDATE events SET ' . implode(', ', $sets) . ' WHERE id = ?');
        return $stmt->execute($vals);
    }

    public static function updateStatus($eventId, $statusId) {
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('UPDATE events SET status_id = ?, updated_at = NOW() WHERE id = ?');
        return $stmt->execute([$statusId, $eventId]);
    }

    /**
     * Список или одно событие. Удалённые в списке не показываем.
     */
    public static function getAll($viewerId = null)
    {
        return self::fetchExpanded(null, $viewerId, true);
    }

    public static function findExpanded($id, $viewerId = null)
    {
        $rows = self::fetchExpanded((int)$id, $viewerId, false);
        return $rows[0] ?? null;
    }

    public static function countAll()
    {
        $pdo = Database::getInstance();
        return (int)$pdo->query('SELECT COUNT(*) FROM events')->fetchColumn();
    }

    private static function fetchExpanded($id, $viewerId, $hideDeleted)
    {
        $pdo = Database::getInstance();
        $sql = 'SELECT e.*,
                    et.code AS _et_code, et.name AS _et_name,
                    u.first_name_app AS _org_first, u.last_name_app AS _org_last, u.username AS _org_username,
                    u.telegram_photo_url AS _org_tg,
                    up.id AS _org_photo_id, up.url AS _org_photo_url,
                    s.code AS _st_code, s.name AS _st_name,
                    p.id AS photo_id, p.url AS photo_url, p.description AS photo_description
             FROM events e
             LEFT JOIN ref_event_types et ON e.event_type_id = et.id
             LEFT JOIN users u ON e.org_user_id = u.id
             LEFT JOIN ref_statuses s ON e.status_id = s.id
             LEFT JOIN photos p ON p.id = (
                 SELECT id FROM photos
                 WHERE entity_type = "event" AND entity_id = e.id
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
            $where[] = 'e.id = ?';
            $params[] = $id;
        }
        if ($hideDeleted) {
            $where[] = '(s.code IS NULL OR LOWER(s.code) NOT IN ("deleted","removed","удалён","удален"))';
        }
        if ($where) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY e.event_date DESC, e.event_time DESC, e.id DESC';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();
        $events = [];
        foreach ($rows as $row) {
            $events[] = self::hydrate($row, $viewerId);
        }
        $tally = LinkEventParticipant::tallyByEventIds(array_column($events, 'id'));
        foreach ($events as &$event) {
            $eid = (int)$event['id'];
            $counts = $tally[$eid] ?? ['going_count' => 0, 'maybe_count' => 0, 'no_count' => 0];
            $going = (int)$counts['going_count'];
            $maybe = (int)$counts['maybe_count'];
            $no = (int)$counts['no_count'];
            $max = isset($event['max_participants']) && $event['max_participants'] !== '' && $event['max_participants'] !== null
                ? (int)$event['max_participants'] : 0;
            $event['going_count'] = $going;
            $event['maybe_count'] = $maybe;
            $event['no_count'] = $no;
            $event['participants_count'] = $going;
            $event['spots_left'] = $max > 0 ? max(0, $max - $going) : null;
        }
        unset($event);
        // Имена ответивших грузим только в одной карточке, не в списке
        if ($id && $events) {
            $people = LinkEventParticipant::peopleForEvent((int)$id);
            $goingPeople = [];
            $maybePeople = [];
            $noPeople = [];
            foreach ($people as $p) {
                if (($p['confidence'] ?? '') === 'yes') {
                    $goingPeople[] = $p;
                } elseif (($p['confidence'] ?? '') === 'maybe') {
                    $maybePeople[] = $p;
                } elseif (($p['confidence'] ?? '') === 'no') {
                    $noPeople[] = $p;
                }
            }
            $events[0]['rsvp_going'] = $goingPeople;
            $events[0]['rsvp_maybe'] = $maybePeople;
            $events[0]['rsvp_no'] = $noPeople;
        }
        return $events;
    }

    private static function hydrate($row, $viewerId)
    {
        $event = $row;
        $event['event_type'] = [
            'id' => $row['event_type_id'],
            'code' => $row['_et_code'],
            'name' => $row['_et_name'],
        ];
        $event['organizer'] = $row['org_user_id'] ? [
            'id' => (int)$row['org_user_id'],
            'first_name' => $row['_org_first'],
            'last_name' => $row['_org_last'],
            'username' => $row['_org_username'],
            'photo' => User::photoFromJoin($row['_org_photo_id'] ?? null, $row['_org_photo_url'] ?? null, $row['_org_tg'] ?? null),
        ] : null;
        $event['status'] = [
            'id' => $row['status_id'],
            'code' => $row['_st_code'],
            'name' => $row['_st_name'],
        ];
        $event['photo'] = $row['photo_id'] ? [
            'id' => (int)$row['photo_id'],
            'url' => UrlHelper::buildUploadsUrlSized($row['photo_url'], 'orig'),
            'urls' => [
                'medium' => UrlHelper::buildUploadsUrlSized($row['photo_url'], 'medium'),
                'mini' => UrlHelper::buildUploadsUrlSized($row['photo_url'], 'mini'),
            ],
            'description' => $row['photo_description'],
        ] : null;
        unset(
            $event['_et_code'], $event['_et_name'],
            $event['_org_first'], $event['_org_last'], $event['_org_username'],
            $event['_org_tg'], $event['_org_photo_id'], $event['_org_photo_url'],
            $event['_st_code'], $event['_st_name'],
            $event['photo_id'], $event['photo_url'], $event['photo_description']
        );

        $eventId = (int)$row['id'];
        try {
            $event['my_rsvp'] = $viewerId ? LinkEventParticipant::findArray($eventId, (int)$viewerId) : null;
        } catch (Throwable $e) {
            $event['my_rsvp'] = null;
        }
        return $event;
    }
}
