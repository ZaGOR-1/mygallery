<?php

declare(strict_types=1);

$workflow = (string) file_get_contents(
    dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.github' . DIRECTORY_SEPARATOR . 'workflows'
    . DIRECTORY_SEPARATOR . 'build_release.yml'
);

assert_true(str_contains($workflow, "php: [ '8.2', '8.4' ]"), 'CI must test the supported PHP 8.2 and 8.4 boundaries');
assert_true(str_contains($workflow, 'php-version: ${{ matrix.php }}'), 'CI setup must use the PHP matrix value');
assert_true(str_contains($workflow, "printf 'RESTORE\\n' | php tools/restore.php"), 'CI must run a real backup-to-restore round-trip');
assert_true(str_contains($workflow, 'php -S 127.0.0.1:8080 -t public'), 'CI must start the public application for HTTP smoke tests');
assert_true(str_contains($workflow, "grep -qi '^Content-Security-Policy:'"), 'HTTP smoke tests must verify the CSP response header');
assert_true(str_contains($workflow, "grep -qi '^X-Content-Type-Options: nosniff'"), 'HTTP smoke tests must verify nosniff');
