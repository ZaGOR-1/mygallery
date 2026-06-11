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
    $sessionToken = $_SESSION['csrf_token'] ?? null;
    $token = $_POST['csrf_token'] ?? null;

    if (
        !is_string($sessionToken) ||
        !is_string($token) ||
        $sessionToken === '' ||
        $token === ''
    ) {
        return false;
    }

    return hash_equals($sessionToken, $token);
}
