<?php
/**
 * TelegramAvatarHelper — получение аватаров пользователей из Telegram
 */

require_once __DIR__ . '/../../utils/load_env.php';
require_once __DIR__ . '/../../utils/Logger.php';
require_once __DIR__ . '/../level1/_CreatePhotoAction.php';
require_once __DIR__ . '/FileHelper.php';
require_once __DIR__ . '/../../models/Photo.php';
require_once __DIR__ . '/../../models/User.php';

class TelegramAvatarHelper {
    
    /**
     * Попытаться захватить файловый лок на загрузку аватара пользователя
     * Возвращает дескриптор файла при успехе, иначе null
     */
    private static function acquireLock($userId) {
        try {
            $lockDir = __DIR__ . '/../../logs/locks';
            if (!is_dir($lockDir)) {
                @mkdir($lockDir, 0755, true);
            }
            $lockPath = $lockDir . "/avatar_{$userId}.lock";
            $fh = @fopen($lockPath, 'c');
            if (!$fh) {
                return null;
            }
            // Неблокирующая попытка захватить эксклюзивный лок
            if (!@flock($fh, LOCK_EX | LOCK_NB)) {
                // Уже обрабатывается другим процессом — не дублируем
                @fclose($fh);
                return null;
            }
            return $fh;
        } catch (Exception $e) {
            return null;
        }
    }

    /**
     * Освободить файловый лок
     */
    private static function releaseLock($fh) {
        if ($fh) {
            @flock($fh, LOCK_UN);
            @fclose($fh);
        }
    }

    /**
     * Получить аватар пользователя из Telegram
     */
    public static function getUserAvatar($telegramId, $userId) {
        try {
            // Не спрашиваем Telegram на каждый API-запрос: иначе списки и разделы «висят» секундами.
            $existing = self::getExistingAvatar($userId);
            if ($existing) {
                return $existing;
            }
            if (self::checkedRecently($userId)) {
                return null;
            }
            self::markChecked($userId);

            // Защита от параллельных скачиваний одного и того же аватара
            $lock = self::acquireLock($userId);
            if ($lock === null) {
                Logger::info("TelegramAvatarHelper: Skip duplicate avatar fetch (locked)", ['user_id' => $userId]);
                return self::getExistingAvatar($userId);
            }
            $botToken = getenv('BOT_TOKEN');
            if (!$botToken) {
                Logger::warning('TelegramAvatarHelper: BOT_TOKEN not found');
                self::releaseLock($lock);
                return null;
            }
            
            // 1. Получаем информацию о пользователе
            $userInfo = self::getUserInfo($botToken, $telegramId);
            if (!$userInfo || !isset($userInfo['photo'])) {
                Logger::info("TelegramAvatarHelper: No avatar for telegram_id=$telegramId");
                self::releaseLock($lock);
                return null;
            }
            
            // 2. Получаем file_id аватара
            $fileId = self::getFileId($userInfo['photo']);
            if (!$fileId) {
                Logger::info("TelegramAvatarHelper: No file_id for telegram_id=$telegramId");
                self::releaseLock($lock);
                return null;
            }
            
            Logger::info("TelegramAvatarHelper: Found avatar file_id=$fileId");
            
            // 3. Проверяем, изменился ли аватар
            $currentUser = User::findById($userId);
            if ($currentUser && $currentUser->telegram_photo_id === $fileId) {
                Logger::info("TelegramAvatarHelper: Avatar unchanged for user_id=$userId");
                self::releaseLock($lock);
                return self::getExistingAvatar($userId);
            }
            
            // 4. Получаем file_path
            $filePath = self::getFilePath($botToken, $fileId);
            if (!$filePath) {
                Logger::warning("TelegramAvatarHelper: Failed to get file_path for file_id=$fileId");
                self::releaseLock($lock);
                return null;
            }
            
            // 5. Скачиваем файл
            $imageData = self::downloadFile($botToken, $filePath);
            if (!$imageData) {
                Logger::warning("TelegramAvatarHelper: Failed to download file");
                self::releaseLock($lock);
                return null;
            }
            
            // 6. Сохраняем аватар
            $avatarData = self::saveAvatar($imageData, $userId, $filePath);
            if (!$avatarData) {
                Logger::warning("TelegramAvatarHelper: Failed to save avatar");
                self::releaseLock($lock);
                return null;
            }
            
            // 7. Обновляем ссылку в БД
            self::updateAvatarLink($userId, $fileId);
            
            Logger::info("TelegramAvatarHelper: Avatar saved for user_id=$userId");
            self::releaseLock($lock);
            return $avatarData;
            
        } catch (Exception $e) {
            Logger::error('TelegramAvatarHelper: Error', [
                'telegram_id' => $telegramId,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }
    
    /** Не чаще раза в сутки, если аватара ещё нет */
    private static function checkedPath($userId): string
    {
        return rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'cabrio_avatar_chk_' . (int)$userId;
    }

    private static function checkedRecently($userId): bool
    {
        $path = self::checkedPath($userId);
        if (!is_file($path)) {
            return false;
        }
        return (time() - (int)@filemtime($path)) < 86400;
    }

    private static function markChecked($userId): void
    {
        @file_put_contents(self::checkedPath($userId), (string)time());
    }

    /** Короткий запрос к api.telegram.org, без минуты ожидания PHP */
    private static function telegramGet(string $url): ?string
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => 2,
                CURLOPT_TIMEOUT => 4,
            ]);
            $raw = curl_exec($ch);
            curl_close($ch);
            return ($raw !== false && $raw !== '') ? $raw : null;
        }
        $ctx = stream_context_create(['http' => ['timeout' => 4, 'ignore_errors' => true]]);
        $raw = @file_get_contents($url, false, $ctx);
        return ($raw !== false && $raw !== '') ? $raw : null;
    }

    /**
     * Получить информацию о пользователе
     */
    private static function getUserInfo($botToken, $telegramId) {
        $url = "https://api.telegram.org/bot{$botToken}/getChat?chat_id={$telegramId}";
        $response = self::telegramGet($url);
        
        if (!$response) return null;
        
        $data = json_decode($response, true);
        return ($data && $data['ok']) ? $data['result'] : null;
    }
    
    /**
     * Извлечь file_id из данных фото
     */
    private static function getFileId($photoData) {
        if (isset($photoData['big_file_id'])) {
            return $photoData['big_file_id']; // Лучшее качество
        }
        if (isset($photoData['small_file_id'])) {
            return $photoData['small_file_id']; // Fallback
        }
        return null;
    }
    
    /**
     * Получить file_path
     */
    private static function getFilePath($botToken, $fileId) {
        $url = "https://api.telegram.org/bot{$botToken}/getFile?file_id={$fileId}";
        $response = self::telegramGet($url);
        
        if (!$response) return null;
        
        $data = json_decode($response, true);
        return ($data && $data['ok']) ? $data['result']['file_path'] : null;
    }
    
    /**
     * Скачать файл
     */
    private static function downloadFile($botToken, $filePath) {
        $url = "https://api.telegram.org/file/bot{$botToken}/{$filePath}";
        return self::telegramGet($url);
    }
    
    /**
     * Сохранить аватар
     */
    private static function saveAvatar($imageData, $userId, $filePath) {
        try {
            $extension = pathinfo($filePath, PATHINFO_EXTENSION) ?: 'jpg';
            $photoId = Photo::getNextId();
            $fileName = FileHelper::generateCorrectFileName('user', $userId, $photoId, $extension);
            
            // Конвертируем бинарные данные в base64
            $base64Data = base64_encode($imageData);
            
            // Сохраняем файл используя savePhotoFromBase64
            $savedPath = FileHelper::savePhotoFromBase64($base64Data, 'user', $userId, $photoId, $fileName);
            
            if (!$savedPath) return null;
            
            // Создаем запись в БД
            $result = _CreatePhotoAction::handle([
                'entity_type' => 'user',
                'entity_id' => $userId,
                'file_name' => $fileName,
                'url' => $savedPath,
                'photo_type' => 'avatar',
                'description' => 'Аватар из Telegram',
                'uploaded_by' => $userId
            ]);
            
            return $result['success'] ? $result['data'] : null;
            
        } catch (Exception $e) {
            Logger::error('TelegramAvatarHelper: Save error', ['error' => $e->getMessage()]);
            return null;
        }
    }
    
    /**
     * Получить существующий аватар
     */
    private static function getExistingAvatar($userId) {
        try {
            $pdo = \Database::getInstance();
            $stmt = $pdo->prepare('SELECT * FROM photos WHERE entity_type = "user" AND entity_id = ? AND photo_type = "avatar" ORDER BY id DESC LIMIT 1');
            $stmt->execute([$userId]);
            return $stmt->fetch();
        } catch (Exception $e) {
            return null;
        }
    }
    
    /**
     * Обновить ссылку на аватар в БД
     */
    private static function updateAvatarLink($userId, $fileId) {
        try {
            $pdo = \Database::getInstance();
            $stmt = $pdo->prepare('UPDATE users SET telegram_photo_id = ?, updated_at = NOW() WHERE id = ?');
            $stmt->execute([$fileId, $userId]);
        } catch (Exception $e) {
            Logger::error('TelegramAvatarHelper: Update error', ['error' => $e->getMessage()]);
        }
    }
} 