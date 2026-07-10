<?php

declare(strict_types=1);

const MYGALLERY_BACKUP_FORMAT_VERSION = 2;
const MYGALLERY_BACKUP_ROOT = 'mygallery_backup/';
const MYGALLERY_BACKUP_MANIFEST_ENTRY = MYGALLERY_BACKUP_ROOT . 'BACKUP_MANIFEST.json';
const MYGALLERY_BACKUP_DATABASE_ENTRY = MYGALLERY_BACKUP_ROOT . 'database.sql';
const MYGALLERY_BACKUP_CONFIG_ENTRY = MYGALLERY_BACKUP_ROOT . 'config/database.php';

/**
 * @return array<string, string>
 */
function backup_media_prefixes(): array
{
    return [
        'storage_originals' => MYGALLERY_BACKUP_ROOT . 'storage/originals/',
        'public_large' => MYGALLERY_BACKUP_ROOT . 'public/uploads/large/',
        'public_thumbnails' => MYGALLERY_BACKUP_ROOT . 'public/uploads/thumbnails/',
    ];
}

function backup_valid_media_filename(string $filename): bool
{
    return preg_match('/\A[a-f0-9]{32}\.(?:jpg|webp|avif)\z/', $filename) === 1;
}

function backup_zip_entry_is_safe(string $name): bool
{
    if ($name === '' || str_contains($name, "\0") || str_contains($name, '\\')) {
        return false;
    }

    if (str_starts_with($name, '/') || preg_match('/\A[A-Za-z]:/', $name) === 1) {
        return false;
    }

    foreach (explode('/', $name) as $segment) {
        if ($segment === '..') {
            return false;
        }
    }

    return true;
}

/**
 * @return array{entry: string, size: int, sha256: string}
 */
function backup_file_descriptor(string $path, string $entry): array
{
    if (!is_file($path) || !is_readable($path)) {
        throw new RuntimeException('Backup source file is not readable: ' . $path);
    }

    $size = filesize($path);
    $sha256 = hash_file('sha256', $path);
    if (!is_int($size) || !is_string($sha256)) {
        throw new RuntimeException('Не вдалося обчислити metadata для backup-файлу: ' . $path);
    }

    return [
        'entry' => $entry,
        'size' => $size,
        'sha256' => $sha256,
    ];
}

/**
 * @return array{size: int, sha256: string, content: ?string}
 */
function backup_read_zip_entry(ZipArchive $zip, string $entry, int $maximumBytes, bool $collectContent = false): array
{
    $stat = $zip->statName($entry, ZipArchive::FL_UNCHANGED);
    if ($stat === false || !isset($stat['size']) || !is_int($stat['size'])) {
        throw new RuntimeException('Файл відсутній у backup ZIP: ' . $entry);
    }

    if ($stat['size'] < 0 || $stat['size'] > $maximumBytes) {
        throw new RuntimeException('Некоректний або завеликий файл у backup ZIP: ' . $entry);
    }

    $stream = $zip->getStream($entry);
    if ($stream === false) {
        throw new RuntimeException('Не вдалося відкрити файл у backup ZIP: ' . $entry);
    }

    $hash = hash_init('sha256');
    $size = 0;
    $content = $collectContent ? '' : null;

    try {
        while (!feof($stream)) {
            $chunk = fread($stream, 1024 * 1024);
            if ($chunk === false) {
                throw new RuntimeException('Помилка читання backup ZIP entry: ' . $entry);
            }
            if ($chunk === '') {
                if (!feof($stream)) {
                    throw new RuntimeException('Передчасне завершення читання backup ZIP entry: ' . $entry);
                }
                break;
            }

            $size += strlen($chunk);
            if ($size > $maximumBytes) {
                throw new RuntimeException('Розпакований backup ZIP entry перевищує ліміт: ' . $entry);
            }
            hash_update($hash, $chunk);
            if ($collectContent) {
                $content .= $chunk;
            }
        }
    } finally {
        fclose($stream);
    }

    if ($size !== $stat['size']) {
        throw new RuntimeException('Розмір backup ZIP entry не збігається з ZIP metadata: ' . $entry);
    }

    return [
        'size' => $size,
        'sha256' => hash_final($hash),
        'content' => $content,
    ];
}

/**
 * @return array{entry: string, size: int, sha256: string}
 */
function backup_validate_descriptor(mixed $value, string $expectedEntry, string $label): array
{
    if (!is_array($value)) {
        throw new RuntimeException("Manifest: {$label} має бути об'єктом.");
    }

    $keys = array_keys($value);
    sort($keys);
    if ($keys !== ['entry', 'sha256', 'size']) {
        throw new RuntimeException("Manifest: {$label} має неочікувані або відсутні поля.");
    }

    if (($value['entry'] ?? null) !== $expectedEntry) {
        throw new RuntimeException("Manifest: некоректний entry для {$label}.");
    }
    if (!is_int($value['size']) || $value['size'] < 0) {
        throw new RuntimeException("Manifest: некоректний size для {$label}.");
    }
    if (!is_string($value['sha256']) || preg_match('/\A[a-f0-9]{64}\z/', $value['sha256']) !== 1) {
        throw new RuntimeException("Manifest: некоректний sha256 для {$label}.");
    }

    return [
        'entry' => $value['entry'],
        'size' => $value['size'],
        'sha256' => $value['sha256'],
    ];
}

function backup_media_base_jpeg(string $filename): string
{
    if (str_ends_with($filename, '.webp')) {
        return substr($filename, 0, -5) . '.jpg';
    }
    if (str_ends_with($filename, '.avif')) {
        return substr($filename, 0, -5) . '.jpg';
    }

    return $filename;
}

/**
 * @param array<string, list<array{entry: string, filename: string, size: int, sha256: string}>> $mediaEntries
 * @return list<array{id: int, filename: string, thumbnail_filename: string, original_sha256: ?string}>
 */
function backup_validate_photo_inventory(mixed $value, array $mediaEntries): array
{
    if (!is_array($value) || !array_is_list($value)) {
        throw new RuntimeException('Manifest photo_inventory має бути списком.');
    }

    $inventory = [];
    $expectedOriginals = [];
    $expectedLarge = [];
    $expectedThumbnails = [];
    $previousId = 0;

    foreach ($value as $position => $row) {
        if (!is_array($row)) {
            throw new RuntimeException("Manifest photo_inventory[{$position}] має некоректний формат.");
        }
        $keys = array_keys($row);
        sort($keys);
        if ($keys !== ['filename', 'id', 'original_sha256', 'thumbnail_filename']) {
            throw new RuntimeException("Manifest photo_inventory[{$position}] має неочікувані поля.");
        }

        $id = $row['id'] ?? null;
        $filename = $row['filename'] ?? null;
        $thumbnail = $row['thumbnail_filename'] ?? null;
        $originalSha256 = $row['original_sha256'] ?? null;
        if (!is_int($id) || $id <= $previousId
            || !is_string($filename) || preg_match('/\A[a-f0-9]{32}\.jpg\z/', $filename) !== 1
            || !is_string($thumbnail) || preg_match('/\A[a-f0-9]{32}\.jpg\z/', $thumbnail) !== 1
            || ($originalSha256 !== null
                && (!is_string($originalSha256) || preg_match('/\A[a-f0-9]{64}\z/', $originalSha256) !== 1))) {
            throw new RuntimeException("Manifest photo_inventory[{$position}] містить некоректні значення або порядок ID.");
        }

        $previousId = $id;
        $expectedOriginals[$filename] = $originalSha256;
        $expectedLarge[$filename] = true;
        $expectedThumbnails[$thumbnail] = true;
        $inventory[] = [
            'id' => $id,
            'filename' => $filename,
            'thumbnail_filename' => $thumbnail,
            'original_sha256' => $originalSha256,
        ];
    }

    $actualOriginals = [];
    foreach ($mediaEntries['storage_originals'] ?? [] as $descriptor) {
        if (!str_ends_with($descriptor['filename'], '.jpg') || !array_key_exists($descriptor['filename'], $expectedOriginals)) {
            throw new RuntimeException('Backup містить original, відсутній у DB inventory: ' . $descriptor['filename']);
        }
        $expectedHash = $expectedOriginals[$descriptor['filename']];
        if (is_string($expectedHash) && !hash_equals($expectedHash, $descriptor['sha256'])) {
            throw new RuntimeException('SHA-256 original не збігається з photos.original_sha256: ' . $descriptor['filename']);
        }
        $actualOriginals[$descriptor['filename']] = true;
    }

    $actualLarge = [];
    foreach ($mediaEntries['public_large'] ?? [] as $descriptor) {
        $baseJpeg = backup_media_base_jpeg($descriptor['filename']);
        if (!isset($expectedLarge[$baseJpeg])) {
            throw new RuntimeException('Backup містить large derivative, відсутній у DB inventory: ' . $descriptor['filename']);
        }
        if ($descriptor['filename'] === $baseJpeg) {
            $actualLarge[$baseJpeg] = true;
        }
    }

    $actualThumbnails = [];
    foreach ($mediaEntries['public_thumbnails'] ?? [] as $descriptor) {
        $baseJpeg = backup_media_base_jpeg($descriptor['filename']);
        if (!isset($expectedThumbnails[$baseJpeg])) {
            throw new RuntimeException('Backup містить thumbnail derivative, відсутній у DB inventory: ' . $descriptor['filename']);
        }
        if ($descriptor['filename'] === $baseJpeg) {
            $actualThumbnails[$baseJpeg] = true;
        }
    }

    foreach ($expectedOriginals as $filename => $_hash) {
        if (!isset($actualOriginals[$filename]) || !isset($actualLarge[$filename])) {
            throw new RuntimeException('Backup не містить обов’язковий original/large для DB photo: ' . $filename);
        }
    }
    foreach ($expectedThumbnails as $filename => $_unused) {
        if (!isset($actualThumbnails[$filename])) {
            throw new RuntimeException('Backup не містить обов’язковий thumbnail для DB photo: ' . $filename);
        }
    }

    return $inventory;
}

/**
 * Validates the complete archive allowlist and every declared file stream.
 *
 * @return array{
 *     sql: string,
 *     manifest: array<string, mixed>,
 *     media_entries: array<string, list<array{entry: string, filename: string, size: int, sha256: string}>>,
 *     photo_inventory: list<array{id: int, filename: string, thumbnail_filename: string, original_sha256: ?string}>,
 *     file_count: int
 * }
 */
function backup_validate_archive(ZipArchive $zip): array
{
    if ($zip->numFiles < 2 || $zip->numFiles > 200000) {
        throw new RuntimeException('Некоректна кількість entries у backup ZIP.');
    }

    $archiveEntries = [];
    for ($index = 0; $index < $zip->numFiles; $index++) {
        $stat = $zip->statIndex($index, ZipArchive::FL_UNCHANGED);
        if ($stat === false || !isset($stat['name']) || !is_string($stat['name'])) {
            throw new RuntimeException('Не вдалося прочитати ZIP entry.');
        }

        $name = $stat['name'];
        if (!backup_zip_entry_is_safe($name)) {
            throw new RuntimeException('Небезпечний або ненормалізований шлях у backup ZIP: ' . $name);
        }
        if (isset($archiveEntries[$name])) {
            throw new RuntimeException('Дубльований entry у backup ZIP: ' . $name);
        }
        $archiveEntries[$name] = true;
    }

    $manifestRead = backup_read_zip_entry($zip, MYGALLERY_BACKUP_MANIFEST_ENTRY, 64 * 1024 * 1024, true);
    $manifestJson = $manifestRead['content'];
    if (!is_string($manifestJson) || trim($manifestJson) === '') {
        throw new RuntimeException('BACKUP_MANIFEST.json порожній.');
    }

    try {
        $manifest = json_decode($manifestJson, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new RuntimeException('BACKUP_MANIFEST.json не є валідним JSON.', 0, $exception);
    }

    if (!is_array($manifest)) {
        throw new RuntimeException('BACKUP_MANIFEST.json має некоректний формат.');
    }

    $manifestKeys = array_keys($manifest);
    sort($manifestKeys);
    if ($manifestKeys !== ['config', 'created_at', 'database', 'files', 'format_version', 'include_config', 'photo_inventory']) {
        throw new RuntimeException('BACKUP_MANIFEST.json має неочікувані або відсутні поля.');
    }
    if (($manifest['format_version'] ?? null) !== MYGALLERY_BACKUP_FORMAT_VERSION) {
        throw new RuntimeException('Непідтримуваний backup format_version. Створіть новий backup цією версією MyGallery.');
    }
    if (!is_string($manifest['created_at']) || strtotime($manifest['created_at']) === false) {
        throw new RuntimeException('Manifest містить некоректний created_at.');
    }
    if (!is_bool($manifest['include_config']) || !is_array($manifest['files'])) {
        throw new RuntimeException('Manifest містить некоректні include_config/files.');
    }

    $database = backup_validate_descriptor($manifest['database'] ?? null, MYGALLERY_BACKUP_DATABASE_ENTRY, 'database');
    if ($database['size'] === 0) {
        throw new RuntimeException('Manifest декларує порожній database.sql.');
    }

    $expectedFiles = [
        MYGALLERY_BACKUP_MANIFEST_ENTRY => true,
        MYGALLERY_BACKUP_DATABASE_ENTRY => true,
    ];
    $mediaEntries = [];
    $prefixes = backup_media_prefixes();
    $fileGroupKeys = array_keys($manifest['files']);
    sort($fileGroupKeys);
    $expectedGroupKeys = array_keys($prefixes);
    sort($expectedGroupKeys);
    if ($fileGroupKeys !== $expectedGroupKeys) {
        throw new RuntimeException('Manifest files має неочікувані або відсутні media-групи.');
    }

    foreach ($prefixes as $group => $prefix) {
        $descriptors = $manifest['files'][$group] ?? null;
        if (!is_array($descriptors) || !array_is_list($descriptors)) {
            throw new RuntimeException("Manifest media-група {$group} має бути списком.");
        }

        $mediaEntries[$group] = [];
        foreach ($descriptors as $position => $descriptorValue) {
            if (!is_array($descriptorValue) || !isset($descriptorValue['entry']) || !is_string($descriptorValue['entry'])) {
                throw new RuntimeException("Manifest: некоректний media descriptor {$group}[{$position}].");
            }
            $entry = $descriptorValue['entry'];
            if (!str_starts_with($entry, $prefix)) {
                throw new RuntimeException("Manifest: media entry не належить до групи {$group}.");
            }

            $filename = substr($entry, strlen($prefix));
            if ($filename === '' || basename($filename) !== $filename || !backup_valid_media_filename($filename)) {
                throw new RuntimeException('Manifest містить некоректне ім’я media-файлу: ' . $entry);
            }

            $descriptor = backup_validate_descriptor($descriptorValue, $prefix . $filename, "{$group}[{$position}]");
            if (isset($expectedFiles[$entry])) {
                throw new RuntimeException('Manifest дублює backup entry: ' . $entry);
            }
            $expectedFiles[$entry] = true;
            $mediaEntries[$group][] = $descriptor + ['filename' => $filename];
        }
    }

    $photoInventory = backup_validate_photo_inventory($manifest['photo_inventory'] ?? null, $mediaEntries);

    if ($manifest['include_config']) {
        $config = backup_validate_descriptor($manifest['config'], MYGALLERY_BACKUP_CONFIG_ENTRY, 'config');
        $expectedFiles[MYGALLERY_BACKUP_CONFIG_ENTRY] = true;
    } elseif ($manifest['config'] !== null) {
        throw new RuntimeException('Manifest config має бути null, коли include_config=false.');
    }

    $allowedDirectories = [
        MYGALLERY_BACKUP_ROOT => true,
        MYGALLERY_BACKUP_ROOT . 'storage/' => true,
        MYGALLERY_BACKUP_ROOT . 'storage/originals/' => true,
        MYGALLERY_BACKUP_ROOT . 'public/' => true,
        MYGALLERY_BACKUP_ROOT . 'public/uploads/' => true,
        MYGALLERY_BACKUP_ROOT . 'public/uploads/large/' => true,
        MYGALLERY_BACKUP_ROOT . 'public/uploads/thumbnails/' => true,
        MYGALLERY_BACKUP_ROOT . 'config/' => true,
    ];

    foreach ($archiveEntries as $entry => $_unused) {
        if (str_ends_with($entry, '/')) {
            if (!isset($allowedDirectories[$entry])) {
                throw new RuntimeException('Неочікувана директорія у backup ZIP: ' . $entry);
            }
            continue;
        }
        if (!isset($expectedFiles[$entry])) {
            throw new RuntimeException('Неочікуваний файл у backup ZIP: ' . $entry);
        }
    }

    foreach ($expectedFiles as $entry => $_unused) {
        if (!isset($archiveEntries[$entry])) {
            throw new RuntimeException('Manifest декларує відсутній backup entry: ' . $entry);
        }
    }

    $databaseRead = backup_read_zip_entry($zip, $database['entry'], 512 * 1024 * 1024, true);
    if ($databaseRead['size'] !== $database['size'] || !hash_equals($database['sha256'], $databaseRead['sha256'])) {
        throw new RuntimeException('database.sql не збігається з manifest за size/sha256.');
    }
    $sql = $databaseRead['content'];
    if (!is_string($sql)
        || trim($sql) === ''
        || !str_contains($sql, '-- MyGallery backup format 2')
        || !str_contains($sql, 'SET FOREIGN_KEY_CHECKS=0;')
        || !str_contains($sql, 'DELETE FROM `schema_migrations`;')
        || !str_contains($sql, 'SET FOREIGN_KEY_CHECKS=1;')) {
        throw new RuntimeException('database.sql не схожий на повний DML dump MyGallery format 2.');
    }

    foreach ($mediaEntries as $descriptors) {
        foreach ($descriptors as $descriptor) {
            $read = backup_read_zip_entry($zip, $descriptor['entry'], 1024 * 1024 * 1024);
            if ($read['size'] !== $descriptor['size'] || !hash_equals($descriptor['sha256'], $read['sha256'])) {
                throw new RuntimeException('Media-файл не збігається з manifest за size/sha256: ' . $descriptor['entry']);
            }
        }
    }

    if ($manifest['include_config']) {
        /** @var array{entry: string, size: int, sha256: string} $config */
        $read = backup_read_zip_entry($zip, $config['entry'], 2 * 1024 * 1024);
        if ($read['size'] !== $config['size'] || !hash_equals($config['sha256'], $read['sha256'])) {
            throw new RuntimeException('config/database.php не збігається з manifest за size/sha256.');
        }
    }

    return [
        'sql' => $sql,
        'manifest' => $manifest,
        'media_entries' => $mediaEntries,
        'photo_inventory' => $photoInventory,
        'file_count' => count($expectedFiles),
    ];
}
