<?php

declare(strict_types=1);

define('APP_ENV', 'test');

require_once dirname(__DIR__) . '/app/includes/functions.php';

try {
    db()->query('SELECT 1');
    define('TESTS_DB_AVAILABLE', true);
} catch (Throwable $e) {
    define('TESTS_DB_AVAILABLE', false);
}

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
