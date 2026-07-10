<?php

declare(strict_types=1);

define('APP_ENV', 'test');

final class TestSkipped extends RuntimeException
{
}

function skip_test(string $reason): never
{
    throw new TestSkipped($reason);
}

require_once dirname(__DIR__) . '/app/includes/functions.php';

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
});

try {
    db()->query('SELECT 1');
    define('TESTS_DB_AVAILABLE', true);
} catch (Throwable $e) {
    define('TESTS_DB_AVAILABLE', false);
}

define('TESTS_DB_REQUIRED', getenv('REQUIRE_TEST_DB') === '1');

function assert_true(bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException("Assertion failed: $message");
    }
}

function assert_false(bool $condition, string $message): void {
    if ($condition) {
        throw new RuntimeException("Assertion failed: $message");
    }
}

function assert_equals(mixed $expected, mixed $actual, string $message = ''): void {
    if ($expected !== $actual) {
        $expectedStr = print_r($expected, true);
        $actualStr = print_r($actual, true);
        throw new RuntimeException("Assertion failed: $message. Expected: $expectedStr, Got: $actualStr");
    }
}

function assert_throws(Closure $callback, string $exceptionClass, string $message = ''): void {
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
