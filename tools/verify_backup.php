<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'BackupArchiveValidator.php';

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

try {
    $validated = backup_validate_archive(
        $zip,
        (int) app_config()['RESTORE_MAX_UNCOMPRESSED_BYTES'],
        (int) app_config()['RESTORE_MAX_COMPRESSION_RATIO']
    );
} catch (Throwable $exception) {
    fwrite(STDERR, "Backup не пройшов перевірку: " . $exception->getMessage() . "\n");
    $zip->close();
    exit(1);
}

$zip->close();
$manifest = $validated['manifest'];

echo "Перевірка backup архіву успішна.\n";
echo "Формат: " . $manifest['format_version'] . "\n";
echo "Створено: " . $manifest['created_at'] . "\n";
echo "Оригінали: " . count($validated['media_entries']['storage_originals']) . "\n";
echo "Large: " . count($validated['media_entries']['public_large']) . "\n";
echo "Thumbnails: " . count($validated['media_entries']['public_thumbnails']) . "\n";
echo "Усі дозволені ZIP streams, розміри та SHA-256 збігаються з manifest.\n";
exit(0);
