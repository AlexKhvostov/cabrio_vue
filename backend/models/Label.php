<?php
/**
 * Ярлык (хештег) — короткое слово без #, например «мойка».
 * Одно слово хранится один раз; к месту цепляется через link_guide_object_labels.
 */
require_once __DIR__ . '/../utils/Database.php';

class Label
{
    /** Сколько ярлыков можно повесить на одно место */
    public const MAX_PER_OBJECT = 12;

    /**
     * Привести ввод к коду: без #, без пробелов, маленькими буквами.
     */
    public static function normalize($raw)
    {
        $s = trim((string)$raw);
        $s = preg_replace('/^#+/u', '', $s);
        $s = preg_replace('/\s+/u', '', $s);
        if (function_exists('mb_strtolower')) {
            $s = mb_strtolower($s, 'UTF-8');
        } else {
            $s = strtolower($s);
        }
        $s = preg_replace('/[^\p{L}\p{N}_-]+/u', '', $s);
        if (function_exists('mb_substr')) {
            $s = mb_substr($s, 0, 80, 'UTF-8');
        } else {
            $s = substr($s, 0, 80);
        }
        return $s;
    }

    /**
     * Список всех ярлыков для подсказок в форме.
     */
    public static function getAll()
    {
        $pdo = Database::getInstance();
        $rows = $pdo->query('SELECT id, code, name FROM labels ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
        return $rows ?: [];
    }

    /**
     * Найти или создать ярлык по тексту, который ввёл человек.
     */
    public static function findOrCreate($raw)
    {
        $code = self::normalize($raw);
        if ($code === '') {
            return null;
        }
        $pdo = Database::getInstance();
        $stmt = $pdo->prepare('SELECT id, code, name FROM labels WHERE code = ? LIMIT 1');
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row;
        }
        $ins = $pdo->prepare('INSERT INTO labels (code, name) VALUES (?, ?)');
        $ins->execute([$code, $code]);
        return [
            'id' => (int)$pdo->lastInsertId(),
            'code' => $code,
            'name' => $code,
        ];
    }

    /**
     * Заменить набор ярлыков у места. $rawList — массив строк или объектов {code,name}.
     */
    public static function syncForGuideObject($guideObjectId, $rawList)
    {
        $guideObjectId = (int)$guideObjectId;
        $pdo = Database::getInstance();
        $pdo->prepare('DELETE FROM link_guide_object_labels WHERE guide_object_id = ?')->execute([$guideObjectId]);
        if (!is_array($rawList) || !$rawList) {
            return [];
        }
        $seen = [];
        $attached = [];
        foreach ($rawList as $item) {
            if (count($attached) >= self::MAX_PER_OBJECT) {
                break;
            }
            $raw = is_array($item) ? ($item['code'] ?? $item['name'] ?? '') : $item;
            $row = self::findOrCreate($raw);
            if (!$row || isset($seen[$row['code']])) {
                continue;
            }
            $seen[$row['code']] = true;
            $pdo->prepare('INSERT INTO link_guide_object_labels (guide_object_id, label_id) VALUES (?, ?)')
                ->execute([$guideObjectId, (int)$row['id']]);
            $attached[] = $row;
        }
        return $attached;
    }

    /**
     * Дописать поле labels[] к списку мест одним запросом.
     */
    public static function attachToList(array &$items)
    {
        foreach ($items as &$it) {
            $it['labels'] = [];
        }
        unset($it);
        if (!$items) {
            return;
        }
        $ids = [];
        foreach ($items as $it) {
            if (!empty($it['id'])) {
                $ids[] = (int)$it['id'];
            }
        }
        if (!$ids) {
            return;
        }
        try {
            $pdo = Database::getInstance();
            $in = implode(',', array_fill(0, count($ids), '?'));
            $sql = "SELECT lgl.guide_object_id, l.id, l.code, l.name
                    FROM link_guide_object_labels lgl
                    INNER JOIN labels l ON l.id = lgl.label_id
                    WHERE lgl.guide_object_id IN ($in)
                    ORDER BY l.name";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($ids);
            $byGo = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $gid = (int)$row['guide_object_id'];
                $byGo[$gid][] = [
                    'id' => (int)$row['id'],
                    'code' => $row['code'],
                    'name' => $row['name'],
                ];
            }
            foreach ($items as &$it) {
                $it['labels'] = $byGo[(int)$it['id']] ?? [];
            }
            unset($it);
        } catch (Throwable $e) {
            // Таблиц ещё нет — места всё равно отдаём, ярлыки пустые
        }
    }
}
