<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'photo_service.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Цей файл запускається тільки з консолі.');
}

$previousUmask = umask(0077);
$apply = in_array('--apply', $argv, true);
$now = time();
$deletedFiles = 0;
$freedBytes = 0;
$unsafeEntries = 0;
$operationErrors = 0;
$busySkipped = 0;
$maintenanceLock = null;

/** @return bool true when the candidate was/would be removed */
function cleanup_runtime_file(string $path, string $root, int $olderThan, bool $apply, int $now): bool
{
    global $deletedFiles, $freedBytes, $unsafeEntries, $operationErrors, $busySkipped;

    if (!is_file($path) || is_link($path) || !filesystem_path_is_safe_child($path, $root)) {
        $unsafeEntries++;
        return false;
    }
    $mtime = filemtime($path);
    $size = filesize($path);
    if (!is_int($mtime) || ($now - $mtime) <= $olderThan) {
        return false;
    }

    if (!$apply) {
        echo '[DRY RUN] Видалити: ' . $path . ' (вік ' . round(($now - $mtime) / 86400, 1) . " днів)\n";
        return true;
    }

    // Do not unlink a session/rate-limit/lock file currently held by another process.
    $handle = @fopen($path, 'c+');
    if ($handle === false) {
        $operationErrors++;
        fwrite(STDERR, "Не вдалося відкрити runtime-файл для очищення: {$path}\n");
        return false;
    }
    if (!flock($handle, LOCK_EX | LOCK_NB)) {
        $busySkipped++;
        fclose($handle);
        echo "Пропущено зайнятий runtime-файл: {$path}\n";
        return false;
    }
    $removed = @unlink($path);
    flock($handle, LOCK_UN);
    fclose($handle);

    if ($removed) {
        $deletedFiles++;
        $freedBytes += is_int($size) ? $size : 0;
    } else {
        $operationErrors++;
        fwrite(STDERR, "Не вдалося видалити runtime-файл: {$path}\n");
    }

    return $removed;
}

function cleanup_runtime_tree(string $root, int $olderThan, bool $apply, int $now, array $protectedNames = []): int
{
    if (!is_dir($root)) {
        return 0;
    }
    if (!filesystem_path_is_safe_child($root, dirname($root))) {
        fwrite(STDERR, "Небезпечна runtime-директорія: {$root}\n");
        $GLOBALS['unsafeEntries']++;
        return 0;
    }

    $matched = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $fileInfo) {
        $path = $fileInfo->getPathname();
        if (in_array($fileInfo->getFilename(), $protectedNames, true)) {
            continue;
        }
        if ($fileInfo->isFile() && cleanup_runtime_file($path, $root, $olderThan, $apply, $now)) {
            $matched++;
        } elseif ($apply && $fileInfo->isDir() && filesystem_path_is_safe_child($path, $root)) {
            @rmdir($path);
        }
    }

    return $matched;
}

function trash_manifest_timestamp(string $manifestPath): int
{
    $raw = @file_get_contents($manifestPath);
    $manifest = is_string($raw) ? json_decode($raw, true) : null;
    if (is_array($manifest)) {
        foreach (['deleted_at', 'created_at'] as $key) {
            $timestamp = strtotime((string) ($manifest[$key] ?? ''));
            if (is_int($timestamp)) {
                return $timestamp;
            }
        }
    }
    $mtime = filemtime($manifestPath);

    return is_int($mtime) ? $mtime : time();
}

function estimate_trash_operation_bytes(string $manifestPath): int
{
    $total = (int) (@filesize($manifestPath) ?: 0);
    $manifest = json_decode((string) @file_get_contents($manifestPath), true);
    foreach (is_array($manifest['files'] ?? null) ? $manifest['files'] : [] as $entry) {
        $resolved = resolve_trash_manifest_entry((array) $entry);
        if ($resolved !== null && is_file($resolved['trash'])) {
            $total += (int) (@filesize($resolved['trash']) ?: 0);
        }
    }

    return $total;
}

try {
    if ($apply) {
        $maintenanceLock = acquire_media_maintenance_lock(LOCK_EX);
    }

    echo "Запуск manifest-aware runtime cleanup...\n";
    $groups = [
        'logs' => [storage_path('logs'), 30 * 86400, ['.gitkeep', '.htaccess']],
        'sessions' => [storage_path('sessions'), 7 * 86400, ['.gitkeep', '.htaccess']],
        'share_ratelimit' => [storage_path('share_ratelimit'), 2 * 86400, ['.gitkeep', '.htaccess', '.shard.lock']],
        'download_locks' => [storage_path('download_locks'), 2 * 86400, ['.gitkeep', '.htaccess', 'zip_global.lock']],
    ];
    foreach ($groups as $name => [$root, $age, $protected]) {
        $count = cleanup_runtime_tree($root, $age, $apply, $now, $protected);
        if ($count > 0) {
            echo ucfirst($name) . ': ' . ($apply ? 'видалено ' : 'знайдено ') . $count . " файлів.\n";
        }
    }

    // Trash photo operations are purged as atomic manifest-defined units.
    $trashRoot = trash_path();
    $trashMaxAge = 7 * 86400;
    recover_interrupted_trash_manifest_updates();
    foreach (glob($trashRoot . DIRECTORY_SEPARATOR . '*.json') ?: [] as $manifestPath) {
        if (is_link($manifestPath) || !filesystem_path_is_safe_child($manifestPath, $trashRoot)) {
            $unsafeEntries++;
            continue;
        }
        $operationId = pathinfo($manifestPath, PATHINFO_FILENAME);
        if (preg_match('/\A[a-f0-9]{32}\z/', $operationId) !== 1 || ($now - trash_manifest_timestamp($manifestPath)) <= $trashMaxAge) {
            continue;
        }

        $estimatedBytes = estimate_trash_operation_bytes($manifestPath);
        if (!$apply) {
            echo "[DRY RUN] Purge trash operation: {$operationId}\n";
            continue;
        }
        try {
            purge_photo_from_trash_unlocked($operationId);
            $deletedFiles++;
            $freedBytes += $estimatedBytes;
        } catch (Throwable $exception) {
            $operationErrors++;
            fwrite(STDERR, "Не вдалося атомарно очистити {$operationId}: {$exception->getMessage()}\n");
        }
    }

    // ZIP cache is independent from photo recovery manifests.
    $zipCache = $trashRoot . DIRECTORY_SEPARATOR . 'zip_cache';
    foreach (glob($zipCache . DIRECTORY_SEPARATOR . '*.zip') ?: [] as $zipPath) {
        $mtime = filemtime($zipPath);
        if (!is_int($mtime) || ($now - $mtime) <= $trashMaxAge) {
            continue;
        }
        $size = (int) (@filesize($zipPath) ?: 0) + (int) (@filesize(album_zip_cache_metadata_path($zipPath)) ?: 0);
        if (!$apply) {
            echo "[DRY RUN] Видалити ZIP cache: {$zipPath}\n";
        } elseif (delete_album_zip_cache_pair($zipPath, 'Runtime ZIP cache cleanup')) {
            $deletedFiles++;
            $freedBytes += $size;
        } else {
            $operationErrors++;
            fwrite(STDERR, "Не вдалося видалити ZIP cache pair: {$zipPath}\n");
        }
    }

    // Only known temporary ZIP names may be removed from the trash root.
    foreach (glob($trashRoot . DIRECTORY_SEPARATOR . 'album_zip_*') ?: [] as $tempPath) {
        cleanup_runtime_file($tempPath, $trashRoot, $trashMaxAge, $apply, $now);
    }

    if (!$apply) {
        echo "\nDRY RUN. Додайте --apply для фактичного очищення.\n";
    } else {
        echo "\nОчищення завершено. Файлів/операцій: {$deletedFiles}; звільнено: "
            . round($freedBytes / 1024 / 1024, 2) . " MB.\n";
    }
} finally {
    release_media_maintenance_lock($maintenanceLock);
    umask($previousUmask);
}

if ($busySkipped > 0) {
    fwrite(STDERR, "Пропущено зайнятих runtime-файлів: {$busySkipped}.\n");
}
if ($unsafeEntries > 0) {
    fwrite(STDERR, "Виявлено небезпечних filesystem entries: {$unsafeEntries}. Їх не змінено.\n");
}
if ($operationErrors > 0) {
    fwrite(STDERR, "Невдалих запитаних filesystem-операцій: {$operationErrors}.\n");
}
if ($unsafeEntries > 0 || $operationErrors > 0) {
    exit(1);
}

exit(0);
