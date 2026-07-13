<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'auth.php';

$_SESSION = ['pre_auth' => 'value'];
assert_throws(
    static fn () => login_admin(
        ['id' => 9, 'username' => 'failure-test', 'session_version' => 1],
        static fn (): bool => false
    ),
    RuntimeException::class,
    'failed session regeneration must abort login'
);
assert_false(isset($_SESSION['admin_id']), 'failed session regeneration must not create privileged state');

start_session();
