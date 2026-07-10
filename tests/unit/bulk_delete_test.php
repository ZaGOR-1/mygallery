<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$service = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'photo_service.php');
$controller = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'bulk_edit.php');

assert_true(str_contains($service, 'function bulk_delete_photos_with_trash'), 'bulk delete must use a service-level preflight/result helper');
assert_true(str_contains($service, "if (\$result['failed'] !== [])"), 'bulk delete preflight must abort before changes when any item is unsafe');
assert_true(str_contains($service, "\$result['deleted'][] = \$photoId"), 'bulk delete must record exact successful IDs');
assert_true(str_contains($service, "\$result['failed'][\$photoId]"), 'bulk delete must record exact failed IDs');
assert_true(str_contains($controller, "implode(', #', \$result['deleted'])"), 'bulk delete UI must show successful IDs');
assert_true(str_contains($controller, "foreach (\$result['failed'] as \$failedId => \$message)"), 'bulk delete UI must show per-item failures');

if (defined('TESTS_DB_AVAILABLE') && TESTS_DB_AVAILABLE) {
    require_once $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'photo_service.php';
    $missingId = 2147483647;
    $result = bulk_delete_photos_with_trash(db(), [$missingId, $missingId]);
    assert_equals([], $result['deleted'], 'missing-ID preflight must not delete anything');
    assert_true(isset($result['failed'][$missingId]), 'missing-ID preflight must report the exact ID once');
}
