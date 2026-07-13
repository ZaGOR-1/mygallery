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

    if (!ensure_private_directory($sessionPath)) {
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

    $previousUmask = umask(0077);
    $started = @session_start();
    umask($previousUmask);
    if (!$started || session_status() !== PHP_SESSION_ACTIVE) {
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
    $referrerPolicy = (string) ($GLOBALS['mygallery_referrer_policy'] ?? 'same-origin');
    if (!in_array($referrerPolicy, ['same-origin', 'no-referrer'], true)) {
        $referrerPolicy = 'same-origin';
    }
    header('Referrer-Policy: ' . $referrerPolicy);
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

/** @return list<string> */
function private_cache_headers(): array
{
    return [
        'Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0',
        'Pragma: no-cache',
        'Expires: 0',
    ];
}

function send_private_cache_headers(): void
{
    if (headers_sent()) {
        return;
    }

    foreach (private_cache_headers() as $cacheHeader) {
        header($cacheHeader);
    }
}

function send_admin_cache_headers(): void
{
    send_private_cache_headers();
}


function set_one_time_secret(string $key, string $value): void
{
    start_session();
    if (!isset($_SESSION['one_time_secrets']) || !is_array($_SESSION['one_time_secrets'])) {
        $_SESSION['one_time_secrets'] = [];
    }
    $_SESSION['one_time_secrets'][$key] = [
        'value' => $value,
        'created_at' => time(),
    ];

    if (count($_SESSION['one_time_secrets']) > 20) {
        uasort($_SESSION['one_time_secrets'], static fn (array $a, array $b): int => (int) ($a['created_at'] ?? 0) <=> (int) ($b['created_at'] ?? 0));
        while (count($_SESSION['one_time_secrets']) > 20) {
            array_shift($_SESSION['one_time_secrets']);
        }
    }
}

function pull_one_time_secret(string $key, int $maximumAgeSeconds = 600): ?string
{
    start_session();
    $entry = $_SESSION['one_time_secrets'][$key] ?? null;
    unset($_SESSION['one_time_secrets'][$key]);

    if (!is_array($entry)
        || !is_string($entry['value'] ?? null)
        || (time() - (int) ($entry['created_at'] ?? 0)) > $maximumAgeSeconds) {
        return null;
    }

    return $entry['value'];
}
