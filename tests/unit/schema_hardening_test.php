<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$schema = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql');
$migration = (string) file_get_contents(
    $root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations'
    . DIRECTORY_SEPARATOR . '2026_07_10_add_share_target_check.sql'
);
$selfCheck = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'self_check.php');

assert_true(str_contains($schema, 'chk_share_links_exactly_one_target'), 'schema must define the share target CHECK');
assert_true(str_contains($schema, '`photo_id` IS NOT NULL AND `album_id` IS NULL'), 'schema CHECK must allow a photo-only target');
assert_true(str_contains($schema, '`photo_id` IS NULL AND `album_id` IS NOT NULL'), 'schema CHECK must allow an album-only target');
assert_true(str_contains($migration, 'information_schema.TABLE_CONSTRAINTS'), 'share target migration must guard repeated runs');
assert_true(str_contains($migration, '@constraint_exists = 0'), 'share target migration must add the CHECK only when absent');
assert_true(str_contains($selfCheck, 'chk_share_links_exactly_one_target'), 'self-check must require the deployed share target CHECK');
