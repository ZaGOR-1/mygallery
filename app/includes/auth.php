<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'functions.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth_functions.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'csrf.php';

function is_admin_logged_in(): bool
{
    if (session_status() !== PHP_SESSION_ACTIVE && !has_session_cookie()) {
        return false;
    }

    start_session();

    if (!isset($_SESSION['admin_id'])) {
        return false;
    }

    $adminId = (int) $_SESSION['admin_id'];
    $lastActivity = (int) ($_SESSION['admin_last_activity'] ?? 0);
    $idleLimit = 3600;

    if ($lastActivity > 0 && (time() - $lastActivity) > $idleLimit) {
        logout_admin();
        return false;
    }

    $lastAdminCheck = (int) ($_SESSION['admin_checked_at'] ?? 0);

    if ($lastAdminCheck === 0 || (time() - $lastAdminCheck) > 60) {
        try {
            $currentVersion = get_admin_session_version($adminId);
            if ($currentVersion === null || $currentVersion !== (int) ($_SESSION['admin_session_version'] ?? 1)) {
                logout_admin();
                return false;
            }

            $_SESSION['admin_checked_at'] = time();
        } catch (Throwable $exception) {
            app_log_exception($exception, 'Admin session freshness check failed');
            logout_admin();
            return false;
        }
    }

    $_SESSION['admin_last_activity'] = time();

    return true;
}

function require_admin(): void
{
    if (!is_admin_logged_in()) {
        set_flash('error', 'Увійдіть в адміністративну панель.');
        redirect('admin/login.php');
    }

    send_admin_cache_headers();
}

function login_admin(array $admin): void
{
    start_session();
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $admin['id'];
    $_SESSION['admin_username'] = (string) $admin['username'];
    $_SESSION['admin_session_version'] = (int) ($admin['session_version'] ?? 1);
    $_SESSION['admin_login_at'] = time();
    $_SESSION['admin_last_activity'] = time();
    $_SESSION['admin_checked_at'] = time();
    reset_csrf_tokens_after_privilege_change();
}

function logout_admin(): void
{
    start_session();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => (bool) $params['secure'],
            'httponly' => (bool) $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
}
