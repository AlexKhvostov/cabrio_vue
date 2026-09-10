<?php
/**
 * Журнал действий Mini App — кто, когда, что сделал.
 * Пишем в таблицу app_audit_logs. Ошибка записи приложение не ломает.
 */
require_once __DIR__ . '/Database.php';
require_once __DIR__ . '/Logger.php';
require_once __DIR__ . '/AppContext.php';
require_once __DIR__ . '/../../config/sectionGroups.php';

class AppAudit
{
    private const ROLE_NAMES = [
        'external' => 'Внешний',
        'guest' => 'Гость',
        'new' => 'Новый',
        'registered' => 'Зарегистрирован',
        'user' => 'Пользователь',
        'member' => 'Участник',
        'moderator' => 'Модератор',
        'admin' => 'Администратор',
    ];

    /** Записать событие. $throttleMin — не чаще раза в N минут для таких же кто+действие+раздел */
    public static function write(array $row, int $throttleMin = 0): void
    {
        try {
            $user = AppContext::getCurrentUser();
            $userId = $row['user_id'] ?? ($user['id'] ?? null);
            $role = self::roleCode($user);
            $name = self::actorName($user);
            $action = (string)($row['action'] ?? '');
            $entityType = (string)($row['entity_type'] ?? '');
            $entityId = isset($row['entity_id']) ? (int)$row['entity_id'] : null;
            $section = (string)($row['section'] ?? '');
            $summary = mb_substr((string)($row['summary'] ?? ''), 0, 500);
            if ($action === '') {
                return;
            }
            $pdo = Database::getInstance();
            if ($throttleMin > 0) {
                $stmt = $pdo->prepare(
                    'SELECT id FROM app_audit_logs
                     WHERE user_id <=> ? AND action = ? AND section = ? AND entity_type = ?
                     AND created_at >= (UTC_TIMESTAMP() - INTERVAL ? MINUTE)
                     LIMIT 1'
                );
                $stmt->execute([$userId ?: null, $action, $section, $entityType, $throttleMin]);
                if ($stmt->fetch()) {
                    return;
                }
            }
            $ins = $pdo->prepare(
                'INSERT INTO app_audit_logs
                 (user_id, actor_name, actor_role, action, entity_type, entity_id, section, summary, created_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())'
            );
            $ins->execute([
                $userId ? (int)$userId : null,
                $name,
                $role,
                $action,
                $entityType,
                $entityId ?: null,
                $section,
                $summary,
            ]);
        } catch (Throwable $e) {
            Logger::error('AppAudit write failed: ' . $e->getMessage());
        }
    }

    public static function roleName(string $code): string
    {
        $c = strtolower($code);
        return self::ROLE_NAMES[$c] ?? ($code !== '' ? $code : '—');
    }

    public static function actionName(string $code): string
    {
        return [
            'create' => 'Создание',
            'update' => 'Правка',
            'delete' => 'Удаление',
            'login' => 'Вход',
            'view' => 'Раздел',
        ][$code] ?? $code;
    }

    public static function entityName(string $code): string
    {
        return [
            'car' => 'Авто',
            'event' => 'Событие',
            'place' => 'Место',
            'guide_object' => 'Место',
            'review' => 'Отзыв',
            'photo' => 'Фото',
            'user' => 'Человек',
            'app' => 'Приложение',
            'page' => 'Экран',
        ][$code] ?? $code;
    }

    public static function sectionName(string $code): string
    {
        return [
            'home' => 'Главная',
            'users' => 'Участники',
            'cars' => 'Авто',
            'map' => 'Карта',
            'events' => 'События',
            'guide' => 'Отзывы',
            'me' => 'Профиль',
        ][$code] ?? ($code !== '' ? $code : '—');
    }

    public static function list(array $filters, int $limit = 80, int $offset = 0): array
    {
        $pdo = Database::getInstance();
        $where = ['1=1'];
        $bind = [];
        if (!empty($filters['action'])) {
            $where[] = 'action = ?';
            $bind[] = $filters['action'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(actor_name LIKE ? OR summary LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            $bind[] = $like;
            $bind[] = $like;
        }
        $sqlWhere = implode(' AND ', $where);
        $cnt = $pdo->prepare("SELECT COUNT(*) FROM app_audit_logs WHERE $sqlWhere");
        $cnt->execute($bind);
        $total = (int)$cnt->fetchColumn();
        $stmt = $pdo->prepare(
            "SELECT * FROM app_audit_logs WHERE $sqlWhere ORDER BY id DESC LIMIT " . (int)$limit . ' OFFSET ' . (int)$offset
        );
        $stmt->execute($bind);
        return ['rows' => $stmt->fetchAll() ?: [], 'total' => $total];
    }

    private static function roleCode($user): string
    {
        if (!$user || !is_array($user)) {
            return '';
        }
        if (isset($user['role']['code'])) {
            return (string)$user['role']['code'];
        }
        if (isset($user['role']) && is_string($user['role'])) {
            return $user['role'];
        }
        if (isset($user['role_id'])) {
            return (string)Roles::getRoleById((int)$user['role_id']);
        }
        return '';
    }

    private static function actorName($user): string
    {
        if (!$user || !is_array($user)) {
            return 'неизвестный';
        }
        $first = trim((string)($user['first_name_app'] ?? $user['first_name'] ?? $user['first_name_tg'] ?? ''));
        $last = trim((string)($user['last_name_app'] ?? $user['last_name'] ?? $user['last_name_tg'] ?? ''));
        $name = trim($first . ' ' . $last);
        if ($name !== '') {
            return mb_substr($name, 0, 190);
        }
        if (!empty($user['username'])) {
            return '@' . $user['username'];
        }
        return 'id ' . ($user['id'] ?? '?');
    }
}
