<?php

declare(strict_types=1);

define('TESTS_DB_REQUIRED', getenv('REQUIRE_TEST_DB') === '1');

$testDbName = trim((string) (getenv('TEST_DB_NAME') ?: ''));
$testDbConfigured = $testDbName !== '';
$unsafeTestDbAllowed = getenv('ALLOW_UNSAFE_TEST_DB_NAME') === '1';

if ($testDbConfigured && !$unsafeTestDbAllowed && !str_contains(strtolower($testDbName), 'test')) {
    throw new RuntimeException('TEST_DB_NAME must contain "test". Refusing to query a non-test database.');
}

if (TESTS_DB_REQUIRED && !$testDbConfigured) {
    throw new RuntimeException('REQUIRE_TEST_DB=1 requires explicit TEST_DB_NAME/TEST_DB_* isolation.');
}
if (!$testDbConfigured
    && is_file(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'database.php')) {
    throw new RuntimeException('Regular config/database.php detected. Refusing to run until explicit TEST_DB_* isolation is configured.');
}

putenv('APP_ENV=test');
$_ENV['APP_ENV'] = 'test';
$_SERVER['APP_ENV'] = 'test';

if ($testDbConfigured) {
    $testDbEnvironment = [
        'DB_HOST' => (string) (getenv('TEST_DB_HOST') ?: '127.0.0.1'),
        'DB_PORT' => (string) (getenv('TEST_DB_PORT') ?: '3306'),
        'DB_NAME' => $testDbName,
        'DB_USER' => (string) (getenv('TEST_DB_USER') ?: ''),
        'DB_PASSWORD' => (string) (getenv('TEST_DB_PASSWORD') ?: ''),
    ];
    if ($testDbEnvironment['DB_USER'] === '') {
        throw new RuntimeException('TEST_DB_USER is required when TEST_DB_NAME is configured.');
    }
    foreach ($testDbEnvironment as $key => $value) {
        putenv($key . '=' . $value);
        $_ENV[$key] = $value;
        $_SERVER[$key] = $value;
    }
}

final class TestSkipped extends RuntimeException
{
}

function skip_test(string $reason): never
{
    throw new TestSkipped($reason);
}

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if ((error_reporting() & $severity) === 0) {
        return false;
    }

    throw new ErrorException($message, 0, $severity, $file, $line);
});

require_once dirname(__DIR__) . '/app/includes/functions.php';

if (app_env() !== 'test') {
    throw new RuntimeException('Test bootstrap must run with APP_ENV=test.');
}

$testSessionPath = storage_path('test_sessions');
if (!is_dir($testSessionPath) && !mkdir($testSessionPath, 0700, true)) {
    throw new RuntimeException('Could not create test session directory.');
}
session_save_path($testSessionPath);
session_id('tests' . bin2hex(random_bytes(8)));
if (!session_start()) {
    throw new RuntimeException('Could not start isolated test session.');
}
register_shutdown_function(static function (): void {
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION = [];
        session_destroy();
    }
    restore_error_handler();
});

if ($testDbConfigured) {
    try {
        db()->query('SELECT 1');
        define('TESTS_DB_AVAILABLE', true);
    } catch (Throwable $e) {
        if (TESTS_DB_REQUIRED) {
            throw new RuntimeException('Could not connect to the explicit isolated test DB.', 0, $e);
        }
        define('TESTS_DB_AVAILABLE', false);
    }
} else {
    // Fail closed: a regular config/database.php is never probed by the test runner.
    define('TESTS_DB_AVAILABLE', false);
}

$GLOBALS['mygallery_test_assertions'] = 0;

function count_test_assertion(): void
{
    $GLOBALS['mygallery_test_assertions'] = (int) ($GLOBALS['mygallery_test_assertions'] ?? 0) + 1;
}

function assert_true(bool $condition, string $message): void {
    count_test_assertion();
    if (!$condition) {
        throw new RuntimeException("Assertion failed: $message");
    }
}

function assert_false(bool $condition, string $message): void {
    count_test_assertion();
    if ($condition) {
        throw new RuntimeException("Assertion failed: $message");
    }
}

function assert_equals(mixed $expected, mixed $actual, string $message = ''): void {
    count_test_assertion();
    if ($expected !== $actual) {
        $expectedStr = print_r($expected, true);
        $actualStr = print_r($actual, true);
        throw new RuntimeException("Assertion failed: $message. Expected: $expectedStr, Got: $actualStr");
    }
}

function assert_throws(Closure $callback, string $exceptionClass, string $message = ''): void {
    count_test_assertion();
    try {
        $callback();
    } catch (Throwable $e) {
        if (!is_a($e, $exceptionClass)) {
            throw new RuntimeException("Assertion failed: Expected exception $exceptionClass, but got " . get_class($e));
        }
        return;
    }
    throw new RuntimeException("Assertion failed: Expected exception $exceptionClass, but no exception was thrown. $message");
}
