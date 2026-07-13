<?php

declare(strict_types=1);

function valid_share_token(string $token): bool
{
    return preg_match('/\A[a-f0-9]{32}\z/', $token) === 1;
}

function share_token_hash(string $token): string
{
    return hash('sha256', $token);
}

function share_token_hint(string $token): string
{
    return substr($token, -8);
}

function find_share_link_by_token(string $token): ?array
{
    if (!valid_share_token($token)) {
        return null;
    }

    $stmt = db()->prepare('SELECT * FROM share_links WHERE token_hash = ? LIMIT 1');
    $stmt->execute([share_token_hash($token)]);
    $share = $stmt->fetch();

    return is_array($share) ? $share : null;
}

function share_link_is_expired(array $share, ?int $now = null): bool
{
    if (empty($share['expires_at'])) {
        return false;
    }

    $expiresAt = strtotime((string) $share['expires_at']);

    return $expiresAt === false || $expiresAt < ($now ?? time());
}

function share_rate_limit_failure(string $message): void
{
    app_log($message);
    if (is_production()) {
        app_http_error('Служба приватних посилань тимчасово недоступна. Спробуйте пізніше.', 503);
    }
}

/**
 * Bounded, sharded file limiter. Every shard has a hard inode quota and stale
 * buckets are removed while holding a shard-wide lock.
 */
function enforce_share_request_rate_limit(
    string $scope = 'page',
    ?int $maximumRequests = null,
    ?int $windowSeconds = null
): void {
    $config = app_config();
    $maximumRequests ??= max(1, (int) ($config['SHARE_RATE_LIMIT_MAX_REQUESTS'] ?? 120));
    $windowSeconds ??= max(1, (int) ($config['SHARE_RATE_LIMIT_WINDOW_SECONDS'] ?? 60));
    $ttlSeconds = max($windowSeconds * 2, (int) ($config['SHARE_RATE_LIMIT_TTL_SECONDS'] ?? 172800));
    $maxFilesPerShard = max(16, min(2048, (int) ($config['SHARE_RATE_LIMIT_MAX_FILES_PER_SHARD'] ?? 256)));

    $ip = client_ip();
    $bucketHash = hash('sha256', $scope . '|' . $ip);
    $root = storage_path('share_ratelimit');
    $shard = substr($bucketHash, 0, 2);
    $directory = $root . DIRECTORY_SEPARATOR . $shard;

    if (!ensure_private_directory($root) || !ensure_private_directory($directory)) {
        share_rate_limit_failure('Share rate limit storage is not writable: ' . $directory);
        return;
    }

    $shardLock = open_private_file($directory . DIRECTORY_SEPARATOR . '.shard.lock', 'c+');
    if ($shardLock === false || !flock($shardLock, LOCK_EX)) {
        if (is_resource($shardLock)) {
            fclose($shardLock);
        }
        share_rate_limit_failure('Share rate limit shard lock failed: ' . $directory);
        return;
    }

    $bucketPath = $directory . DIRECTORY_SEPARATOR . substr($bucketHash, 2) . '.json';
    $now = time();
    $rateLimited = false;

    try {
        $bucketFiles = glob($directory . DIRECTORY_SEPARATOR . '*.json') ?: [];
        $mustClean = count($bucketFiles) >= $maxFilesPerShard || random_int(1, 50) === 1;
        if ($mustClean) {
            foreach ($bucketFiles as $path) {
                if (!is_file($path) || is_link($path)) {
                    continue;
                }
                $mtime = filemtime($path);
                if (is_int($mtime) && ($now - $mtime) > $ttlSeconds) {
                    @unlink($path);
                }
            }
            $bucketFiles = glob($directory . DIRECTORY_SEPARATOR . '*.json') ?: [];
        }

        if (!is_file($bucketPath) && count($bucketFiles) >= $maxFilesPerShard) {
            share_rate_limit_failure('Share rate limit shard quota reached: ' . $shard);
            return;
        }

        $handle = open_private_file($bucketPath, 'c+');
        if ($handle === false) {
            share_rate_limit_failure('Share rate limit bucket could not be opened: ' . $bucketPath);
            return;
        }

        try {
            if (!flock($handle, LOCK_EX)) {
                share_rate_limit_failure('Share rate limit bucket lock failed: ' . $bucketPath);
                return;
            }

            rewind($handle);
            $raw = stream_get_contents($handle);
            $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
            $startedAt = is_array($decoded) ? (int) ($decoded['started_at'] ?? 0) : 0;
            $count = is_array($decoded) ? (int) ($decoded['count'] ?? 0) : 0;

            if ($startedAt < 1 || ($now - $startedAt) >= $windowSeconds) {
                $startedAt = $now;
                $count = 1;
            } else {
                $count++;
            }

            $payload = json_encode(
                ['version' => 1, 'started_at' => $startedAt, 'count' => $count],
                JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
            );
            ftruncate($handle, 0);
            rewind($handle);
            if (fwrite($handle, $payload) !== strlen($payload) || !fflush($handle)) {
                share_rate_limit_failure('Share rate limit bucket write failed: ' . $bucketPath);
                return;
            }
            enforce_private_file_permissions($bucketPath);
            $rateLimited = $count > $maximumRequests;
        } finally {
            if (is_resource($handle)) {
                flock($handle, LOCK_UN);
                fclose($handle);
            }
        }
    } finally {
        flock($shardLock, LOCK_UN);
        fclose($shardLock);
    }

    if ($rateLimited) {
        if (!headers_sent()) {
            header('Retry-After: ' . $windowSeconds);
        }
        app_http_error('Занадто багато запитів. Спробуйте пізніше.', 429);
    }
}
