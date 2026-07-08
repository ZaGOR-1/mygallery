<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$selfCheck = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'self_check.php');

assert_true(str_contains($selfCheck, 'function run_self_check(): void'), 'self_check must wrap checks in a runnable function');
assert_true(str_contains($selfCheck, 'catch (Throwable $exception)'), 'self_check must catch top-level failures');
assert_true(str_contains($selfCheck, 'Self-check failed:'), 'self_check must print a readable failure message');
