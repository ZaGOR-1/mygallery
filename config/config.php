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
    'TRUSTED_PROXIES' => $listEnv('TRUSTED_PROXIES', []),
];
