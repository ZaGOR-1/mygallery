<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Цей файл запускається тільки з консолі.');
}

if ($argc < 2) {
    fwrite(STDERR, "Usage: php tools/verify_backup.php /path/to/backup.zip\n");
    exit(1);
}

$zipPath = $argv[1];

if (!is_file($zipPath)) {
    fwrite(STDERR, "Файл не знайдено: {$zipPath}\n");
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
$manifestExists = $zip->locateName('mygallery_backup/BACKUP_MANIFEST.json') !== false;

if (!$sqlExists) {
    fwrite(STDERR, "Помилка: файл database.sql не знайдено в архіві.\n");
    $zip->close();
    exit(1);
}

if (!$manifestExists) {
    fwrite(STDERR, "Помилка: файл BACKUP_MANIFEST.json не знайдено в архіві.\n");
    $zip->close();
    exit(1);
}

$manifestContent = $zip->getFromName('mygallery_backup/BACKUP_MANIFEST.json');
$manifest = json_decode($manifestContent, true);

if (!is_array($manifest)) {
    fwrite(STDERR, "Помилка: файл BACKUP_MANIFEST.json не є валідним JSON.\n");
    $zip->close();
    exit(1);
}

if (!isset($manifest['created_at'])) {
    fwrite(STDERR, "Помилка: у manifest відсутній created_at.\n");
    $zip->close();
    exit(1);
}

$expectedOriginals = (int) ($manifest['files']['storage_originals'] ?? 0);
$expectedLarge = (int) ($manifest['files']['public_large'] ?? 0);
$expectedThumbnails = (int) ($manifest['files']['public_thumbnails'] ?? 0);

function count_files_in_zip_dir(ZipArchive $zip, string $dir): int {
    $count = 0;
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        if ($stat === false) continue;
        $name = $stat['name'];
        if (str_starts_with($name, $dir) && !str_ends_with($name, '/')) {
            $count++;
        }
    }
    return $count;
}

$actualOriginals = count_files_in_zip_dir($zip, 'mygallery_backup/storage/originals/');
$actualLarge = count_files_in_zip_dir($zip, 'mygallery_backup/public/uploads/large/');
$actualThumbnails = count_files_in_zip_dir($zip, 'mygallery_backup/public/uploads/thumbnails/');

if ($actualOriginals < $expectedOriginals) {
    fwrite(STDERR, "Попередження: кількість оригіналів в архіві ($actualOriginals) менша за заявлену в manifest ($expectedOriginals).\n");
}

if ($actualLarge < $expectedLarge) {
    fwrite(STDERR, "Попередження: кількість large-версій в архіві ($actualLarge) менша за заявлену в manifest ($expectedLarge).\n");
}

if ($actualThumbnails < $expectedThumbnails) {
    fwrite(STDERR, "Попередження: кількість thumbnails в архіві ($actualThumbnails) менша за заявлену в manifest ($expectedThumbnails).\n");
}

echo "Перевірка backup архіву успішна.\n";
echo "Створено: {$manifest['created_at']}\n";
echo "Оригінали: {$actualOriginals} (expected: {$expectedOriginals})\n";
echo "Large: {$actualLarge} (expected: {$expectedLarge})\n";
echo "Thumbnails: {$actualThumbnails} (expected: {$expectedThumbnails})\n";

$zip->close();
exit(0);
