<?php
/**
 * UrlHelper — формирование абсолютных URL для файлов из папки uploads
 */

require_once __DIR__ . '/load_env.php';

class UrlHelper {
    public static function getUploadsBaseUrl(): string {
        $base = getenv('UPLOADS_BASE_URL');
        if (!$base || trim($base) === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
            $base = $scheme . '://' . $host . '/app/uploads';
        }
        return rtrim($base, '/');
    }

    /**
     * Вернёт базовый URL для указанного размера: orig | medium | mini
     */
    public static function getUploadsBaseUrlWithSize(string $size = 'orig'): string {
        $size = in_array($size, ['orig','medium','mini'], true) ? $size : 'orig';
        return self::getUploadsBaseUrl() . '/' . $size;
    }

    /**
     * Нормализовать путь из БД к виду без префикса uploads/ и без префикса размера (orig/medium/mini).
     * Примеры входа:
     *  - "/uploads/user/user_1_1.jpg" → "user/user_1_1.jpg"
     *  - "uploads/orig/car/car_2_3.jpg" → "car/car_2_3.jpg"
     *  - "car/car_2_3.jpg" → "car/car_2_3.jpg"
     */
    public static function normalizeDbPath(?string $dbUrl): ?string {
        if ($dbUrl === null || trim($dbUrl) === '') return null;
        $path = ltrim(trim($dbUrl), '/');
        if (stripos($path, 'uploads/') === 0) {
            $path = substr($path, strlen('uploads/'));
        }
        // Срезаем возможные префиксы размера
        foreach (['orig/','medium/','mini/'] as $prefix) {
            if (stripos($path, $prefix) === 0) {
                $path = substr($path, strlen($prefix));
                break;
            }
        }
        return $path;
    }

    /**
     * Склеить базовый URL и значение из БД (photos.url).
     * Поддерживает варианты: "/uploads/...", "uploads/...", "user/...", "car/...".
     * Если уже абсолютный URL — возвращает как есть.
     */
    public static function buildUploadsUrl(?string $dbUrl): ?string {
        if ($dbUrl === null || $dbUrl === '') return null;
        $trimmed = trim($dbUrl);
        if (preg_match('/^https?:\/\//i', $trimmed)) return $trimmed;
        $path = self::normalizeDbPath($trimmed) ?? '';
        // По умолчанию считаем, что нужен оригинал
        return self::getUploadsBaseUrlWithSize('orig') . '/' . $path;
    }

    /**
     * Построить URL для конкретного размера превью: orig | medium | mini
     */
    public static function buildUploadsUrlSized(?string $dbUrl, string $size = 'orig'): ?string {
        if ($dbUrl === null || $dbUrl === '') return null;
        $trimmed = trim($dbUrl);
        if (preg_match('/^https?:\/\//i', $trimmed)) return $trimmed;
        $path = self::normalizeDbPath($trimmed) ?? '';
        $size = in_array($size, ['orig','medium','mini'], true) ? $size : 'orig';

        // Нет превью (часто HEIC/ошибка GD) — отдаём оригинал, иначе в приложении пустая рамка
        if ($size !== 'orig') {
            $onDisk = self::toAbsoluteUploadsPath($path, $size);
            if (!is_file($onDisk)) {
                $size = 'orig';
            }
        }
        return self::getUploadsBaseUrlWithSize($size) . '/' . $path;
    }

    /**
     * Абсолютный путь на ФС до файла в uploads/{size}/{path}
     */
    private static function toAbsoluteUploadsPath(string $relativePath, string $size = 'orig'): string {
        $size = in_array($size, ['orig','medium','mini'], true) ? $size : 'orig';
        $baseDir = __DIR__ . '/../../..'; // до корня проекта
        $relativePath = ltrim($relativePath, '/');
        return $baseDir . '/uploads/' . $size . '/' . $relativePath;
    }

    private static function toAbsoluteUploadsDir(string $size = 'orig'): string {
        $size = in_array($size, ['orig','medium','mini'], true) ? $size : 'orig';
        $baseDir = __DIR__ . '/../../..';
        return $baseDir . '/uploads/' . $size;
    }
}


