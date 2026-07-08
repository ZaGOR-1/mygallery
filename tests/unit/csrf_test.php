<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'csrf.php';

$_SESSION = [
    'csrf_tokens' => ['fresh-token'],
    'csrf_token' => 'fresh-token',
];

assert_true(consume_csrf_token('fresh-token'), 'fresh CSRF token must verify once');
assert_false(consume_csrf_token('fresh-token'), 'fresh CSRF token must not verify twice through legacy fallback');

$_SESSION = ['csrf_token' => 'legacy-token'];

assert_true(consume_csrf_token('legacy-token'), 'legacy CSRF token must still verify for old forms');
assert_false(consume_csrf_token('legacy-token'), 'legacy CSRF token must be consumed after one use');

$_SESSION = [];
