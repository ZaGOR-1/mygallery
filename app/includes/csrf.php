<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'functions.php';

function csrf_token(): string
{
    start_session();

    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): bool
{
    start_session();
    $token = $_POST['csrf_token'] ?? '';

    return is_string($token) && hash_equals((string) ($_SESSION['csrf_token'] ?? ''), $token);
}
