<?php
// Миграция файлов в каталоги uploads/{orig|medium|mini} и генерация превью

require_once __DIR__ . '/../actions/helpers/FileHelper.php';
require_once __DIR__ . '/../utils/Logger.php';

// Настройки
$entities = ['user','car','business_card','event','guide_object'];
$root = realpath(__DIR__ . '/../../uploads');
if ($root === false) {
    echo "uploads/ not found\n";
    exit(1);
}

$moved = 0; $generated = 0; $skipped = 0;

foreach ($entities as $entity) {
    $legacyDir = $root . DIRECTORY_SEPARATOR . $entity; // старое расположение
    $origDir   = $root . DIRECTORY_SEPARATOR . 'orig' . DIRECTORY_SEPARATOR . $entity;
    if (!is_dir($legacyDir) && !is_dir($origDir)) {
        // Нечего мигрировать
        continue;
    }

    if (!is_dir($origDir)) {
        @mkdir($origDir, 0755, true);
    }

    // Сканируем старые файлы в uploads/{entity}
    if (is_dir($legacyDir)) {
        $files = array_diff(scandir($legacyDir) ?: [], ['.','..']);
        foreach ($files as $file) {
            $src = $legacyDir . DIRECTORY_SEPARATOR . $file;
            if (!is_file($src)) { $skipped++; continue; }
            // Перемещаем в orig/{entity}
            $dst = $origDir . DIRECTORY_SEPARATOR . $file;
            if (@rename($src, $dst)) {
                $moved++;
                // Генерируем превью
                try {
                    FileHelper::generateThumbnails($dst, $entity, $file);
                    $generated++;
                } catch (Exception $e) {
                    Logger::warning('Migration thumbs error: ' . $e->getMessage());
                }
            } else {
                $skipped++;
            }
        }
        // Пытаемся удалить пустую папку legacy
        @rmdir($legacyDir);
    }

    // Пройдёмся по уже лежащим в orig/{entity} и убедимся, что превью созданы
    $files = array_diff(scandir($origDir) ?: [], ['.','..']);
    foreach ($files as $file) {
        $origPath = $origDir . DIRECTORY_SEPARATOR . $file;
        if (!is_file($origPath)) { continue; }
        // Проверим наличие превью и сгенерируем при отсутствии
        $mediumPath = FileHelper::getUploadDir($entity, 'medium') . DIRECTORY_SEPARATOR . $file;
        $miniPath   = FileHelper::getUploadDir($entity, 'mini')   . DIRECTORY_SEPARATOR . $file;
        $needGen = (!file_exists($mediumPath) || !file_exists($miniPath));
        if ($needGen) {
            try {
                FileHelper::generateThumbnails($origPath, $entity, $file);
                $generated++;
            } catch (Exception $e) {
                Logger::warning('Migration thumbs regen error: ' . $e->getMessage());
            }
        }
    }
}

echo "Done. moved={$moved} generated={$generated} skipped={$skipped}\n";


