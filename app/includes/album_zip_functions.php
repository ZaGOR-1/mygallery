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

    if (preg_match('/\A(?:CON|PRN|AUX|NUL|COM[1-9]|LPT[1-9])(?:\.|\z)/i', $stem) === 1) {
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
            'entry_name' => (string) ($photo['entry_name'] ?? ''),
            'source_kind' => (string) ($photo['source_kind'] ?? ''),
            'source_size' => (int) ($photo['source_size'] ?? 0),
            'source_mtime' => (int) ($photo['source_mtime'] ?? 0),
            'source_sha256' => (string) ($photo['source_sha256'] ?? ''),
        ];
    }

    $payload = [
        'writer_version' => 2,
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
    $scope = $token !== '' ? 'share:' . hash('sha256', $token) : 'album:' . $albumId;

    return hash('sha256', album_download_client_ip() . '|' . $scope);
}

function album_download_global_lock_key(): string
{
    return hash('sha256', album_download_client_ip() . '|all-albums');
}

function cleanup_album_download_locks(string $dir, int $olderThanSeconds = 86400): void
{
    if (random_int(1, 100) !== 1) {
        return;
    }

    foreach (glob($dir . DIRECTORY_SEPARATOR . '*.lock') ?: [] as $file) {
        if (is_file($file) && !is_link($file) && (time() - (int) filemtime($file)) > $olderThanSeconds) {
            @unlink($file);
        }
    }
}

/** @param resource $handle */
function read_album_download_cooldown_timestamp($handle): ?int
{
    if (!is_resource($handle) || !@rewind($handle)) {
        return null;
    }
    $contents = stream_get_contents($handle);
    if (!is_string($contents)) {
        return null;
    }
    $contents = trim($contents);
    if ($contents === '') {
        return 0;
    }

    return preg_match('/\A[0-9]+\z/', $contents) === 1 ? (int) $contents : null;
}

/** @param resource $handle */
function write_album_download_cooldown_timestamp($handle, int $timestamp): bool
{
    if (!is_resource($handle) || !@ftruncate($handle, 0) || !@rewind($handle)) {
        return false;
    }

    $payload = (string) $timestamp;
    $offset = 0;
    $length = strlen($payload);
    while ($offset < $length) {
        $written = @fwrite($handle, substr($payload, $offset));
        if (!is_int($written) || $written < 1) {
            return false;
        }
        $offset += $written;
    }

    return @fflush($handle);
}

function enforce_album_download_cooldown_key(string $key, int $cooldownSeconds, string $message): void
{
    $dir = storage_path('download_locks');
    if (!ensure_private_directory($dir)) {
        app_http_error('Папка download rate limit недоступна для запису.', 500);
    }

    $lockPath = $dir . DIRECTORY_SEPARATOR . $key . '.lock';
    $handle = open_private_file($lockPath, 'c+');
    if ($handle === false) {
        app_http_error('Не вдалося перевірити download rate limit.', 500);
    }

    $locked = false;
    $failureMessage = null;
    $failureStatus = 0;
    $retryAfter = null;
    try {
        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            $failureMessage = 'Інше завантаження уже перевіряється. Спробуйте через кілька секунд.';
            $failureStatus = 429;
            $retryAfter = 5;
        } else {
            $locked = true;
            $lastDownload = read_album_download_cooldown_timestamp($handle);
            if ($lastDownload === null) {
                $failureMessage = 'Не вдалося прочитати стан download cooldown.';
                $failureStatus = 503;
                $retryAfter = 30;
            } else {
                $now = time();
                $remaining = $cooldownSeconds - ($now - $lastDownload);
                if ($lastDownload > 0 && $remaining > 0) {
                    $failureMessage = $message . ' ' . $remaining . ' сек.';
                    $failureStatus = 429;
                    $retryAfter = $remaining;
                } elseif (!write_album_download_cooldown_timestamp($handle, $now)) {
                    $failureMessage = 'Не вдалося надійно записати стан download cooldown.';
                    $failureStatus = 503;
                    $retryAfter = 30;
                }
            }
        }
    } finally {
        if (is_resource($handle)) {
            if ($locked) {
                flock($handle, LOCK_UN);
            }
            fclose($handle);
        }
    }

    if ($failureMessage !== null) {
        if (!headers_sent() && is_int($retryAfter) && $retryAfter > 0) {
            header('Retry-After: ' . $retryAfter);
        }
        if ($failureStatus >= 500) {
            app_log('Album ZIP cooldown fail-closed: ' . $failureMessage . ' State: ' . basename($lockPath));
        }
        app_http_error($failureMessage, $failureStatus);
    }
}

function enforce_album_download_cooldown(int $albumId, string $token): void
{
    $dir = storage_path('download_locks');
    if (!ensure_private_directory($dir)) {
        app_http_error('Папка download rate limit недоступна для запису.', 500);
    }
    cleanup_album_download_locks($dir);

    $admin = is_admin_logged_in();
    enforce_album_download_cooldown_key(
        album_download_global_lock_key(),
        $admin ? 5 : 30,
        'Зачекайте перед наступним ZIP-завантаженням з цього IP:'
    );
    enforce_album_download_cooldown_key(
        album_download_lock_key($albumId, $token),
        $admin ? 15 : 90,
        'Зачекайте перед повторним завантаженням цього ZIP:'
    );
}

function album_zip_generation_lock_path(string $cacheKey): string
{
    return storage_path('download_locks') . DIRECTORY_SEPARATOR . 'zip_' . hash('sha256', $cacheKey) . '.lock';
}

/** @return resource */
function acquire_album_zip_generation_lock(string $cacheKey)
{
    $dir = storage_path('download_locks');
    if (!ensure_private_directory($dir)) {
        app_http_error('Папка ZIP lock недоступна для запису.', 500);
    }

    $handle = open_private_file(album_zip_generation_lock_path($cacheKey), 'c+');
    if ($handle === false) {
        app_http_error('Не вдалося створити ZIP lock.', 500);
    }

    if (!flock($handle, LOCK_EX | LOCK_NB)) {
        fclose($handle);
        if (!headers_sent()) {
            header('Retry-After: 10');
        }
        app_http_error('Цей ZIP уже генерується. Спробуйте через кілька секунд.', 429);
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

function album_zip_global_lock_path(): string
{
    return storage_path('download_locks') . DIRECTORY_SEPARATOR . 'zip_global.lock';
}

/** @return resource */
function acquire_album_zip_global_lock()
{
    if (!ensure_private_directory(storage_path('download_locks'))) {
        app_http_error('Папка ZIP lock недоступна для запису.', 500);
    }
    $handle = open_private_file(album_zip_global_lock_path(), 'c+');
    if ($handle === false) {
        app_http_error('Не вдалося створити global ZIP lock.', 500);
    }
    if (!flock($handle, LOCK_EX | LOCK_NB)) {
        fclose($handle);
        if (!headers_sent()) {
            header('Retry-After: 15');
        }
        app_http_error('Сервер уже створює інший ZIP. Спробуйте пізніше.', 429);
    }

    return $handle;
}

function album_zip_cache_metadata_path(string $zipPath): string
{
    return $zipPath . '.json';
}

/** @param list<array{entry_name:string,source_size:int}> $expectedEntries */
function album_zip_cache_is_valid(string $zipPath, string $fingerprint, array $expectedEntries): bool
{
    $metadataPath = album_zip_cache_metadata_path($zipPath);
    if (!is_file($zipPath) || is_link($zipPath) || !is_file($metadataPath) || is_link($metadataPath)) {
        return false;
    }

    $metadata = json_decode((string) @file_get_contents($metadataPath), true);
    $zipSize = filesize($zipPath);
    if (!is_array($metadata)
        || !is_int($zipSize)
        || $zipSize < 1
        || !hash_equals((string) ($metadata['fingerprint'] ?? ''), $fingerprint)
        || (int) ($metadata['zip_size'] ?? -1) !== $zipSize
        || (int) ($metadata['entry_count'] ?? -1) !== count($expectedEntries)) {
        return false;
    }

    $zip = new ZipArchive();
    if ($zip->open($zipPath, ZipArchive::CHECKCONS) !== true) {
        return false;
    }

    try {
        if ($zip->numFiles !== count($expectedEntries)) {
            return false;
        }
        foreach ($expectedEntries as $index => $expected) {
            $name = $zip->getNameIndex($index, ZipArchive::FL_UNCHANGED);
            $stat = $zip->statIndex($index, ZipArchive::FL_UNCHANGED);
            if ($name !== $expected['entry_name']
                || !is_array($stat)
                || (int) ($stat['size'] ?? -1) !== (int) $expected['source_size']) {
                return false;
            }
        }
    } finally {
        $zip->close();
    }

    return true;
}

function write_album_zip_cache_metadata(string $zipPath, string $fingerprint, int $entryCount): bool
{
    $zipSize = filesize($zipPath);
    if (!is_int($zipSize) || $zipSize < 1) {
        return false;
    }

    $payload = json_encode([
        'version' => 1,
        'fingerprint' => $fingerprint,
        'zip_size' => $zipSize,
        'entry_count' => $entryCount,
        'created_at' => gmdate(DATE_ATOM),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

    return private_file_put_contents(album_zip_cache_metadata_path($zipPath), $payload, LOCK_EX) === strlen($payload);
}

function delete_album_zip_cache_pair(string $zipPath, string $context): bool
{
    $zipDeleted = unlink_file_with_log($zipPath, $context);
    $metadataDeleted = unlink_file_with_log(album_zip_cache_metadata_path($zipPath), $context . ' metadata');

    return $zipDeleted && $metadataDeleted;
}

function assert_album_zip_deadline(?float $deadline): void
{
    if ($deadline !== null && microtime(true) >= $deadline) {
        throw new RuntimeException('Перевищено ліміт часу генерації ZIP.');
    }
}

/** @param resource $handle */
function write_album_zip_bytes($handle, string $data, ?float $deadline): void
{
    $length = strlen($data);
    $offset = 0;
    while ($offset < $length) {
        assert_album_zip_deadline($deadline);
        $written = fwrite($handle, substr($data, $offset, 1024 * 1024));
        if ($written === false || $written === 0) {
            throw new RuntimeException('Short write під час створення album ZIP.');
        }
        $offset += $written;
    }
}

/** @return array{0:int,1:int} */
function album_zip_dos_datetime(int $timestamp): array
{
    $parts = getdate($timestamp);
    $year = max(1980, min(2107, (int) $parts['year']));

    return [
        ((int) $parts['hours'] << 11) | ((int) $parts['minutes'] << 5) | (int) floor((int) $parts['seconds'] / 2),
        (($year - 1980) << 9) | ((int) $parts['mon'] << 5) | (int) $parts['mday'],
    ];
}

/**
 * Writes an uncompressed ZIP incrementally. JPEG payloads do not benefit from
 * another deflate pass, and chunk-level deadline checks make the request budget
 * enforceable without a long, deferred ZipArchive::close() operation.
 *
 * @param list<array{path:string,entry_name:string,source_size:int}> $files
 * @return list<array{entry_name:string,source_size:int,source_sha256:string}>
 */
function write_album_zip_file(string $path, array $files, float $deadline): array
{
    $handle = fopen($path, 'wb');
    if ($handle === false || !enforce_private_file_permissions($path)) {
        if (is_resource($handle)) {
            fclose($handle);
        }
        throw new RuntimeException('Не вдалося відкрити безпечний album ZIP для запису.');
    }

    $centralEntries = [];
    $verifiedEntries = [];
    $completed = false;
    try {
        foreach ($files as $file) {
            assert_album_zip_deadline($deadline);
            $sourcePath = (string) $file['path'];
            $entryName = (string) $file['entry_name'];
            $expectedSize = (int) $file['source_size'];
            if (!is_file($sourcePath) || is_link($sourcePath) || !is_readable($sourcePath)
                || $entryName === '' || str_contains($entryName, '/') || str_contains($entryName, '\\')) {
                throw new RuntimeException('Некоректне джерело або назва album ZIP entry.');
            }

            $offset = ftell($handle);
            if (!is_int($offset) || $offset < 0 || $offset > 0xffffffff) {
                throw new RuntimeException('Album ZIP перевищує classic ZIP offset limit.');
            }
            $mtime = filemtime($sourcePath);
            [$dosTime, $dosDate] = album_zip_dos_datetime(is_int($mtime) ? $mtime : time());
            $nameLength = strlen($entryName);
            // Bit 3 announces a trailing descriptor; bit 11 declares UTF-8 names.
            $generalPurposeFlags = 0x0808;
            write_album_zip_bytes($handle, pack(
                'VvvvvvVVVvv',
                0x04034b50,
                20,
                $generalPurposeFlags,
                0,
                $dosTime,
                $dosDate,
                0,
                0,
                0,
                $nameLength,
                0
            ) . $entryName, $deadline);

            $source = fopen($sourcePath, 'rb');
            if ($source === false) {
                throw new RuntimeException('Не вдалося відкрити photo source для album ZIP.');
            }
            $sha256 = hash_init('sha256');
            $crc32 = hash_init('crc32b');
            $size = 0;
            try {
                while (!feof($source)) {
                    assert_album_zip_deadline($deadline);
                    $chunk = fread($source, 1024 * 1024);
                    if ($chunk === false || ($chunk === '' && !feof($source))) {
                        throw new RuntimeException('Помилка читання photo source для album ZIP.');
                    }
                    if ($chunk === '') {
                        break;
                    }
                    $size += strlen($chunk);
                    if ($size > $expectedSize) {
                        throw new RuntimeException('Photo source змінився під час генерації album ZIP.');
                    }
                    hash_update($sha256, $chunk);
                    hash_update($crc32, $chunk);
                    write_album_zip_bytes($handle, $chunk, $deadline);
                }
            } finally {
                fclose($source);
            }
            if ($size !== $expectedSize || $size > 0xffffffff) {
                throw new RuntimeException('Photo source має неочікуваний розмір під час генерації album ZIP.');
            }

            $crc = (int) hexdec(hash_final($crc32));
            write_album_zip_bytes($handle, pack('VVVV', 0x08074b50, $crc, $size, $size), $deadline);
            $centralEntries[] = compact('entryName', 'nameLength', 'dosTime', 'dosDate', 'crc', 'size', 'offset');
            $verifiedEntries[] = [
                'entry_name' => $entryName,
                'source_size' => $size,
                'source_sha256' => hash_final($sha256),
            ];
        }

        $centralOffset = ftell($handle);
        if (!is_int($centralOffset) || $centralOffset < 0 || $centralOffset > 0xffffffff) {
            throw new RuntimeException('Некоректний album ZIP central-directory offset.');
        }
        foreach ($centralEntries as $entry) {
            write_album_zip_bytes($handle, pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,
                0x031e,
                20,
                $generalPurposeFlags,
                0,
                $entry['dosTime'],
                $entry['dosDate'],
                $entry['crc'],
                $entry['size'],
                $entry['size'],
                $entry['nameLength'],
                0,
                0,
                0,
                0,
                0,
                $entry['offset']
            ) . $entry['entryName'], $deadline);
        }
        $centralEnd = ftell($handle);
        $centralSize = is_int($centralEnd) ? $centralEnd - $centralOffset : -1;
        $count = count($centralEntries);
        if ($centralSize < 0 || $centralSize > 0xffffffff || $count > 0xffff) {
            throw new RuntimeException('Album ZIP перевищує classic ZIP directory limits.');
        }
        write_album_zip_bytes($handle, pack(
            'VvvvvVVv',
            0x06054b50,
            0,
            0,
            $count,
            $count,
            $centralSize,
            $centralOffset,
            0
        ), $deadline);
        assert_album_zip_deadline($deadline);
        if (!fflush($handle)) {
            throw new RuntimeException('Не вдалося flush album ZIP.');
        }
        if (!fclose($handle)) {
            throw new RuntimeException('Не вдалося закрити album ZIP.');
        }
        $handle = null;
        assert_album_zip_deadline($deadline);
        $completed = true;
    } finally {
        if (is_resource($handle)) {
            fclose($handle);
        }
        if (!$completed && is_file($path)) {
            unlink_file_with_log($path, 'Incomplete streamed album ZIP cleanup');
        }
    }

    assert_album_zip_deadline($deadline);

    return $verifiedEntries;
}

/**
 * Expensive full verification. Use only immediately after generating a new ZIP.
 * @param list<array{entry_name:string,source_size:int,source_sha256:string}> $expectedEntries
 */
function verify_album_zip_file(string $path, array $expectedEntries, ?float $deadline = null): bool
{
    assert_album_zip_deadline($deadline);
    if (!is_file($path) || filesize($path) === 0) {
        return false;
    }

    $zip = new ZipArchive();
    if ($zip->open($path, ZipArchive::CHECKCONS) !== true) {
        return false;
    }

    try {
        if ($zip->numFiles !== count($expectedEntries)) {
            return false;
        }

        foreach ($expectedEntries as $index => $expected) {
            assert_album_zip_deadline($deadline);
            $name = $zip->getNameIndex($index, ZipArchive::FL_UNCHANGED);
            $stat = $zip->statIndex($index, ZipArchive::FL_UNCHANGED);
            if ($name !== $expected['entry_name']
                || !is_array($stat)
                || (int) ($stat['size'] ?? -1) !== $expected['source_size']) {
                return false;
            }

            $stream = $zip->getStream($expected['entry_name']);
            if ($stream === false) {
                return false;
            }
            $hash = hash_init('sha256');
            $size = 0;
            try {
                while (!feof($stream)) {
                    assert_album_zip_deadline($deadline);
                    $chunk = fread($stream, 1024 * 1024);
                    if ($chunk === false) {
                        return false;
                    }
                    if ($chunk === '') {
                        if (!feof($stream)) {
                            return false;
                        }
                        break;
                    }
                    $size += strlen($chunk);
                    hash_update($hash, $chunk);
                }
            } finally {
                fclose($stream);
            }

            if ($size !== $expected['source_size']
                || !hash_equals($expected['source_sha256'], hash_final($hash))) {
                return false;
            }
        }
    } finally {
        $zip->close();
    }

    assert_album_zip_deadline($deadline);

    return true;
}

function enforce_album_zip_cache_quota(string $directory, int $maximumBytes, int $incomingBytes = 0, ?string $preserve = null): void
{
    if ($maximumBytes < 1 || $incomingBytes > $maximumBytes) {
        throw new RuntimeException('ZIP перевищує дозволену квоту кешу.');
    }

    $files = [];
    $total = 0;
    foreach (glob($directory . DIRECTORY_SEPARATOR . '*.zip') ?: [] as $path) {
        if (!is_file($path) || is_link($path)) {
            continue;
        }
        $size = filesize($path);
        $mtime = filemtime($path);
        if (!is_int($size) || !is_int($mtime)) {
            continue;
        }
        $files[] = ['path' => $path, 'size' => $size, 'mtime' => $mtime];
        $total += $size;
    }

    usort($files, static fn (array $left, array $right): int => $left['mtime'] <=> $right['mtime']);
    $target = max(0, $maximumBytes - $incomingBytes);
    foreach ($files as $file) {
        if ($total <= $target) {
            break;
        }
        if ($preserve !== null && same_filesystem_path($file['path'], $preserve)) {
            continue;
        }
        if (!delete_album_zip_cache_pair($file['path'], 'Album ZIP cache quota eviction')) {
            throw new RuntimeException('Не вдалося звільнити ZIP cache quota.');
        }
        $total -= $file['size'];
    }

    if ($total > $target) {
        throw new RuntimeException('Недостатньо вільної ZIP cache quota.');
    }
}

/** @return resource */
function acquire_album_zip_stream_slot()
{
    $dir = storage_path('download_locks');
    if (!ensure_private_directory($dir)) {
        app_http_error('Папка ZIP stream lock недоступна для запису.', 500);
    }

    $maximum = max(1, min(10, (int) (app_config()['ALBUM_ZIP_MAX_CONCURRENT_STREAMS'] ?? 2)));
    for ($slot = 0; $slot < $maximum; $slot++) {
        $path = $dir . DIRECTORY_SEPARATOR . 'zip_stream_' . $slot . '.lock';
        $handle = open_private_file($path, 'c+');
        if ($handle !== false && flock($handle, LOCK_EX | LOCK_NB)) {
            return $handle;
        }
        if (is_resource($handle)) {
            fclose($handle);
        }
    }

    if (!headers_sent()) {
        header('Retry-After: 15');
    }
    app_http_error('Сервер уже передає максимальну кількість ZIP-архівів. Спробуйте пізніше.', 429);
}

function stream_album_zip_file(string $path, string $archiveName, bool $deleteAfterStream = false, bool $sensitive = true): never
{
    $downloadSize = filesize($path);
    $downloadMtime = filemtime($path);
    if (!is_int($downloadSize) || $downloadSize < 1 || !is_int($downloadMtime)) {
        if ($deleteAfterStream) {
            delete_album_zip_cache_pair($path, 'Album ZIP unreadable cleanup');
        }
        app_http_error('Не вдалося прочитати ZIP-архів.', 500);
    }

    $streamSlot = acquire_album_zip_stream_slot();
    $start = 0;
    $end = $downloadSize - 1;
    $status = 200;
    $etag = '"' . hash('sha256', basename($path) . '|' . $downloadSize . '|' . $downloadMtime) . '"';
    $lastModified = gmdate('D, d M Y H:i:s', $downloadMtime) . ' GMT';
    $rangeHeader = is_string($_SERVER['HTTP_RANGE'] ?? null) ? trim($_SERVER['HTTP_RANGE']) : '';
    $ifRange = is_string($_SERVER['HTTP_IF_RANGE'] ?? null) ? trim($_SERVER['HTTP_IF_RANGE']) : '';

    if ($rangeHeader !== '' && ($ifRange === '' || $ifRange === $etag || $ifRange === $lastModified)) {
        if (preg_match('/\Abytes=(\d*)-(\d*)\z/', $rangeHeader, $matches) !== 1) {
            release_album_zip_generation_lock($streamSlot);
            http_response_code(416);
            header('Content-Range: bytes */' . $downloadSize);
            exit;
        }
        if ($matches[1] === '') {
            $suffix = min($downloadSize, max(0, (int) $matches[2]));
            if ($suffix < 1) {
                release_album_zip_generation_lock($streamSlot);
                http_response_code(416);
                header('Content-Range: bytes */' . $downloadSize);
                exit;
            }
            $start = $downloadSize - $suffix;
        } else {
            $start = (int) $matches[1];
            if ($start >= $downloadSize) {
                release_album_zip_generation_lock($streamSlot);
                http_response_code(416);
                header('Content-Range: bytes */' . $downloadSize);
                exit;
            }
            if ($matches[2] !== '') {
                $end = min($end, (int) $matches[2]);
            }
            if ($end < $start) {
                release_album_zip_generation_lock($streamSlot);
                http_response_code(416);
                header('Content-Range: bytes */' . $downloadSize);
                exit;
            }
        }
        $status = 206;
    }

    $length = $end - $start + 1;
    $handle = @fopen($path, 'rb');
    if ($handle === false || fseek($handle, $start) !== 0) {
        if (is_resource($handle)) {
            fclose($handle);
        }
        release_album_zip_generation_lock($streamSlot);
        app_http_error('Не вдалося відкрити ZIP-архів для передачі.', 500);
    }

    send_security_headers();
    http_response_code($status);
    header('Content-Type: application/zip');
    $asciiName = preg_replace('/[^A-Za-z0-9._-]+/', '_', $archiveName) ?: 'album';
    header("Content-Disposition: attachment; filename=\"{$asciiName}.zip\"; filename*=UTF-8''" . rawurlencode($archiveName . '.zip'));
    header('Accept-Ranges: bytes');
    header('ETag: ' . $etag);
    header('Last-Modified: ' . $lastModified);
    header('Content-Length: ' . $length);
    if ($status === 206) {
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $downloadSize);
    }
    foreach (album_zip_response_cache_headers($sensitive) as $cacheHeader) {
        header($cacheHeader);
    }

    $remaining = $length;
    try {
        while ($remaining > 0 && !feof($handle)) {
            $chunk = fread($handle, min(1024 * 1024, $remaining));
            if ($chunk === false || $chunk === '') {
                break;
            }
            echo $chunk;
            $remaining -= strlen($chunk);
        }
    } finally {
        fclose($handle);
        release_album_zip_generation_lock($streamSlot);
    }

    if ($deleteAfterStream) {
        delete_album_zip_cache_pair($path, 'Album ZIP one-shot cleanup');
    }
    exit;
}

function safe_zip_filename(string $albumName): string
{
    $safe = preg_replace('/[^\p{L}\p{N}\s\-_.]/u', '', $albumName);
    $safe = trim((string) $safe);

    return $safe === '' ? 'album' : $safe;
}
