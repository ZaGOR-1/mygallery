<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'functions.php';

function csrf_token(): string
{
    start_session();

    if (!isset($_SESSION['csrf_tokens']) || !is_array($_SESSION['csrf_tokens'])) {
        $_SESSION['csrf_tokens'] = [];
    }

    $token = bin2hex(random_bytes(32));
    $_SESSION['csrf_tokens'][] = $token;

    if (count($_SESSION['csrf_tokens']) > 10) {
        array_shift($_SESSION['csrf_tokens']);
    }

    // Keep legacy single token for backwards compatibility if needed somewhere
    $_SESSION['csrf_token'] = $token;

    return $token;
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function consume_csrf_token(string $token): bool
{
    $sessionTokenLegacy = $_SESSION['csrf_token'] ?? null;

    if (!empty($_SESSION['csrf_tokens']) && is_array($_SESSION['csrf_tokens'])) {
        foreach ($_SESSION['csrf_tokens'] as $index => $sessionToken) {
            if (hash_equals((string)$sessionToken, $token)) {
                unset($_SESSION['csrf_tokens'][$index]);
                $_SESSION['csrf_tokens'] = array_values($_SESSION['csrf_tokens']); // reindex
                if (is_string($sessionTokenLegacy) && hash_equals($sessionTokenLegacy, $token)) {
                    unset($_SESSION['csrf_token']);
                }
                return true;
            }
        }
    }

    if (is_string($sessionTokenLegacy) && $sessionTokenLegacy !== '' && hash_equals($sessionTokenLegacy, $token)) {
        unset($_SESSION['csrf_token']);
        return true;
    }

    return false;
}

function verify_csrf(): bool
{
    start_session();
    $token = $_POST['csrf_token'] ?? null;

    if (!is_string($token) || $token === '') {
        return false;
    }

    return consume_csrf_token($token);
}

function csrf_error(): never
{
    $errorStatusCode = 400;
    $errorTitle = 'Помилка CSRF-захисту';
    $errorMessage = 'Не вдалося перевірити безпеку запиту. Найчастіше це трапляється, якщо сторінка була відчинена занадто довго і ваша сесія закінчилася. Будь ласка, оновіть сторінку та спробуйте ще раз.';
    $errorPage = public_path('500.php');

    if (!headers_sent()) {
        http_response_code(400);
        send_security_headers();
    }

    if (is_file($errorPage)) {
        require $errorPage;
        exit;
    }

    echo '<!doctype html><html lang="uk"><head><meta charset="utf-8"><title>' . h($errorTitle) . '</title></head><body><h1>' . h($errorTitle) . '</h1><p>' . h($errorMessage) . '</p></body></html>';
    exit;
}

function require_csrf(): void
{
    if (!verify_csrf()) {
        csrf_error();
    }
}
