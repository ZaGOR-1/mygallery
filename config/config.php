<?php

declare(strict_types=1);

$env = static function (string $key, mixed $default = null): mixed {
    $value = getenv($key);

    if ($value === false) {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
    }

    return ($value === null || $value === '') ? $default : $value;
};

$boolEnv = static function (string $key, bool $default = false) use ($env): bool {
    $value = $env($key, $default ? 'true' : 'false');

    return filter_var($value, FILTER_VALIDATE_BOOL, FILTER_NULL_ON_FAILURE) ?? $default;
};


$intEnv = static function (string $key, int $default, int $minimum = 0, ?int $maximum = null) use ($env): int {
    $value = $env($key, (string) $default);
    if (!is_string($value) && !is_int($value)) {
        return $default;
    }
    $parsed = filter_var($value, FILTER_VALIDATE_INT);
    if (!is_int($parsed) || $parsed < $minimum || ($maximum !== null && $parsed > $maximum)) {
        return $default;
    }

    return $parsed;
};

$listEnv = static function (string $key, array $default = []) use ($env): array {
    $value = $env($key, null);

    if (!is_string($value)) {
        return $default;
    }

    return array_values(array_filter(
        array_map('trim', explode(',', $value)),
        static fn (string $item): bool => $item !== ''
    ));
};

return [
    'APP_NAME' => (string) $env('APP_NAME', 'My Photo Gallery'),
    'APP_URL' => (string) $env('APP_URL', 'http://mygallery'),
    'APP_ENV' => (string) $env('APP_ENV', 'local'),
    'APP_DEBUG' => $boolEnv('APP_DEBUG', false),
    'UPLOAD_MAX_SIZE' => 50 * 1024 * 1024,
    'PHOTOS_PER_PAGE' => 12,
    'MAX_IMAGE_WIDTH' => 8000,
    'MAX_IMAGE_HEIGHT' => 8000,
    'MAX_IMAGE_PIXELS' => 50 * 1000 * 1000,
    'LARGE_MAX_WIDTH' => 4000,
    'LOGIN_MAX_ATTEMPTS' => 5,
    'LOGIN_ACCOUNT_MAX_ATTEMPTS' => 10,
    'LOGIN_IP_MAX_ATTEMPTS' => 20,
    'LOGIN_LOCK_SECONDS' => 300,
    'IMAGE_QUALITY_WEBP' => 85,
    'IMAGE_QUALITY_AVIF' => 65,
    'ALBUM_ZIP_ENABLED' => $boolEnv('ALBUM_ZIP_ENABLED', true),
    'ALBUM_ZIP_CACHE_MAX_BYTES' => $intEnv('ALBUM_ZIP_CACHE_MAX_BYTES', 2 * 1024 * 1024 * 1024, 1),
    'ALBUM_ZIP_MAX_GENERATION_SECONDS' => $intEnv('ALBUM_ZIP_MAX_GENERATION_SECONDS', 120, 10, 1800),
    'ALBUM_ZIP_MAX_PHOTOS' => $intEnv('ALBUM_ZIP_MAX_PHOTOS', 200, 1, 1000),
    'ALBUM_ZIP_MAX_SOURCE_BYTES' => $intEnv('ALBUM_ZIP_MAX_SOURCE_BYTES', 500 * 1024 * 1024, 1),
    'ALBUM_ZIP_MAX_CONCURRENT_STREAMS' => $intEnv('ALBUM_ZIP_MAX_CONCURRENT_STREAMS', 2, 1, 10),
    'SHARE_RATE_LIMIT_MAX_REQUESTS' => $intEnv('SHARE_RATE_LIMIT_MAX_REQUESTS', 120, 1, 10000),
    'SHARE_RATE_LIMIT_WINDOW_SECONDS' => $intEnv('SHARE_RATE_LIMIT_WINDOW_SECONDS', 60, 1, 3600),
    'SHARE_RATE_LIMIT_TTL_SECONDS' => $intEnv('SHARE_RATE_LIMIT_TTL_SECONDS', 172800, 60, 2592000),
    'SHARE_RATE_LIMIT_MAX_FILES_PER_SHARD' => $intEnv('SHARE_RATE_LIMIT_MAX_FILES_PER_SHARD', 256, 16, 2048),
    'BULK_EDIT_MAX_PHOTOS' => $intEnv('BULK_EDIT_MAX_PHOTOS', 200, 1, 1000),
    'RESTORE_MAX_UNCOMPRESSED_BYTES' => $intEnv('RESTORE_MAX_UNCOMPRESSED_BYTES', 100 * 1024 * 1024 * 1024, 1, 1024 * 1024 * 1024 * 1024),
    'RESTORE_MAX_COMPRESSION_RATIO' => $intEnv('RESTORE_MAX_COMPRESSION_RATIO', 250, 1, 10000),
    'RESTORE_MIN_FREE_BYTES' => $intEnv('RESTORE_MIN_FREE_BYTES', 256 * 1024 * 1024, 0),
    'TRUSTED_PROXIES' => $listEnv('TRUSTED_PROXIES', []),
];
