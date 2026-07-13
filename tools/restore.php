<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'BackupArchiveValidator.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Цей файл запускається тільки з консолі.');
}

if (!defined('MYGALLERY_RESTORE_LIBRARY_ONLY') && $argc < 2) {
    fwrite(STDERR, "Usage: php tools/restore.php /path/to/backup.zip\n");
    fwrite(STDERR, "УВАГА: Цей скрипт повністю замінить поточну базу даних і файли!\n");
    exit(1);
}

if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "Для роботи скрипта потрібне PHP-розширення zip.\n");
    exit(1);
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

function restore_same_path(string $first, string $second): bool
{
    $first = restore_normalize_path($first);
    $second = restore_normalize_path($second);
    if (PHP_OS_FAMILY === 'Windows') {
        return strtolower($first) === strtolower($second);
    }

    return $first === $second;
}

/**
 * @return array<string, array{target: string, stage: string, old: string, old_moved: bool, new_installed: bool}>
 */
function restore_build_mappings(string $operationId, ?array $targets = null): array
{
    if (preg_match('/\A[a-f0-9]{32}\z/', $operationId) !== 1) {
        throw new RuntimeException('Некоректний restore operation id.');
    }

    $targets ??= [
        'storage_originals' => originals_path(),
        'public_large' => uploads_path('large'),
        'public_thumbnails' => uploads_path('thumbnails'),
    ];
    if (array_keys($targets) !== ['storage_originals', 'public_large', 'public_thumbnails']) {
        throw new RuntimeException('Некоректний набір media targets для restore.');
    }
    $mappings = [];

    foreach ($targets as $group => $target) {
        $target = rtrim(restore_normalize_path($target), DIRECTORY_SEPARATOR);
        $parent = dirname($target);
        $suffix = str_replace('_', '-', $group);
        $mappings[$group] = [
            'target' => $target,
            'stage' => $parent . DIRECTORY_SEPARATOR . ".restore-stage-{$operationId}-{$suffix}",
            'old' => $parent . DIRECTORY_SEPARATOR . ".restore-old-{$operationId}-{$suffix}",
            'old_moved' => false,
            'new_installed' => false,
        ];
    }

    return $mappings;
}

/**
 * @param array<string, array{target: string, stage: string, old: string, old_moved: bool, new_installed: bool}> $mappings
 * @param array<string, int> $mediaBytes
 */
function restore_require_staging_capacity(array $mappings, array $mediaBytes, int $minimumFreeBytes): void
{
    $volumes = [];
    foreach ($mappings as $group => $mapping) {
        $parent = dirname($mapping['target']);
        $stat = @stat($parent);
        $device = is_array($stat) && isset($stat['dev'])
            ? 'dev:' . (string) $stat['dev']
            : 'path:' . strtolower((string) preg_replace('/[\\\/].*$/', '', restore_normalize_path($parent)));
        if (!isset($volumes[$device])) {
            $free = @disk_free_space($parent);
            if (!is_float($free) && !is_int($free)) {
                throw new RuntimeException('Не вдалося визначити вільне місце для restore staging: ' . $parent);
            }
            $volumes[$device] = ['free' => (float) $free, 'required' => 0, 'path' => $parent];
        }
        $bytes = (int) ($mediaBytes[$group] ?? 0);
        if ($bytes < 0 || $bytes > PHP_INT_MAX - $volumes[$device]['required']) {
            throw new RuntimeException('Некоректний сукупний media size для restore staging.');
        }
        $volumes[$device]['required'] += $bytes;
    }

    foreach ($volumes as $volume) {
        $requiredWithReserve = $volume['required'] + max(0, $minimumFreeBytes);
        if ($volume['free'] < $requiredWithReserve) {
            throw new RuntimeException(
                'Недостатньо вільного місця для restore staging у ' . $volume['path']
                . ': потрібно щонайменше ' . $requiredWithReserve . ' bytes.'
            );
        }
    }
}

function restore_remove_generated_tree(string $path, string $expectedParent): void
{
    if (!file_exists($path) && !is_link($path)) {
        return;
    }
    if (!restore_same_path(dirname($path), $expectedParent)
        || preg_match('/\A\.restore-(?:stage|old|discard)-[a-f0-9]{32}-[a-z-]+\z/', basename($path)) !== 1) {
        throw new RuntimeException('Відмова видаляти шлях поза restore staging: ' . $path);
    }
    if (!filesystem_path_is_safe_child($path, $expectedParent) || !is_dir($path)) {
        throw new RuntimeException('Restore staging path має неочікуваний тип: ' . $path);
    }

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        /** @var SplFileInfo $item */
        $itemPath = $item->getPathname();
        if (!filesystem_path_is_safe_child($itemPath, $path)) {
            throw new RuntimeException('Небезпечний symlink/junction у restore staging: ' . $itemPath);
        }
        if ($item->isFile()) {
            if (!unlink($itemPath)) {
                throw new RuntimeException('Не вдалося видалити restore staging file: ' . $itemPath);
            }
        } elseif ($item->isDir() && !rmdir($itemPath)) {
            throw new RuntimeException('Не вдалося видалити restore staging directory: ' . $itemPath);
        }
    }
    if (!rmdir($path)) {
        throw new RuntimeException('Не вдалося видалити restore staging root: ' . $path);
    }
}

/**
 * @param array<string, array{target: string, stage: string, old: string, old_moved: bool, new_installed: bool}> $mappings
 */
function restore_write_journal(string $journalPath, string $operationId, string $marker, array $mappings): void
{
    if (is_link($journalPath)) {
        throw new RuntimeException('Restore journal не може бути symbolic link: ' . $journalPath);
    }
    if (!ensure_private_directory(dirname($journalPath))) {
        throw new RuntimeException('Не вдалося підготувати приватну директорію restore journal: ' . dirname($journalPath));
    }

    $payload = json_encode([
        'version' => 1,
        'operation_id' => $operationId,
        'database_marker' => $marker,
        'mappings' => $mappings,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    $temporary = tempnam(dirname($journalPath), '.restore-journal-');
    if (!is_string($temporary)) {
        throw new RuntimeException('Не вдалося створити тимчасовий restore journal: ' . $journalPath);
    }
    if (is_link($temporary) || !restore_same_path(dirname($temporary), dirname($journalPath))) {
        @unlink($temporary);
        throw new RuntimeException('Тимчасовий restore journal створено поза дозволеною директорією: ' . $temporary);
    }

    try {
        $written = private_file_put_contents($temporary, $payload, LOCK_EX);
        if ($written !== strlen($payload)) {
            throw new RuntimeException('Не вдалося повністю записати restore journal: ' . $journalPath);
        }
        if (is_link($journalPath) || !rename($temporary, $journalPath)) {
            throw new RuntimeException('Не вдалося атомарно встановити restore journal: ' . $journalPath);
        }
        if (!enforce_private_file_permissions($journalPath)) {
            throw new RuntimeException('Не вдалося встановити 0600 для restore journal: ' . $journalPath);
        }
    } finally {
        if (is_file($temporary) || is_link($temporary)) {
            @unlink($temporary);
        }
    }
}

/**
 * @param array<string, array{target: string, stage: string, old: string, old_moved: bool, new_installed: bool}> $mappings
 */
function restore_prepare_staging(array $mappings): void
{
    foreach ($mappings as $group => $mapping) {
        $target = $mapping['target'];
        if (!is_dir($target)
            || !is_writable($target)
            || !filesystem_path_is_safe_child($target, dirname($target))) {
            throw new RuntimeException('Media-директорія відсутня або недоступна для запису: ' . $target);
        }
        if (file_exists($mapping['stage']) || file_exists($mapping['old'])) {
            throw new RuntimeException('Restore staging path уже існує: ' . $mapping['stage']);
        }
        $directoryMode = $group === 'storage_originals' ? private_directory_mode() : shared_directory_mode();
        if (!mkdir($mapping['stage'], $directoryMode)) {
            throw new RuntimeException('Не вдалося створити restore staging: ' . $mapping['stage']);
        }
        if (PHP_OS_FAMILY !== 'Windows' && !chmod($mapping['stage'], $directoryMode)) {
            throw new RuntimeException('Не вдалося встановити безпечні права restore staging: ' . $mapping['stage']);
        }

        foreach (['.gitkeep', '.htaccess'] as $controlFile) {
            $source = $target . DIRECTORY_SEPARATOR . $controlFile;
            if (is_file($source) && !copy($source, $mapping['stage'] . DIRECTORY_SEPARATOR . $controlFile)) {
                throw new RuntimeException('Не вдалося скопіювати control file у restore staging: ' . $source);
            }
            $copied = $mapping['stage'] . DIRECTORY_SEPARATOR . $controlFile;
            if (is_file($copied)) {
                $permissionOk = $group === 'storage_originals'
                    ? enforce_private_file_permissions($copied)
                    : enforce_shared_file_permissions($copied);
                if (!$permissionOk) {
                    throw new RuntimeException('Не вдалося встановити права restore control file: ' . $copied);
                }
            }
        }
        if (($group === 'public_large' || $group === 'public_thumbnails')
            && !is_file($mapping['stage'] . DIRECTORY_SEPARATOR . '.htaccess')) {
            throw new RuntimeException('У public media-директорії відсутній обов’язковий .htaccess: ' . $target);
        }
    }
}

/**
 * @param array<string, list<array{entry: string, filename: string, size: int, sha256: string}>> $mediaEntries
 * @param array<string, array{target: string, stage: string, old: string, old_moved: bool, new_installed: bool}> $mappings
 */
function restore_extract_to_staging(ZipArchive $zip, array $mediaEntries, array $mappings): int
{
    $count = 0;
    foreach ($mediaEntries as $group => $descriptors) {
        if (!isset($mappings[$group])) {
            throw new RuntimeException('Backup містить невідому media-групу: ' . $group);
        }

        foreach ($descriptors as $descriptor) {
            $destination = $mappings[$group]['stage'] . DIRECTORY_SEPARATOR . $descriptor['filename'];
            $source = $zip->getStream($descriptor['entry']);
            if ($source === false) {
                throw new RuntimeException('Не вдалося повторно відкрити backup entry: ' . $descriptor['entry']);
            }
            $target = fopen($destination, 'xb');
            if ($target === false) {
                fclose($source);
                throw new RuntimeException('Не вдалося створити staging file: ' . $destination);
            }

            $hash = hash_init('sha256');
            $written = 0;
            try {
                while (!feof($source)) {
                    $chunk = fread($source, 1024 * 1024);
                    if ($chunk === false) {
                        throw new RuntimeException('Помилка читання backup entry: ' . $descriptor['entry']);
                    }
                    if ($chunk === '') {
                        if (!feof($source)) {
                            throw new RuntimeException('Передчасне завершення backup entry: ' . $descriptor['entry']);
                        }
                        break;
                    }

                    $length = strlen($chunk);
                    $offset = 0;
                    while ($offset < $length) {
                        $result = fwrite($target, substr($chunk, $offset));
                        if ($result === false || $result === 0) {
                            throw new RuntimeException('Помилка запису staging file: ' . $destination);
                        }
                        $offset += $result;
                    }
                    $written += $length;
                    if ($written > $descriptor['size']) {
                        throw new RuntimeException('Staging file перевищив розмір із manifest: ' . $descriptor['entry']);
                    }
                    hash_update($hash, $chunk);
                }
                if (!fflush($target)) {
                    throw new RuntimeException('Не вдалося flush staging file: ' . $destination);
                }
            } finally {
                fclose($source);
                fclose($target);
            }

            if ($written !== $descriptor['size'] || !hash_equals($descriptor['sha256'], hash_final($hash))) {
                throw new RuntimeException('Staging file не збігається з manifest: ' . $descriptor['entry']);
            }
            $permissionOk = $group === 'storage_originals'
                ? enforce_private_file_permissions($destination)
                : enforce_shared_file_permissions($destination);
            if (!$permissionOk) {
                throw new RuntimeException('Не вдалося встановити безпечні права staging file: ' . $destination);
            }
            $count++;
        }
    }

    return $count;
}

/**
 * @param array<string, array{target: string, stage: string, old: string, old_moved: bool, new_installed: bool}> $mappings
 */
function restore_swap_directories(array &$mappings, string $journalPath, string $operationId, string $marker): void
{
    foreach ($mappings as $group => &$mapping) {
        if (!rename($mapping['target'], $mapping['old'])) {
            throw new RuntimeException('Не вдалося перемістити поточну media-директорію: ' . $mapping['target']);
        }
        $mapping['old_moved'] = true;
        restore_write_journal($journalPath, $operationId, $marker, $mappings);

        if (!rename($mapping['stage'], $mapping['target'])) {
            throw new RuntimeException('Не вдалося активувати restore staging: ' . $mapping['stage']);
        }
        $mapping['new_installed'] = true;
        restore_write_journal($journalPath, $operationId, $marker, $mappings);
    }
    unset($mapping);
}

/**
 * Completes or rolls back a journaled media swap based on the DB marker committed
 * in the same transaction as the restored dump.
 */
function restore_recover_interrupted_operation(PDO $pdo, string $journalPath, ?array $targets = null): void
{
    if (is_link($journalPath)) {
        throw new RuntimeException('Restore journal не може бути symbolic link: ' . $journalPath);
    }
    if (!file_exists($journalPath)) {
        return;
    }
    if (!is_file($journalPath) || !filesystem_permissions_are_private($journalPath)) {
        throw new RuntimeException('Restore journal не є приватним regular file: ' . $journalPath);
    }

    $json = file_get_contents($journalPath);
    $journal = is_string($json) ? json_decode($json, true) : null;
    if (!is_array($journal)
        || ($journal['version'] ?? null) !== 1
        || !is_string($journal['operation_id'] ?? null)
        || !is_string($journal['database_marker'] ?? null)
        || !is_array($journal['mappings'] ?? null)) {
        throw new RuntimeException('Restore journal пошкоджений; потрібне ручне втручання: ' . $journalPath);
    }

    $operationId = $journal['operation_id'];
    $marker = $journal['database_marker'];
    if ($marker !== '__mygallery_restore__' . $operationId) {
        throw new RuntimeException('Restore journal містить некоректний DB marker.');
    }

    $expectedMappings = restore_build_mappings($operationId, $targets);
    foreach ($expectedMappings as $group => $expected) {
        $actual = $journal['mappings'][$group] ?? null;
        if (!is_array($actual)
            || !restore_same_path((string) ($actual['target'] ?? ''), $expected['target'])
            || !restore_same_path((string) ($actual['stage'] ?? ''), $expected['stage'])
            || !restore_same_path((string) ($actual['old'] ?? ''), $expected['old'])) {
            throw new RuntimeException('Restore journal містить шлях поза дозволеним staging.');
        }
    }

    $statement = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE migration = ? LIMIT 1');
    $statement->execute([$marker]);
    $databaseCommitted = $statement->fetchColumn() !== false;

    foreach ($expectedMappings as $group => $mapping) {
        $targetExists = is_dir($mapping['target']);
        $stageExists = is_dir($mapping['stage']);
        $oldExists = is_dir($mapping['old']);

        if ($databaseCommitted) {
            if ($stageExists && !$oldExists) {
                if ($targetExists && !rename($mapping['target'], $mapping['old'])) {
                    throw new RuntimeException('Не вдалося завершити interrupted restore для ' . $group);
                }
                $oldExists = is_dir($mapping['old']);
                $targetExists = is_dir($mapping['target']);
            }
            if ($stageExists && !$targetExists) {
                if (!rename($mapping['stage'], $mapping['target'])) {
                    throw new RuntimeException('Не вдалося активувати staging після interrupted restore: ' . $group);
                }
                $stageExists = false;
                $targetExists = true;
            }
            if (!$targetExists) {
                throw new RuntimeException('Неможливо завершити interrupted restore: відсутні нові media для ' . $group);
            }
            if ($stageExists) {
                restore_remove_generated_tree($mapping['stage'], dirname($mapping['stage']));
            }
            if ($oldExists) {
                restore_remove_generated_tree($mapping['old'], dirname($mapping['old']));
            }
            continue;
        }

        if ($oldExists) {
            if ($targetExists) {
                $discard = dirname($mapping['target']) . DIRECTORY_SEPARATOR
                    . '.restore-discard-' . $operationId . '-' . str_replace('_', '-', $group);
                if (file_exists($discard) || !rename($mapping['target'], $discard)) {
                    throw new RuntimeException('Не вдалося відкласти нові media під час rollback: ' . $group);
                }
                if (!rename($mapping['old'], $mapping['target'])) {
                    throw new RuntimeException('Не вдалося повернути старі media під час rollback: ' . $group);
                }
                restore_remove_generated_tree($discard, dirname($discard));
            } elseif (!rename($mapping['old'], $mapping['target'])) {
                throw new RuntimeException('Не вдалося повернути старі media під час rollback: ' . $group);
            }
        } elseif (!$targetExists) {
            throw new RuntimeException('Неможливо відкотити interrupted restore: відсутні старі media для ' . $group);
        }

        if ($stageExists) {
            restore_remove_generated_tree($mapping['stage'], dirname($mapping['stage']));
        }
    }

    if ($databaseCommitted) {
        $deleteMarker = $pdo->prepare('DELETE FROM schema_migrations WHERE migration = ?');
        $deleteMarker->execute([$marker]);
    }
    if (!unlink($journalPath)) {
        throw new RuntimeException('Не вдалося видалити завершений restore journal: ' . $journalPath);
    }
}

if (defined('MYGALLERY_RESTORE_LIBRARY_ONLY')) {
    return;
}

$zipPath = $argv[1];
if (!is_file($zipPath)) {
    fwrite(STDERR, "Файл не знайдено: {$zipPath}\n");
    exit(1);
}

$journalPath = storage_path('restore_journal.json');
if (file_exists($journalPath) || is_link($journalPath)) {
    $recoveryMaintenanceLock = null;
    try {
        $recoveryMaintenanceLock = acquire_media_maintenance_lock(LOCK_EX);
        echo "Знайдено interrupted restore journal. Виконується безпечне відновлення стану...\n";
        restore_recover_interrupted_operation(db(), $journalPath);
        release_media_maintenance_lock($recoveryMaintenanceLock);
        echo "Interrupted restore узгоджено.\n";
    } catch (Throwable $exception) {
        release_media_maintenance_lock($recoveryMaintenanceLock);
        app_log_exception($exception, 'Interrupted restore recovery failed');
        fwrite(STDERR, "Не вдалося узгодити попередній restore: " . $exception->getMessage() . "\n");
        exit(1);
    }
}

$zip = new ZipArchive();
if ($zip->open($zipPath) !== true) {
    fwrite(STDERR, "Не вдалося відкрити ZIP архів: {$zipPath}\n");
    exit(1);
}

try {
    echo "Повна перевірка backup архіву перед відновленням...\n";
    $validatedBackup = backup_validate_archive(
        $zip,
        (int) app_config()['RESTORE_MAX_UNCOMPRESSED_BYTES'],
        (int) app_config()['RESTORE_MAX_COMPRESSION_RATIO']
    );
    $mediaCount = array_sum(array_map('count', $validatedBackup['media_entries']));
    echo "Backup format 2 валідний: перевірено allowlist, streams, size і SHA-256. Media-файлів: {$mediaCount}.\n";
} catch (Throwable $exception) {
    fwrite(STDERR, "Backup не пройшов перевірку: " . $exception->getMessage() . "\n");
    $zip->close();
    exit(1);
}

echo "УВАГА: Буде повністю замінено поточну БД і media-файли.\n";
echo "Щоб продовжити, введіть 'RESTORE': ";
$confirmation = trim((string) fgets(STDIN));
if ($confirmation !== 'RESTORE') {
    echo "Відмінено.\n";
    $zip->close();
    exit(1);
}

$operationId = bin2hex(random_bytes(16));
$marker = '__mygallery_restore__' . $operationId;
$mappings = restore_build_mappings($operationId);
$mediaMaintenanceLock = null;

try {
    $mediaMaintenanceLock = acquire_media_maintenance_lock(LOCK_EX);
    $pdo = db();
    $pdo->query('SELECT 1');
    echo "Підготовка і повторна перевірка media у staging...\n";
    restore_require_staging_capacity(
        $mappings,
        $validatedBackup['media_uncompressed_bytes'],
        (int) app_config()['RESTORE_MIN_FREE_BYTES']
    );
    restore_prepare_staging($mappings);
    $stagedCount = restore_extract_to_staging($zip, $validatedBackup['media_entries'], $mappings);
    restore_write_journal($journalPath, $operationId, $marker, $mappings);
} catch (Throwable $exception) {
    foreach ($mappings as $mapping) {
        try {
            restore_remove_generated_tree($mapping['stage'], dirname($mapping['stage']));
        } catch (Throwable) {
            // Основна помилка нижче важливіша; залишок staging не активований.
        }
    }
    app_log_exception($exception, 'Restore staging failed');
    fwrite(STDERR, "Restore зупинено до зміни БД/media: " . $exception->getMessage() . "\n");
    release_media_maintenance_lock($mediaMaintenanceLock);
    $zip->close();
    exit(1);
}

try {
    if (!$pdo->beginTransaction()) {
        throw new RuntimeException('Не вдалося почати DB transaction для restore.');
    }
    $pdo->exec($validatedBackup['sql']);
    $insertMarker = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (?)');
    $insertMarker->execute([$marker]);
    restore_swap_directories($mappings, $journalPath, $operationId, $marker);
    if (!$pdo->commit()) {
        throw new RuntimeException('Не вдалося commit DB transaction для restore.');
    }
} catch (Throwable $exception) {
    try {
        if ($pdo->inTransaction() && !$pdo->rollBack()) {
            throw new RuntimeException('PDO повернув false під час rollback.');
        }
    } catch (Throwable $rollbackException) {
        app_log_exception($rollbackException, 'Atomic restore DB rollback failed');
    }
    app_log_exception($exception, 'Atomic restore failed');
    fwrite(STDERR, "Restore не завершено: " . $exception->getMessage() . "\n");
    try {
        restore_recover_interrupted_operation($pdo, $journalPath);
        fwrite(STDERR, "Попередню БД та media-файли узгоджено/відновлено за журналом.\n");
    } catch (Throwable $recoveryException) {
        app_log_exception($recoveryException, 'Atomic restore rollback recovery failed');
        fwrite(STDERR, "Автоматичне узгодження не вдалося. Не запускайте сайт; повторно запустіть restore: "
            . $recoveryException->getMessage() . "\n");
    }
    release_media_maintenance_lock($mediaMaintenanceLock);
    $zip->close();
    exit(1);
}

try {
    restore_recover_interrupted_operation($pdo, $journalPath);
} catch (Throwable $exception) {
    app_log_exception($exception, 'Restore post-commit cleanup failed');
    fwrite(STDERR, "Дані відновлено, але cleanup не завершено. Повторно запустіть restore перед запуском сайту: "
        . $exception->getMessage() . "\n");
    release_media_maintenance_lock($mediaMaintenanceLock);
    $zip->close();
    exit(1);
}

$zip->close();
release_media_maintenance_lock($mediaMaintenanceLock);
echo "Базу даних і media-директорії атомарно перемкнено.\n";
echo "Відновлено файлів: {$stagedCount}.\n";
echo "Відновлення успішно завершено!\n";
