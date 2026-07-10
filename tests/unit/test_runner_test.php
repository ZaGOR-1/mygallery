<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$runner = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'run.php');
$workflow = (string) file_get_contents($root . DIRECTORY_SEPARATOR . '.github' . DIRECTORY_SEPARATOR . 'workflows' . DIRECTORY_SEPARATOR . 'build_release.yml');

assert_true(str_contains($runner, 'catch (TestSkipped $e)'), 'test runner must count skipped suites separately');
assert_true(str_contains($runner, '$skipped++'), 'test runner must increment the skipped count');
assert_true(str_contains($runner, 'TESTS_DB_REQUIRED && $skipped > 0'), 'required DB mode must fail on skipped suites');
assert_true(str_contains($workflow, 'REQUIRE_TEST_DB: "1"'), 'CI must require DB-backed suites');
