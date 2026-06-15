<?php

declare(strict_types=1);

function db_config(): array
{
    $path = project_root_path('config' . DIRECTORY_SEPARATOR . 'database.php');

    if (!file_exists($path)) {
        throw new RuntimeException('Файл config/database.php не знайдено. Скопіюйте config/database.example.php і впишіть налаштування бази даних.');
    }

    $config = require $path;

    if (is_production()) {
        $user = (string) ($config['DB_USER'] ?? '');
        $password = (string) ($config['DB_PASSWORD'] ?? '');

        if ($user === 'root' || $password === '') {
            app_http_error('Небезпечна production-конфігурація БД: використайте окремого користувача з паролем.', 500);
        }
    }

    return $config;
}

function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $config = db_config();
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4',
            $config['DB_HOST'],
            (int) $config['DB_PORT'],
            $config['DB_NAME']
        );

        try {
            $pdo = new PDO($dsn, $config['DB_USER'], $config['DB_PASSWORD'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);
        } catch (Throwable $exception) {
            app_log_exception($exception, 'Database connection failed');
            throw $exception;
        }
    }

    return $pdo;
}
