<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'BackupArchiveValidator.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'SafeCliZipOutput.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Цей файл запускається тільки з консолі.');
}

$options = getopt('', ['include-config', 'output:', 'help']);
if (isset($options['help'])) {
    echo "Usage:\n";
    echo "  php tools/backup.php\n";
    echo "  php tools/backup.php --include-config\n";
    echo "  php tools/backup.php --output=/path/to/backup.zip\n";
    exit(0);
}

if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "Для створення й обов'язкової перевірки backup потрібне PHP-розширення zip.\n");
    exit(1);
}

$root = dirname(__DIR__);
$backupDir = $root . DIRECTORY_SEPARATOR . 'backups';
$timestamp = date('Ymd_His');
$output = $backupDir . DIRECTORY_SEPARATOR . 'mygallery_backup_' . $timestamp . '.zip';
$includeConfig = isset($options['include-config']);
$customOutput = false;

if (isset($options['output']) && is_string($options['output']) && $options['output'] !== '') {
    $output = $options['output'];
    $customOutput = true;
}

if (!$customOutput && !is_dir($backupDir) && !mkdir($backupDir, 0700, true)) {
    fwrite(STDERR, "Не вдалося створити папку backups.\n");
    exit(1);
}
if (!$customOutput && PHP_OS_FAMILY !== 'Windows') {
    if (!chmod($backupDir, 0700)) {
        fwrite(STDERR, "Не вдалося встановити права 0700 для папки backups.\n");
        exit(1);
    }
    clearstatcache(true, $backupDir);
    $backupPermissions = fileperms($backupDir);
    if ($backupPermissions === false || (($backupPermissions & 0777) & 0077) !== 0) {
        fwrite(STDERR, "Папка backups доступна group/other; backup скасовано.\n");
        exit(1);
    }
}

function backup_sql_value(mixed $value): string
{
    if ($value === null) {
        return 'NULL';
    }

    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }

    return db()->quote((string) $value);
}

function export_table_sql(PDO $pdo, string $table): string
{
    $stmt = $pdo->query('SELECT * FROM `' . str_replace('`', '``', $table) . '`');
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    // Без `LOCK TABLES`: у MySQL вони роблять неявний COMMIT і зробили б застосування
    // дампу в `tools/restore.php` неатомарним. Чистий DML дозволяє відновлювати БД
    // однією транзакцією.
    $sql = "-- Table: `{$table}`\n";
    $sql .= "DELETE FROM `{$table}`;\n";

    foreach ($rows as $row) {
        $columns = array_map(static fn (string $column): string => '`' . str_replace('`', '``', $column) . '`', array_keys($row));
        $values = array_map('backup_sql_value', array_values($row));
        $sql .= 'INSERT INTO `' . str_replace('`', '``', $table) . '` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ");\n";
    }

    $sql .= "\n";

    return $sql;
}

/**
 * @return list<array{entry: string, size: int, sha256: string}>
 */
function backup_add_directory_files(ZipArchive $zip, string $baseDir, string $entryBase): array
{
    if (!is_dir($baseDir)) {
        return [];
    }

    $paths = [];
    $iterator = new DirectoryIterator($baseDir);

    foreach ($iterator as $item) {
        if ($item->isDot()) {
            continue;
        }

        $filename = $item->getFilename();
        if ($filename === '.gitkeep' || $filename === '.htaccess') {
            continue;
        }

        if ($item->isLink() || !$item->isFile() || !backup_valid_media_filename($filename)) {
            throw new RuntimeException('Неочікуваний файл або директорія у media-сховищі: ' . $item->getPathname());
        }

        $paths[$filename] = $item->getPathname();
    }

    ksort($paths, SORT_STRING);
    $descriptors = [];
    foreach ($paths as $filename => $path) {
        $entry = rtrim($entryBase, '/') . '/' . $filename;
        $descriptor = backup_file_descriptor($path, $entry);

        if (!$zip->addFile($path, $entry)) {
            throw new RuntimeException('Не вдалося додати media-файл до backup ZIP: ' . $filename);
        }
        $descriptors[] = $descriptor;
    }

    return $descriptors;
}

$mediaMaintenanceLock = null;
try {
    $mediaMaintenanceLock = acquire_media_maintenance_lock(LOCK_EX);
    $pdo = db();
    $pdo->query('SELECT 1');
    $pdo->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
    $pdo->exec('START TRANSACTION WITH CONSISTENT SNAPSHOT');
} catch (Throwable $exception) {
    release_media_maintenance_lock($mediaMaintenanceLock);
    app_log_exception($exception, 'Backup DB connection failed');
    fwrite(STDERR, "Не вдалося почати consistent backup snapshot. Перевірте БД і storage permissions.\n");
    exit(1);
}

try {
    $inventoryRows = $pdo->query(
        'SELECT id, filename, thumbnail_filename, original_sha256 FROM photos ORDER BY id ASC'
    )->fetchAll(PDO::FETCH_ASSOC);
    $photoInventory = array_map(
        static fn (array $row): array => [
            'id' => (int) $row['id'],
            'filename' => (string) $row['filename'],
            'thumbnail_filename' => (string) $row['thumbnail_filename'],
            'original_sha256' => is_string($row['original_sha256'] ?? null) && $row['original_sha256'] !== ''
                ? strtolower($row['original_sha256'])
                : null,
        ],
        $inventoryRows
    );

    $sql = "-- MyGallery backup format 2\n";
    $sql .= "-- Created at: " . date('c') . "\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
    // `schema_migrations` має бути в дампі: інакше після restore реєстр міграцій порожній
    // і `tools/migrate.php` повторно проганяє всі міграції по вже мігрованих даних.
    foreach (['admins', 'albums', 'photos', 'tags', 'photo_tags', 'login_attempts', 'share_links', 'schema_migrations'] as $table) {
        $sql .= export_table_sql($pdo, $table);
    }
    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    release_media_maintenance_lock($mediaMaintenanceLock);
    app_log_exception($exception, 'Backup consistent snapshot export failed');
    fwrite(STDERR, "Не вдалося експортувати consistent DB snapshot.\n");
    exit(1);
}

$tmpSql = tempnam(sys_get_temp_dir(), 'mygallery_backup_');
if ($tmpSql === false || file_put_contents($tmpSql, $sql) === false) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    release_media_maintenance_lock($mediaMaintenanceLock);
    fwrite(STDERR, "Не вдалося створити тимчасовий SQL dump.\n");
    exit(1);
}

$tmpManifest = null;
$manifest = [];
$zip = null;
$safeOutput = null;

try {
    $safeOutput = prepare_safe_cli_zip_output($output, $root, [$backupDir], 0600);
    $output = $safeOutput['final'];
    $zip = new ZipArchive();
    if ($zip->open($safeOutput['temporary'], ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Не вдалося відкрити temporary backup ZIP.');
    }
    foreach ([
        'mygallery_backup',
        'mygallery_backup/storage',
        'mygallery_backup/storage/originals',
        'mygallery_backup/public',
        'mygallery_backup/public/uploads',
        'mygallery_backup/public/uploads/large',
        'mygallery_backup/public/uploads/thumbnails',
    ] as $directoryEntry) {
        if (!$zip->addEmptyDir($directoryEntry)) {
            throw new RuntimeException('Не вдалося додати директорію до backup ZIP: ' . $directoryEntry);
        }
    }

    $databaseDescriptor = backup_file_descriptor($tmpSql, MYGALLERY_BACKUP_DATABASE_ENTRY);
    if (!$zip->addFile($tmpSql, MYGALLERY_BACKUP_DATABASE_ENTRY)) {
        throw new RuntimeException('Не вдалося додати database.sql до backup ZIP.');
    }

    $manifest = [
        'format_version' => MYGALLERY_BACKUP_FORMAT_VERSION,
        'created_at' => date('c'),
        'include_config' => $includeConfig,
        'database' => $databaseDescriptor,
        'files' => [
            'storage_originals' => backup_add_directory_files($zip, originals_path(), backup_media_prefixes()['storage_originals']),
            'public_large' => backup_add_directory_files($zip, uploads_path('large'), backup_media_prefixes()['public_large']),
            'public_thumbnails' => backup_add_directory_files($zip, uploads_path('thumbnails'), backup_media_prefixes()['public_thumbnails']),
        ],
        'photo_inventory' => $photoInventory,
        'config' => null,
    ];

    if ($includeConfig) {
        $databaseConfig = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';
        if (!is_file($databaseConfig) || !is_readable($databaseConfig)) {
            throw new RuntimeException('Запитано --include-config, але config/database.php відсутній або недоступний для читання.');
        }
        if (!$zip->addEmptyDir('mygallery_backup/config')) {
            throw new RuntimeException('Не вдалося додати config directory до backup ZIP.');
        }
        $manifest['config'] = backup_file_descriptor($databaseConfig, MYGALLERY_BACKUP_CONFIG_ENTRY);
        if (!$zip->addFile($databaseConfig, MYGALLERY_BACKUP_CONFIG_ENTRY)) {
            throw new RuntimeException('Не вдалося додати config до backup ZIP.');
        }
    }

    $tmpManifest = tempnam(sys_get_temp_dir(), 'mygallery_manifest_');
    $manifestJson = json_encode(
        $manifest,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    if ($tmpManifest === false || file_put_contents($tmpManifest, $manifestJson) === false) {
        throw new RuntimeException('Не вдалося створити manifest.');
    }
    $estimatedZipBytes = (int) $databaseDescriptor['size'] + strlen($manifestJson);
    foreach ($manifest['files'] as $descriptors) {
        foreach ($descriptors as $descriptor) {
            $estimatedZipBytes += (int) $descriptor['size'];
        }
    }
    if (is_array($manifest['config'])) {
        $estimatedZipBytes += (int) $manifest['config']['size'];
    }
    require_safe_cli_zip_free_space($safeOutput, $estimatedZipBytes);
    if (!$zip->addFile($tmpManifest, MYGALLERY_BACKUP_MANIFEST_ENTRY)) {
        throw new RuntimeException('Не вдалося додати manifest до backup ZIP.');
    }
    if (!$zip->close()) {
        throw new RuntimeException('Не вдалося завершити temporary backup ZIP.');
    }
    $zip = null;

    $validationZip = new ZipArchive();
    if ($validationZip->open($safeOutput['temporary'], ZipArchive::CHECKCONS) !== true) {
        throw new RuntimeException('Створений backup ZIP неможливо повторно відкрити для перевірки.');
    }
    try {
        backup_validate_archive(
            $validationZip,
            (int) app_config()['RESTORE_MAX_UNCOMPRESSED_BYTES'],
            (int) app_config()['RESTORE_MAX_COMPRESSION_RATIO']
        );
    } finally {
        $validationZip->close();
    }
    if (!$pdo->commit()) {
        throw new RuntimeException('Не вдалося завершити consistent DB snapshot transaction.');
    }
    publish_safe_cli_zip_output($safeOutput);
    release_media_maintenance_lock($mediaMaintenanceLock);
    $mediaMaintenanceLock = null;
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    release_media_maintenance_lock($mediaMaintenanceLock);
    $mediaMaintenanceLock = null;
    if ($zip instanceof ZipArchive) {
        $zip->close();
    }
    cleanup_safe_cli_zip_output($safeOutput);
    app_log_exception($exception, 'Backup creation/validation failed');
    fwrite(STDERR, "Backup не створено: " . $exception->getMessage() . "\n");
    exit(1);
} finally {
    if (is_string($tmpSql) && is_file($tmpSql)) {
        unlink($tmpSql);
    }
    if (is_string($tmpManifest) && is_file($tmpManifest)) {
        unlink($tmpManifest);
    }
}

$size = filesize($output);
echo "Backup ZIP створено: {$output}\n";
echo "Size: " . ($size === false ? 'unknown' : $size . ' bytes') . "\n";
echo "Оригінали: " . count($manifest['files']['storage_originals']) . "\n";
echo "Large: " . count($manifest['files']['public_large']) . "\n";
echo "Thumbnails: " . count($manifest['files']['public_thumbnails']) . "\n";
echo "SHA-256/size/ZIP streams: перевірено.\n";
if (!$includeConfig) {
    echo "config/database.php НЕ включено. Додайте --include-config тільки для приватного backup.\n";
}

function backup_rotate(string $backupDir, int $keep = 5): void
{
    $files = glob($backupDir . DIRECTORY_SEPARATOR . 'mygallery_backup_*.zip');
    if ($files === false || count($files) <= $keep) {
        return;
    }
    
    usort($files, static fn (string $a, string $b): int => filemtime($a) <=> filemtime($b));
    
    $toDelete = count($files) - $keep;
    for ($i = 0; $i < $toDelete; $i++) {
        @unlink($files[$i]);
        echo "Видалено старий бекап: " . basename($files[$i]) . "\n";
    }
}

if (!$customOutput) {
    backup_rotate($backupDir, 5);
}
