<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'SafeCliZipOutput.php';

$root = dirname(__DIR__, 2);
$testDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mygallery_safe_output_' . bin2hex(random_bytes(6));
assert_true(mkdir($testDirectory, 0700), 'safe output test directory should be created');
try {
    $plan = prepare_safe_cli_zip_output($testDirectory . DIRECTORY_SEPARATOR . 'archive.zip', $root, [], 0600);
    assert_true(is_file($plan['temporary']), 'safe output must reserve an exclusive sibling temp file');
    assert_false(file_exists($plan['final']), 'safe output must not touch final path before publish');
    assert_equals(3, file_put_contents($plan['temporary'], 'zip'), 'temporary output should be writable');
    publish_safe_cli_zip_output($plan);
    assert_true(is_file($plan['final']), 'safe output must atomically publish final ZIP');

    assert_throws(
        static fn () => prepare_safe_cli_zip_output($testDirectory . DIRECTORY_SEPARATOR . 'not-zip.txt', $root, [], 0600),
        InvalidArgumentException::class,
        'non-ZIP output must be rejected'
    );
    assert_throws(
        static fn () => prepare_safe_cli_zip_output($root . DIRECTORY_SEPARATOR . 'config' . DIRECTORY_SEPARATOR . 'danger.zip', $root, [], 0600),
        RuntimeException::class,
        'project source/config output must be rejected'
    );
    assert_throws(
        static fn () => prepare_safe_cli_zip_output($testDirectory . DIRECTORY_SEPARATOR . 'archive.zip', $root, [], 0600),
        RuntimeException::class,
        'existing output must never be overwritten'
    );
} finally {
    foreach (glob($testDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
        if (is_file($file) || is_link($file)) {
            unlink($file);
        }
    }
    rmdir($testDirectory);
}

$backupSource = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'backup.php');
$releaseSource = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'build_release.php');
assert_true(str_contains($backupSource, 'new ZipArchive()'), 'backup must use streaming/ZIP64-capable ZipArchive');
assert_true(str_contains($releaseSource, 'new ZipArchive()'), 'release must use streaming/ZIP64-capable ZipArchive');
assert_true(str_contains($backupSource, 'require_safe_cli_zip_free_space'), 'backup must preflight free disk space');
assert_true(str_contains($releaseSource, 'require_safe_cli_zip_free_space'), 'release must preflight free disk space');

$freeSpacePlan = [
    'final' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'probe.zip',
    'temporary' => sys_get_temp_dir() . DIRECTORY_SEPARATOR . '.probe_zip',
    'mode' => 0600,
];
assert_throws(
    static fn () => require_safe_cli_zip_free_space($freeSpacePlan, PHP_INT_MAX),
    RuntimeException::class,
    'impossible ZIP size must fail the free-space preflight'
);
assert_false(str_contains($backupSource, 'new SimpleZipWriter'), 'backup must not buffer each media file through SimpleZipWriter');
assert_false(str_contains($releaseSource, 'new SimpleZipWriter'), 'release must not buffer each source file through SimpleZipWriter');
assert_true(str_contains($backupSource, 'prepare_safe_cli_zip_output'), 'backup must use common safe output policy');
assert_true(str_contains($releaseSource, 'prepare_safe_cli_zip_output'), 'release must use common safe output policy');
