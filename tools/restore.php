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

if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "Для роботи скрипта потрібне PHP-розширення zip.\n");
    exit(1);
}

function restore_zip_entry_is_safe(string $name): bool
{
    if ($name === '' || str_contains($name, "\0")) {
        return false;
    }

    $normalized = str_replace('\\', '/', $name);

    if (str_starts_with($normalized, '/') || preg_match('/^[A-Za-z]:/', $normalized) === 1) {
        return false;
    }

    foreach (explode('/', $normalized) as $segment) {
        if ($segment === '..') {
            return false;
        }
    }

    return true;
}

function restore_normalize_path(string $path): string
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

function restore_path_is_inside(string $path, string $directory): bool
{
    $path = restore_normalize_path($path);
    $directory = rtrim(restore_normalize_path($directory), DIRECTORY_SEPARATOR);
    $pathForCompare = PHP_OS_FAMILY === 'Windows' ? strtolower($path) : $path;
    $directoryForCompare = PHP_OS_FAMILY === 'Windows' ? strtolower($directory) : $directory;

    return $pathForCompare === $directoryForCompare
        || str_starts_with($pathForCompare, $directoryForCompare . DIRECTORY_SEPARATOR);
}

function clean_directory(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($iterator as $fileinfo) {
        $filename = $fileinfo->getFilename();
        if ($filename === '.gitkeep' || $filename === '.htaccess') {
            continue;
        }

        $path = $fileinfo->getRealPath();
        if (!is_string($path)) {
            continue;
        }

        if ($fileinfo->isDir()) {
            @rmdir($path);
        } else {
            @unlink($path);
        }
    }
}

function restore_validate_media_entry(ZipArchive $zip, int $index, string $name, string $prefix, string $baseDirectory): ?array
{
    if (!str_starts_with($name, $prefix) || str_ends_with($name, '/')) {
        return null;
    }

    $relative = substr($name, strlen($prefix));
    $relative = str_replace('\\', '/', $relative);

    if ($relative === '' || basename($relative) !== $relative || !valid_photo_filename($relative)) {
        throw new RuntimeException("Некоректний media-файл у backup: {$name}");
    }

    $targetPath = rtrim($baseDirectory, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $relative;
    if (!restore_path_is_inside($targetPath, $baseDirectory)) {
        throw new RuntimeException("Path Traversal у backup: {$name}");
    }

    $stream = $zip->getStream($name);
    if ($stream === false) {
        throw new RuntimeException("Не вдалося прочитати файл із backup: {$name}");
    }
    fclose($stream);

    return [
        'index' => $index,
        'name' => $name,
        'target' => $targetPath,
        'base' => $baseDirectory,
    ];
}

function restore_validate_backup_archive(ZipArchive $zip): array
{
    $sqlContent = $zip->getFromName('mygallery_backup/database.sql');
    if ($sqlContent === false || trim($sqlContent) === '') {
        throw new RuntimeException('Файл mygallery_backup/database.sql не знайдено або він порожній.');
    }

    $manifestContent = $zip->getFromName('mygallery_backup/BACKUP_MANIFEST.json');
    if ($manifestContent === false || trim($manifestContent) === '') {
        throw new RuntimeException('Файл mygallery_backup/BACKUP_MANIFEST.json не знайдено або він порожній.');
    }

    $manifest = json_decode($manifestContent, true);
    if (!is_array($manifest) || empty($manifest['created_at']) || !isset($manifest['files']) || !is_array($manifest['files'])) {
        throw new RuntimeException('BACKUP_MANIFEST.json має некоректний формат.');
    }

    foreach ([originals_path(), uploads_path('large'), uploads_path('thumbnails')] as $directory) {
        if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
            throw new RuntimeException('Не вдалося створити media-директорію: ' . $directory);
        }

        if (!is_writable($directory)) {
            throw new RuntimeException('Media-директорія недоступна для запису: ' . $directory);
        }
    }

    $entries = [];
    $allowedExactFiles = [
        'mygallery_backup/database.sql' => true,
        'mygallery_backup/BACKUP_MANIFEST.json' => true,
        'mygallery_backup/config/database.php' => true,
    ];
    $allowedDirectoryEntries = [
        'mygallery_backup/' => true,
        'mygallery_backup/storage/' => true,
        'mygallery_backup/storage/originals/' => true,
        'mygallery_backup/public/' => true,
        'mygallery_backup/public/uploads/' => true,
        'mygallery_backup/public/uploads/large/' => true,
        'mygallery_backup/public/uploads/thumbnails/' => true,
        'mygallery_backup/config/' => true,
    ];

    for ($i = 0; $i < $zip->numFiles; $i++) {
        $stat = $zip->statIndex($i);
        if ($stat === false || !isset($stat['name'])) {
            throw new RuntimeException('Не вдалося прочитати entry з backup ZIP.');
        }

        $name = str_replace('\\', '/', (string) $stat['name']);
        if (!restore_zip_entry_is_safe($name)) {
            throw new RuntimeException("Небезпечний шлях у backup ZIP: {$name}");
        }

        if (str_ends_with($name, '/')) {
            if (!isset($allowedDirectoryEntries[$name])) {
                throw new RuntimeException("Неочікувана директорія у backup ZIP: {$name}");
            }
            continue;
        }

        if (isset($allowedExactFiles[$name])) {
            continue;
        }

        $entry = restore_validate_media_entry($zip, $i, $name, 'mygallery_backup/storage/originals/', originals_path())
            ?? restore_validate_media_entry($zip, $i, $name, 'mygallery_backup/public/uploads/large/', uploads_path('large'))
            ?? restore_validate_media_entry($zip, $i, $name, 'mygallery_backup/public/uploads/thumbnails/', uploads_path('thumbnails'));

        if ($entry === null) {
            throw new RuntimeException("Неочікуваний файл у backup ZIP: {$name}");
        }

        $entries[] = $entry;
    }

    return [
        'sql' => $sqlContent,
        'manifest' => $manifest,
        'media_entries' => $entries,
    ];
}

$zip = new ZipArchive();
if ($zip->open($zipPath) !== true) {
    fwrite(STDERR, "Не вдалося відкрити ZIP архів: {$zipPath}\n");
    exit(1);
}

try {
    echo "Перевірка backup архіву перед відновленням...\n";
    $validatedBackup = restore_validate_backup_archive($zip);
    echo "Backup архів валідний. Media-файлів до відновлення: " . count($validatedBackup['media_entries']) . ".\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Backup не пройшов перевірку: " . $exception->getMessage() . "\n");
    $zip->close();
    exit(1);
}

echo "УВАГА: Ця дія незворотна! Вона видалить усі існуючі дані та замінить їх даними з резервної копії.\n";
echo "Щоб продовжити, введіть 'RESTORE': ";
$confirmation = trim((string) fgets(STDIN));
if ($confirmation !== 'RESTORE') {
    echo "Відмінено.\n";
    $zip->close();
    exit(1);
}

$tmpSql = tempnam(sys_get_temp_dir(), 'mygallery_restore_');
if ($tmpSql === false || file_put_contents($tmpSql, $validatedBackup['sql']) === false) {
    fwrite(STDERR, "Не вдалося створити тимчасовий SQL-файл.\n");
    $zip->close();
    exit(1);
}

try {
    $pdo = db();
    $pdo->exec($validatedBackup['sql']);
    echo "Базу даних успішно відновлено.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Помилка відновлення БД: " . $e->getMessage() . "\n");
    @unlink($tmpSql);
    $zip->close();
    exit(1);
}
@unlink($tmpSql);

echo "Очищення поточних медіа-файлів...\n";
clean_directory(originals_path());
clean_directory(uploads_path('large'));
clean_directory(uploads_path('thumbnails'));

echo "Розпакування медіа-файлів...\n";
$extractedCount = 0;
foreach ($validatedBackup['media_entries'] as $entry) {
    $dir = dirname($entry['target']);
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        fwrite(STDERR, "Не вдалося створити директорію: {$dir}\n");
        $zip->close();
        exit(1);
    }

    $source = $zip->getStream($entry['name']);
    if ($source === false) {
        fwrite(STDERR, "Не вдалося прочитати файл із backup: {$entry['name']}\n");
        $zip->close();
        exit(1);
    }

    $target = fopen($entry['target'], 'wb');
    if ($target === false) {
        fclose($source);
        fwrite(STDERR, "Не вдалося записати файл: {$entry['target']}\n");
        $zip->close();
        exit(1);
    }

    stream_copy_to_stream($source, $target);
    fclose($source);
    fclose($target);
    if (str_contains($entry['target'], 'public' . DIRECTORY_SEPARATOR . 'uploads')) {
        create_webp_copy($entry['target']);
        create_avif_copy($entry['target']);
    }
    $extractedCount++;
}

$zip->close();
echo "Відновлено файлів: {$extractedCount}.\n";
echo "Відновлення успішно завершено!\n";
