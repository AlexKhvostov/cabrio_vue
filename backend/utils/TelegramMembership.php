<?php
/**
 * Спрашивает Telegram: человек сейчас в клубном чате?
 *
 * Важно: «бот не видит чат» ≠ «человека нет в группе».
 * Иначе администратор тоже получает заглушку.
 */
class TelegramMembership
{
    private const CACHE_TTL = 1800;
    private const CACHE_VER = 'v4';

    public static function check(int $telegramId, bool $force = false): array
    {
        if ($telegramId <= 0) {
            return ['success' => false, 'error' => 'Нет Telegram ID'];
        }

        if (!$force) {
            $cached = self::readCache($telegramId);
            if ($cached !== null) {
                return $cached;
            }
        }

        $token = trim((string)($_ENV['BOT_TOKEN'] ?? (getenv('BOT_TOKEN') ?: '')));
        $chatIds = self::chatIds((string)($_ENV['CLUB_CHAT_ID'] ?? (getenv('CLUB_CHAT_ID') ?: '')));

        if ($token === '' || !$chatIds) {
            return [
                'success' => false,
                'error' => 'На сервере не настроены BOT_TOKEN или CLUB_CHAT_ID',
            ];
        }

        $notInAll = true;
        $lastError = '';

        foreach ($chatIds as $chatId) {
            $raw = self::askTelegram($token, $chatId, $telegramId);
            if ($raw === false || $raw === '') {
                $lastError = 'Telegram API временно недоступен';
                $notInAll = false;
                continue;
            }

            $response = json_decode($raw, true);
            if (!is_array($response)) {
                $lastError = 'Некорректный ответ Telegram API';
                $notInAll = false;
                continue;
            }

            if (!empty($response['ok'])) {
                $member = $response['result'] ?? [];
                $status = (string)($member['status'] ?? '');
                if (self::statusMeansInChat($status, $member)) {
                    $pack = [
                        'success' => true,
                        'is_member' => true,
                        'status' => $status,
                    ];
                    self::writeCache($telegramId, $pack);
                    return $pack;
                }
                // left / kicked — в этом чате нет, смотрим следующий
                continue;
            }

            $description = (string)($response['description'] ?? 'Отказ Telegram API');
            $lastError = $description;
            if (self::isNotInChatError($description)) {
                continue;
            }
            // Бот не в чате, неверный id, нет прав — это не «гость вне группы».
            $notInAll = false;
        }

        if ($notInAll) {
            $pack = [
                'success' => true,
                'is_member' => false,
                'status' => 'left',
            ];
            self::writeCache($telegramId, $pack);
            return $pack;
        }

        return [
            'success' => false,
            'error' => $lastError !== '' ? $lastError : 'Telegram API временно недоступен',
        ];
    }

    private static function statusMeansInChat(string $status, array $member): bool
    {
        if (in_array($status, ['creator', 'administrator', 'member'], true)) {
            return true;
        }
        return $status === 'restricted' && !empty($member['is_member']);
    }

    private static function isNotInChatError(string $description): bool
    {
        $t = strtolower($description);
        foreach (['user not found', 'user_not_participant', 'participant_id_invalid', 'member not found'] as $needle) {
            if (str_contains($t, $needle)) {
                return true;
            }
        }
        return false;
    }

    private static function askTelegram(string $token, string $chatId, int $telegramId)
    {
        $url = 'https://api.telegram.org/bot' . $token . '/getChatMember';
        $fields = http_build_query([
            'chat_id' => $chatId,
            'user_id' => $telegramId,
        ]);

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $fields,
                CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_TIMEOUT => 6,
            ]);
            $raw = curl_exec($ch);
            curl_close($ch);
            if ($raw !== false && $raw !== '') {
                return $raw;
            }
        }

        $getUrl = $url . '?' . $fields;
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 6,
                'ignore_errors' => true,
            ],
        ]);
        return @file_get_contents($getUrl, false, $context);
    }

    private static function cachePath(int $telegramId): string
    {
        return rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'cabrio_member_' . self::CACHE_VER . '_' . $telegramId . '.json';
    }

    private static function readCache(int $telegramId): ?array
    {
        $path = self::cachePath($telegramId);
        if (!is_file($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        $data = json_decode((string)$raw, true);
        if (!is_array($data) || empty($data['at']) || !isset($data['result']) || !is_array($data['result'])) {
            return null;
        }
        if ((time() - (int)$data['at']) > self::CACHE_TTL) {
            return null;
        }
        return $data['result'];
    }

    private static function writeCache(int $telegramId, array $result): void
    {
        @file_put_contents(self::cachePath($telegramId), json_encode([
            'at' => time(),
            'result' => $result,
        ], JSON_UNESCAPED_UNICODE));
    }

    /** Все id из CLUB_CHAT_ID, без кавычек и комментариев в конце строки. */
    private static function chatIds(string $csv): array
    {
        $ids = [];
        $csv = trim(explode('#', $csv, 2)[0]);
        foreach (explode(',', $csv) as $part) {
            $id = trim($part, " \t\"'");
            if ($id === '') {
                continue;
            }
            if (preg_match('/^-?\d+$/', $id)) {
                $ids[] = $id;
                continue;
            }
            if (preg_match('#(?:https?://)?t\.me/([A-Za-z][A-Za-z0-9_]{4,})#', $id, $m)) {
                $ids[] = '@' . $m[1];
                continue;
            }
            if (preg_match('/^@?[A-Za-z][A-Za-z0-9_]{4,}$/', $id)) {
                $ids[] = str_starts_with($id, '@') ? $id : ('@' . $id);
            }
        }
        return array_values(array_unique($ids));
    }
}
