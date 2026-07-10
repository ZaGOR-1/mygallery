<?php

declare(strict_types=1);

function safe_zip_entry_filename(string $originalName, int $photoId): string
{
    $name = safe_original_name($originalName);
    $lastDot = strrpos($name, '.');
    $stem = $lastDot === false ? $name : substr($name, 0, $lastDot);
    $stem = preg_replace('/[^\p{L}\p{N}\p{M} _().\-\[\]]+/u', '_', $stem);
    if (!is_string($stem)) {
        $stem = '';
    }
    $stem = preg_replace('/\s+/u', ' ', $stem) ?? $stem;
    $stem = trim($stem, " ._-");
    $stem = mb_strcut($stem, 0, 180, 'UTF-8');

    if ($stem === '' || $stem === '.' || $stem === '..') {
        $stem = 'photo_' . max(1, $photoId);
    }

    if (preg_match('/\A(?:CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])\z/i', $stem) === 1) {
        $stem = '_' . $stem;
    }

    return $stem . '.jpg';
}

/**
 * @param array<string, true> $usedNames
 */
function reserve_unique_zip_entry_filename(string $safeName, array &$usedNames): string
{
    $base = pathinfo($safeName, PATHINFO_FILENAME);
    $extension = pathinfo($safeName, PATHINFO_EXTENSION);
    $candidate = $safeName;
    $counter = 1;

    while (isset($usedNames[mb_strtolower($candidate, 'UTF-8')])) {
        $candidate = $base . '_' . $counter . ($extension !== '' ? '.' . $extension : '');
        $counter++;
    }

    $usedNames[mb_strtolower($candidate, 'UTF-8')] = true;

    return $candidate;
}

function album_zip_cache_fingerprint(int $albumId, string $albumName, string $variant, string $scope, array $photos): string
{
    $items = [];

    foreach ($photos as $photo) {
        $items[] = [
            'id' => (int) ($photo['id'] ?? 0),
            'filename' => (string) ($photo['filename'] ?? ''),
            'original_name' => (string) ($photo['original_name'] ?? ''),
            'original_sha256' => (string) ($photo['original_sha256'] ?? ''),
            'file_size' => (int) ($photo['file_size'] ?? 0),
            'created_at' => (string) ($photo['created_at'] ?? ''),
            'updated_at' => (string) ($photo['updated_at'] ?? ''),
        ];
    }

    $payload = [
        'album_id' => $albumId,
        'album_name' => $albumName,
        'variant' => $variant,
        'scope' => $scope,
        'photos' => $items,
    ];

    return hash('sha256', (string) json_encode($payload, JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/**
 * @return list<string>
 */
function album_zip_response_cache_headers(bool $sensitive, ?int $now = null): array
{
    if ($sensitive) {
        return [
            'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0',
            'Pragma: no-cache',
            'Expires: 0',
        ];
    }

    $now ??= time();

    return [
        'Cache-Control: public, max-age=300',
        'Pragma: public',
        'Expires: ' . gmdate('D, d M Y H:i:s \\G\\M\\T', $now + 300),
    ];
}

function album_download_client_ip(): string
{
    return client_ip(default: 'cli');
}

function album_download_lock_key(int $albumId, string $token): string
{
    // User-Agent is client-controlled and would allow a fresh cooldown bucket.
    $scope = $token !== '' ? 'share:' . hash('sha256', $token) : 'album:' . $albumId;

    return hash('sha256', album_download_client_ip() . '|' . $scope);
}

function cleanup_album_download_locks(string $dir, int $olderThanSeconds = 86400): void
{
    if (random_int(1, 100) !== 1) {
        return;
    }

    foreach (glob($dir . DIRECTORY_SEPARATOR . '*.lock') ?: [] as $file) {
        if (is_file($file) && (time() - (int) filemtime($file)) > $olderThanSeconds) {
            @unlink($file);
        }
    }
}

function enforce_album_download_cooldown(int $albumId, string $token): void
{
    $dir = storage_path('download_locks');
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        app_http_error('Не вдалося створити папку для download rate limit.', 500);
    }

    if (!is_writable($dir)) {
        app_http_error('Папка download rate limit недоступна для запису.', 500);
    }

    cleanup_album_download_locks($dir);

    $cooldownSeconds = is_admin_logged_in() ? 15 : 90;
    $lockPath = $dir . DIRECTORY_SEPARATOR . album_download_lock_key($albumId, $token) . '.lock';
    $handle = fopen($lockPath, 'c+');
    if ($handle === false) {
        app_http_error('Не вдалося перевірити download rate limit.', 500);
    }

    try {
        if (!flock($handle, LOCK_EX)) {
            app_http_error('Не вдалося заблокувати download rate limit.', 500);
        }

        $lastDownload = (int) trim((string) stream_get_contents($handle));
        $now = time();
        $remaining = $cooldownSeconds - ($now - $lastDownload);

        if ($lastDownload > 0 && $remaining > 0) {
            flock($handle, LOCK_UN);
            fclose($handle);
            if (!headers_sent()) {
                header('Retry-After: ' . $remaining);
            }
            app_http_error('Зачекайте ' . $remaining . ' сек. перед повторним завантаженням ZIP.', 429);
        }

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, (string) $now);
        fflush($handle);
        flock($handle, LOCK_UN);
    } finally {
        if (is_resource($handle)) {
            fclose($handle);
        }
    }
}

function album_zip_generation_lock_path(string $cacheKey): string
{
    return storage_path('download_locks') . DIRECTORY_SEPARATOR . 'zip_' . hash('sha256', $cacheKey) . '.lock';
}

/** @return resource */
function acquire_album_zip_generation_lock(string $cacheKey)
{
    $dir = storage_path('download_locks');
    if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
        app_http_error('Не вдалося створити папку для ZIP lock.', 500);
    }

    if (!is_writable($dir)) {
        app_http_error('Папка ZIP lock недоступна для запису.', 500);
    }

    $handle = fopen(album_zip_generation_lock_path($cacheKey), 'c+');
    if ($handle === false) {
        app_http_error('Не вдалося створити ZIP lock.', 500);
    }

    if (!flock($handle, LOCK_EX)) {
        fclose($handle);
        app_http_error('Не вдалося заблокувати генерацію ZIP.', 500);
    }

    return $handle;
}

function release_album_zip_generation_lock(mixed $handle): void
{
    if (is_resource($handle)) {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}

function stream_album_zip_file(string $path, string $archiveName, bool $deleteAfterStream = false, bool $sensitive = true): never
{
    $downloadSize = filesize($path);
    if ($downloadSize === false) {
        if ($deleteAfterStream) {
            unlink_file_with_log($path, 'Album ZIP temp cleanup');
        }

        app_http_error('Не вдалося прочитати ZIP-архів.', 500);
    }

    send_security_headers();
    header('Content-Type: application/zip');
    header('Content-Disposition: attachment; filename="' . rawurlencode($archiveName) . '.zip"');
    header('Content-Length: ' . $downloadSize);
    foreach (album_zip_response_cache_headers($sensitive) as $cacheHeader) {
        header($cacheHeader);
    }

    readfile($path);
    if ($deleteAfterStream) {
        unlink_file_with_log($path, 'Album ZIP temp cleanup');
    }
    exit;
}

function safe_zip_filename(string $albumName): string
{
    $safe = preg_replace('/[^\p{L}\p{N}\s\-_.]/u', '', $albumName);
    $safe = trim((string) $safe);

    return $safe === '' ? 'album' : $safe;
}
