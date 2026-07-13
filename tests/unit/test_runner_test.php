<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$runner = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'run.php');
$bootstrap = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR . 'bootstrap.php');
$dbFunctions = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'db.php');
$workflow = (string) file_get_contents($root . DIRECTORY_SEPARATOR . '.github' . DIRECTORY_SEPARATOR . 'workflows' . DIRECTORY_SEPARATOR . 'build_release.yml');

assert_true(str_contains($runner, 'catch (TestSkipped $e)'), 'test runner must count skipped suites separately');
assert_true(str_contains($runner, '$skipped++'), 'test runner must increment the skipped count');
assert_true(str_contains($runner, 'TESTS_DB_REQUIRED && $skipped > 0'), 'required DB mode must fail on skipped suites');
assert_true(str_contains($runner, 'assertions'), 'runner summary must distinguish suites from assertions');
assert_true(str_contains($bootstrap, "getenv('TEST_DB_NAME')"), 'DB suites must require separate TEST_DB_* configuration');
assert_true(str_contains($bootstrap, 'Refusing to query a non-test database'), 'test bootstrap must reject unsafe DB names before connecting');
assert_true(str_contains($bootstrap, 'Regular config/database.php detected'), 'installed local/production DB config must fail before any test query');
assert_true(str_contains($bootstrap, 'set_error_handler'), 'test bootstrap must turn PHP warnings/deprecations into failures');
assert_true(str_contains($dbFunctions, "app_env() === 'test'") && str_contains($dbFunctions, "getenv('TEST_DB_NAME')"), 'db_config must ignore regular DB config under APP_ENV=test');
assert_true(str_contains($workflow, 'REQUIRE_TEST_DB: "1"'), 'CI must require DB-backed suites');
