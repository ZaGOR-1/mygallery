<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'csrf.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'auth.php';

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
unset($GLOBALS['mygallery_csrf_request_token']);

$renderedTokens = [];
for ($index = 0; $index < 25; $index++) {
    $renderedTokens[] = csrf_token();
}
assert_equals(1, count(array_unique($renderedTokens)), 'all forms rendered in one request must reuse one token');
$pageToken = $renderedTokens[0];
assert_true(consume_csrf_token($pageToken), 'shared page token must verify once');
assert_false(consume_csrf_token($pageToken), 'shared page token must reject replay');

$_SESSION = [
    'csrf_tokens' => ['pre-auth-one', 'pre-auth-two'],
    'csrf_token' => 'pre-auth-two',
];
$GLOBALS['mygallery_csrf_request_token'] = 'pre-auth-two';
login_admin(['id' => 7, 'username' => 'tester', 'session_version' => 1]);
assert_false(consume_csrf_token('pre-auth-one'), 'pre-auth CSRF tokens must not survive login');
assert_false(consume_csrf_token('pre-auth-two'), 'submitted pre-auth CSRF token must not survive login');
$postAuthToken = (string) ($_SESSION['csrf_token'] ?? '');
assert_true($postAuthToken !== '', 'login must create a post-auth CSRF token');
assert_true(consume_csrf_token($postAuthToken), 'post-auth CSRF token must verify once');
assert_false(consume_csrf_token($postAuthToken), 'post-auth CSRF token must reject replay');

$_SESSION = [];
unset($GLOBALS['mygallery_csrf_request_token']);
