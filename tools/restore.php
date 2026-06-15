<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Цей файл запускається тільки з консолі.');
}

if ($argc < 2) {
    fwrite(STDERR, "Usage: php tools/restore.php /path/to/backup.zip\n");
    fwrite(STDERR, "УВАГА: Цей скрипт повністю замінить поточну базу даних і файли!\n");
    exit(1);
}

$zipPath = $argv[1];

if (!is_file($zipPath)) {
    fwrite(STDERR, "Файл не знайдено: {$zipPath}\n");
    exit(1);
}

echo "УВАГА: Ця дія незворотна! Вона видалить усі існуючі дані та замінить їх даними з резервної копії.\n";
echo "Щоб продовжити, введіть 'RESTORE': ";
$confirmation = trim((string) fgets(STDIN));
if ($confirmation !== 'RESTORE') {
    echo "Відмінено.\n";
    exit(1);
}

if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "Для роботи скрипта потрібне PHP-розширення zip.\n");
    exit(1);
}

$zip = new ZipArchive();
if ($zip->open($zipPath) !== true) {
    fwrite(STDERR, "Не вдалося відкрити ZIP архів: {$zipPath}\n");
    exit(1);
}

$sqlExists = $zip->locateName('mygallery_backup/database.sql') !== false;
if (!$sqlExists) {
    fwrite(STDERR, "Помилка: файл database.sql не знайдено в архіві.\n");
    $zip->close();
    exit(1);
}

$tmpSql = tempnam(sys_get_temp_dir(), 'mygallery_restore_');
$sqlContent = $zip->getFromName('mygallery_backup/database.sql');
file_put_contents($tmpSql, $sqlContent);

try {
    $pdo = db();
    $sql = file_get_contents($tmpSql);
    $pdo->exec($sql);
    echo "Базу даних успішно відновлено.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Помилка відновлення БД: " . $e->getMessage() . "\n");
    @unlink($tmpSql);
    $zip->close();
    exit(1);
}
@unlink($tmpSql);

function clean_directory(string $dir): void {
    if (!is_dir($dir)) return;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $fileinfo) {
        $filename = $fileinfo->getFilename();
        if ($filename === '.gitkeep' || $filename === '.htaccess') continue;
        
        $path = $fileinfo->getRealPath();
        if ($fileinfo->isDir()) {
            @rmdir($path);
        } else {
            @unlink($path);
        }
    }
}

echo "Очищення поточних медіа-файлів...\n";
clean_directory(originals_path());
clean_directory(uploads_path('large'));
clean_directory(uploads_path('thumbnails'));

echo "Розпакування медіа-файлів...\n";
$extractedCount = 0;
for ($i = 0; $i < $zip->numFiles; $i++) {
    $stat = $zip->statIndex($i);
    if ($stat === false) continue;
    $name = $stat['name'];
    
    // Zapobigannya Zip Slip (Path Traversal)
    if (str_contains($name, '..') || str_starts_with($name, '/')) {
        continue;
    }
    
    $targetPath = null;
    if (str_starts_with($name, 'mygallery_backup/storage/originals/') && !str_ends_with($name, '/')) {
        $relPath = substr($name, strlen('mygallery_backup/storage/originals/'));
        $targetPath = originals_path($relPath);
    } elseif (str_starts_with($name, 'mygallery_backup/public/uploads/large/') && !str_ends_with($name, '/')) {
        $relPath = substr($name, strlen('mygallery_backup/public/uploads/large/'));
        $targetPath = uploads_path('large', $relPath);
    } elseif (str_starts_with($name, 'mygallery_backup/public/uploads/thumbnails/') && !str_ends_with($name, '/')) {
        $relPath = substr($name, strlen('mygallery_backup/public/uploads/thumbnails/'));
        $targetPath = uploads_path('thumbnails', $relPath);
    }

    if ($targetPath !== null) {
        $dir = dirname($targetPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $content = $zip->getFromIndex($i);
        file_put_contents($targetPath, $content);
        $extractedCount++;
    }
}

$zip->close();
echo "Відновлено файлів: {$extractedCount}.\n";
echo "Відновлення успішно завершено!\n";
