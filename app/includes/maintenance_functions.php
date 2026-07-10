<?php

declare(strict_types=1);

function filesystem_path_is_within(string $candidate, string $base): bool
{
    $candidate = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $candidate), DIRECTORY_SEPARATOR);
    $base = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $base), DIRECTORY_SEPARATOR);
    if (PHP_OS_FAMILY === 'Windows') {
        $candidate = strtolower($candidate);
        $base = strtolower($base);
    }

    return $candidate === $base || str_starts_with($candidate, $base . DIRECTORY_SEPARATOR);
}

function filesystem_path_is_safe_child(string $path, string $baseDirectory): bool
{
    if (is_link($path) || is_link($baseDirectory)) {
        return false;
    }

    $baseReal = realpath($baseDirectory);
    $baseParentReal = realpath(dirname($baseDirectory));
    if ($baseReal === false || $baseParentReal === false) {
        return false;
    }

    $expectedBase = $baseParentReal . DIRECTORY_SEPARATOR . basename($baseDirectory);
    if (!same_filesystem_path($baseReal, $expectedBase)) {
        // Refuse a symlink/junction/reparse-point runtime root.
        return false;
    }

    $parentReal = realpath(dirname($path));
    if ($parentReal === false || !filesystem_path_is_within($parentReal, $baseReal)) {
        return false;
    }

    if (file_exists($path)) {
        $pathReal = realpath($path);
        if ($pathReal === false || !filesystem_path_is_within($pathReal, $baseReal)) {
            return false;
        }
    }

    return true;
}

function media_maintenance_lock_path(): string
{
    return storage_path('media_maintenance.lock');
}

/** @return resource */
function acquire_media_maintenance_lock(int $operation = LOCK_SH)
{
    if ($operation !== LOCK_SH && $operation !== LOCK_EX) {
        throw new InvalidArgumentException('Некоректний режим media maintenance lock.');
    }

    $path = media_maintenance_lock_path();
    $handle = fopen($path, 'c+');
    if ($handle === false) {
        throw new RuntimeException('Не вдалося відкрити media maintenance lock.');
    }
    if (PHP_OS_FAMILY !== 'Windows') {
        chmod($path, 0600);
    }
    if (!flock($handle, $operation)) {
        fclose($handle);
        throw new RuntimeException('Не вдалося отримати media maintenance lock.');
    }

    return $handle;
}

function release_media_maintenance_lock(mixed $handle): void
{
    if (is_resource($handle)) {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
