<?php

declare(strict_types=1);

$workflow = (string) file_get_contents(
    dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.github' . DIRECTORY_SEPARATOR . 'workflows'
    . DIRECTORY_SEPARATOR . 'build_release.yml'
);

assert_true(str_contains($workflow, "php: [ '8.2', '8.4' ]"), 'CI must test the supported PHP 8.2 and 8.4 boundaries');
assert_true(str_contains($workflow, 'php-version: ${{ matrix.php }}'), 'CI setup must use the PHP matrix value');
assert_true(str_contains($workflow, 'image: mariadb:10.11'), 'CI must cover the supported MariaDB family');
assert_true(str_contains($workflow, 'TEST_DB_NAME: my_photo_gallery_test'), 'CI DB suites must use explicit isolated TEST_DB_* credentials');
assert_true(str_contains($workflow, "printf 'RESTORE\\n' | php tools/restore.php"), 'CI must run a real backup-to-restore round-trip');
assert_true(str_contains($workflow, 'seed_backup_fixture.php'), 'CI backup round-trip must seed a non-empty relational/media fixture');
assert_true(str_contains($workflow, 'assert_backup_fixture.php'), 'CI restore must compare row counts, metadata and media hashes');
assert_true(str_contains($workflow, 'php -S 127.0.0.1:8080 -t public'), 'CI must start the public application for HTTP smoke tests');
assert_true(str_contains($workflow, "grep -qi '^Content-Security-Policy:'"), 'HTTP smoke tests must verify the CSP response header');
assert_true(str_contains($workflow, "grep -qi '^X-Content-Type-Options: nosniff'"), 'HTTP smoke tests must verify nosniff');
assert_true(str_contains($workflow, "server: [ apache, nginx ]"), 'CI must smoke-test real Apache and Nginx configurations');
assert_true(str_contains($workflow, '/assets/.htaccess') && str_contains($workflow, "= '403'"), 'real webserver smoke tests must deny dotfiles below public assets');
assert_true(str_contains($workflow, '/assets/css/style.css'), 'real webserver smoke tests must keep normal assets reachable');
assert_false((bool) preg_match('/uses:\s*[^\s]+@v\d+/i', $workflow), 'CI actions must be pinned to immutable commit SHAs');
assert_true((bool) preg_match('/actions\/checkout@[a-f0-9]{40}/', $workflow), 'checkout action must use a full commit SHA');
assert_true((bool) preg_match('/shivammathur\/setup-php@[a-f0-9]{40}/', $workflow), 'setup-php action must use a full commit SHA');
$dependabot = (string) file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.github' . DIRECTORY_SEPARATOR . 'dependabot.yml');
assert_true(str_contains($dependabot, 'package-ecosystem: github-actions'), 'Dependabot must update pinned GitHub Actions');
