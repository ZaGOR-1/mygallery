<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'SimpleZipWriter.php';

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

$root = dirname(__DIR__);
$backupDir = $root . DIRECTORY_SEPARATOR . 'backups';
$timestamp = date('Ymd_His');
$output = $backupDir . DIRECTORY_SEPARATOR . 'mygallery_backup_' . $timestamp . '.zip';
$includeConfig = isset($options['include-config']);

if (isset($options['output']) && is_string($options['output']) && $options['output'] !== '') {
    $output = $options['output'];
}

if (!is_dir($backupDir) && !mkdir($backupDir, 0755, true)) {
    fwrite(STDERR, "Не вдалося створити папку backups.\n");
    exit(1);
}

function backup_normalize_path(string $path): string
{
    $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $path);

    if (preg_match('/^[A-Za-z]:\\\\/', $path) !== 1 && !str_starts_with($path, DIRECTORY_SEPARATOR)) {
        $path = getcwd() . DIRECTORY_SEPARATOR . $path;
    }

    $parts = [];
    $prefix = '';

    if (preg_match('/^[A-Za-z]:\\\\/', $path) === 1) {
        $prefix = substr($path, 0, 3);
        $path = substr($path, 3);
    } elseif (str_starts_with($path, DIRECTORY_SEPARATOR)) {
        $prefix = DIRECTORY_SEPARATOR;
        $path = ltrim($path, DIRECTORY_SEPARATOR);
    }

    foreach (explode(DIRECTORY_SEPARATOR, $path) as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }

        if ($part === '..') {
            array_pop($parts);
            continue;
        }

        $parts[] = $part;
    }

    return rtrim($prefix . implode(DIRECTORY_SEPARATOR, $parts), DIRECTORY_SEPARATOR);
}

function backup_path_is_inside(string $path, string $directory): bool
{
    $path = backup_normalize_path($path);
    $directory = rtrim(backup_normalize_path($directory), DIRECTORY_SEPARATOR);
    $pathForCompare = PHP_OS_FAMILY === 'Windows' ? strtolower($path) : $path;
    $directoryForCompare = PHP_OS_FAMILY === 'Windows' ? strtolower($directory) : $directory;

    return $pathForCompare === $directoryForCompare
        || str_starts_with($pathForCompare, $directoryForCompare . DIRECTORY_SEPARATOR);
}

function backup_validate_output_path(string $output, string $root): void
{
    $publicPath = $root . DIRECTORY_SEPARATOR . 'public';
    $outputPath = backup_normalize_path($output);

    if (backup_path_is_inside($outputPath, $publicPath)) {
        fwrite(STDERR, "Backup output must not be inside public/.\n");
        exit(1);
    }
}

backup_validate_output_path($output, $root);

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
    $sql = "-- Table: `{$table}`\n";
    $sql .= "LOCK TABLES `{$table}` WRITE;\n";
    $sql .= "DELETE FROM `{$table}`;\n";

    foreach ($rows as $row) {
        $columns = array_map(static fn (string $column): string => '`' . str_replace('`', '``', $column) . '`', array_keys($row));
        $values = array_map('backup_sql_value', array_values($row));
        $sql .= 'INSERT INTO `' . str_replace('`', '``', $table) . '` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', $values) . ");\n";
    }

    $sql .= "UNLOCK TABLES;\n\n";

    return $sql;
}

function backup_add_directory_files(SimpleZipWriter $zip, string $baseDir, string $entryBase): int
{
    if (!is_dir($baseDir)) {
        return 0;
    }

    $count = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($baseDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );

    foreach ($iterator as $item) {
        /** @var SplFileInfo $item */
        $path = $item->getPathname();
        $relative = trim(str_replace('\\', '/', substr($path, strlen($baseDir))), '/');
        $entry = rtrim($entryBase, '/') . '/' . $relative;

        if ($item->isDir()) {
            $zip->addDirectory($entry);
            continue;
        }

        if (basename($path) === '.gitkeep') {
            continue;
        }

        $zip->addFile($path, $entry);
        $count++;
    }

    return $count;
}

try {
    $pdo = db();
    $pdo->query('SELECT 1');
} catch (Throwable $exception) {
    app_log_exception($exception, 'Backup DB connection failed');
    fwrite(STDERR, "Не вдалося підключитися до БД. Перевірте config/database.php.\n");
    exit(1);
}

$sql = "-- MyGallery backup\n";
$sql .= "-- Created at: " . date('c') . "\n";
$sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
foreach (['admins', 'albums', 'photos', 'tags', 'photo_tags', 'login_attempts', 'share_links'] as $table) {
    $sql .= export_table_sql($pdo, $table);
}
$sql .= "SET FOREIGN_KEY_CHECKS=1;\n";

$tmpSql = tempnam(sys_get_temp_dir(), 'mygallery_backup_');
if ($tmpSql === false || file_put_contents($tmpSql, $sql) === false) {
    fwrite(STDERR, "Не вдалося створити тимчасовий SQL dump.\n");
    exit(1);
}

$zip = new SimpleZipWriter($output);
$zip->addDirectory('mygallery_backup');
$zip->addFile($tmpSql, 'mygallery_backup/database.sql');

$manifest = [
    'created_at' => date('c'),
    'include_config' => $includeConfig,
    'files' => [],
];

$manifest['files']['storage_originals'] = backup_add_directory_files($zip, originals_path(), 'mygallery_backup/storage/originals');
$manifest['files']['public_large'] = backup_add_directory_files($zip, uploads_path('large'), 'mygallery_backup/public/uploads/large');
$manifest['files']['public_thumbnails'] = backup_add_directory_files($zip, uploads_path('thumbnails'), 'mygallery_backup/public/uploads/thumbnails');

if ($includeConfig) {
    $databaseConfig = $root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php';
    if (is_file($databaseConfig)) {
        $zip->addFile($databaseConfig, 'mygallery_backup/config/database.php');
        $manifest['files']['config_database_php'] = 1;
    }
}

$tmpManifest = tempnam(sys_get_temp_dir(), 'mygallery_manifest_');
if ($tmpManifest === false || file_put_contents($tmpManifest, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) === false) {
    fwrite(STDERR, "Не вдалося створити manifest.\n");
    exit(1);
}
$zip->addFile($tmpManifest, 'mygallery_backup/BACKUP_MANIFEST.json');
$zip->finish();

@unlink($tmpSql);
@unlink($tmpManifest);

$size = filesize($output);
echo "Backup ZIP створено: {$output}\n";
echo "Size: " . ($size === false ? 'unknown' : $size . ' bytes') . "\n";
echo "Оригінали: " . $manifest['files']['storage_originals'] . "\n";
echo "Large: " . $manifest['files']['public_large'] . "\n";
echo "Thumbnails: " . $manifest['files']['public_thumbnails'] . "\n";
if (!$includeConfig) {
    echo "config/database.php НЕ включено. Додайте --include-config тільки для приватного backup.\n";
}
