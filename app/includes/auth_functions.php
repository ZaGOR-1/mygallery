<?php

declare(strict_types=1);

function session_cookie_options(): array
{
    $config = app_config();

    return [
        'lifetime' => 0,
        'path' => '/',
        'domain' => '',
        'secure' => is_https_request() || (($config['APP_ENV'] ?? '') === 'production' && str_starts_with((string) $config['APP_URL'], 'https://')),
        'httponly' => true,
        'samesite' => 'Lax',
    ];
}

function start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $sessionPath = storage_path('sessions');

    if (!is_dir($sessionPath) && !@mkdir($sessionPath, 0755, true)) {
        app_http_error('Не вдалося створити папку для PHP-сесій.', 500);
    }

    if (!is_dir($sessionPath) || !is_writable($sessionPath)) {
        app_http_error('Папка PHP-сесій недоступна для запису.', 500);
    }

    if (!str_contains((string) session_save_path(), 'test_sessions')) {
        session_save_path($sessionPath);
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    session_cache_limiter('');
    session_set_cookie_params(session_cookie_options());

    if (!@session_start() || session_status() !== PHP_SESSION_ACTIVE) {
        app_http_error('Не вдалося запустити PHP-сесію. Перевірте права на storage/sessions.', 500);
    }
}

function has_session_cookie(): bool
{
    return isset($_COOKIE[session_name()]);
}

function send_security_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; script-src 'self'; style-src 'self'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");

    if (is_production() && is_https_request()) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

function get_admin_session_version(int $adminId): ?int
{
    $stmt = db()->prepare('SELECT session_version FROM admins WHERE id = :id');
    $stmt->execute(['id' => $adminId]);
    $version = $stmt->fetchColumn();

    return $version === false ? null : (int) $version;
}

function send_admin_cache_headers(): void
{
    if (headers_sent()) {
        return;
    }

    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}
