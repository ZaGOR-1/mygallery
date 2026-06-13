<?php

declare(strict_types=1);

$env = static function (string $key, mixed $default = null): mixed {
    $value = getenv($key);

    if ($value === false) {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
    }

    return ($value === null || $value === '') ? $default : $value;
};

return [
    'DB_HOST' => (string) $env('DB_HOST', '127.0.0.1'),
    'DB_PORT' => (int) $env('DB_PORT', 3306),
    'DB_NAME' => (string) $env('DB_NAME', 'my_photo_gallery'),
    'DB_USER' => (string) $env('DB_USER', 'gallery_user'),
    'DB_PASSWORD' => (string) $env('DB_PASSWORD', 'change_this_password'),
];
