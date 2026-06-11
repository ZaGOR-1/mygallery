<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'functions.php';

function is_admin_logged_in(): bool
{
    start_session();

    return isset($_SESSION['admin_id']);
}

function require_admin(): void
{
    if (!is_admin_logged_in()) {
        set_flash('error', 'Увійдіть в адміністративну панель.');
        redirect('admin/login.php');
    }
}

function login_admin(array $admin): void
{
    start_session();
    session_regenerate_id(true);
    $_SESSION['admin_id'] = (int) $admin['id'];
    $_SESSION['admin_username'] = (string) $admin['username'];
}

function logout_admin(): void
{
    start_session();
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }

    session_destroy();
}
