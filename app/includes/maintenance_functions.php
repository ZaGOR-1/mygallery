<?php

declare(strict_types=1);

function private_directory_mode(): int
{
    return 0700;
}

function shared_directory_mode(): int
{
    return 0750;
}

function private_file_mode(): int
{
    return 0600;
}

function shared_file_mode(): int
{
    return 0640;
}

function ensure_directory(string $path, int $mode = 0700): bool
{
    if (is_link($path)) {
        return false;
    }

    if (!is_dir($path) && !@mkdir($path, $mode, true)) {
        return false;
    }

    if (!is_dir($path)) {
        return false;
    }

    if (PHP_OS_FAMILY !== 'Windows') {
        @chmod($path, $mode);
    }

    return is_writable($path);
}

function ensure_private_directory(string $path): bool
{
    return ensure_directory($path, private_directory_mode());
}

function ensure_shared_directory(string $path): bool
{
    return ensure_directory($path, shared_directory_mode());
}

function enforce_private_file_permissions(string $path): bool
{
    if (!is_file($path) || is_link($path)) {
        return false;
    }

    if (PHP_OS_FAMILY !== 'Windows') {
        @chmod($path, private_file_mode());
        $permissions = @fileperms($path);
        if (is_int($permissions) && (($permissions & 0077) !== 0)) {
            return false;
        }
    }

    return true;
}

function enforce_shared_file_permissions(string $path): bool
{
    if (!is_file($path) || is_link($path)) {
        return false;
    }

    if (PHP_OS_FAMILY !== 'Windows') {
        @chmod($path, shared_file_mode());
        $permissions = @fileperms($path);
        if (is_int($permissions) && (($permissions & 0007) !== 0)) {
            return false;
        }
    }

    return true;
}

function private_file_put_contents(string $path, string $contents, int $flags = 0): int|false
{
    $directory = dirname($path);
    if (!ensure_private_directory($directory)) {
        return false;
    }

    $written = @file_put_contents($path, $contents, $flags);
    if ($written === false || !enforce_private_file_permissions($path)) {
        return false;
    }

    return $written;
}

/** @return resource|false */
function open_private_file(string $path, string $mode)
{
    if (!ensure_private_directory(dirname($path))) {
        return false;
    }

    $handle = @fopen($path, $mode);
    if ($handle === false) {
        return false;
    }

    if (!enforce_private_file_permissions($path)) {
        fclose($handle);
        return false;
    }

    return $handle;
}

function filesystem_permissions_are_private(string $path, bool $directory = false): bool
{
    if (PHP_OS_FAMILY === 'Windows') {
        return true;
    }

    $permissions = @fileperms($path);
    if (!is_int($permissions)) {
        return false;
    }

    $mask = $directory ? 0007 : 0077;

    return ($permissions & $mask) === 0;
}

/**
 * @return array{deletable:bool,reason:string}
 */
function legacy_original_cleanup_decision(string $legacyPath, ?string $privatePath, bool $dbReferenced): array
{
    if (!$dbReferenced) {
        return ['deletable' => true, 'reason' => 'orphan legacy public original'];
    }

    if (!is_file($legacyPath) || is_link($legacyPath)) {
        return ['deletable' => false, 'reason' => 'legacy original path is not a regular file'];
    }

    if ($privatePath === null || !is_file($privatePath) || is_link($privatePath)) {
        return ['deletable' => false, 'reason' => 'legacy original is the only byte-for-byte copy; migrate it first'];
    }

    $legacyHash = @hash_file('sha256', $legacyPath);
    $privateHash = @hash_file('sha256', $privatePath);
    if (!is_string($legacyHash) || !is_string($privateHash)) {
        return ['deletable' => false, 'reason' => 'could not verify legacy/private SHA-256'];
    }

    if (!hash_equals($legacyHash, $privateHash)) {
        return ['deletable' => false, 'reason' => 'legacy and private originals have different SHA-256'];
    }

    return ['deletable' => true, 'reason' => 'verified duplicate legacy public original'];
}

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
    $handle = open_private_file($path, 'c+');
    if ($handle === false) {
        throw new RuntimeException('Не вдалося відкрити media maintenance lock.');
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
