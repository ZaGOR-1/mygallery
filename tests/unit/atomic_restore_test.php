<?php

declare(strict_types=1);

define('MYGALLERY_RESTORE_LIBRARY_ONLY', true);
require_once dirname(__DIR__, 2) . '/tools/restore.php';

assert_true(in_array('sqlite', PDO::getAvailableDrivers(), true), 'PDO SQLite is required for atomic restore tests');

function test_restore_remove_tree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        /** @var SplFileInfo $item */
        if ($item->isDir() && !$item->isLink()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($path);
}

$root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mygallery_restore_tests_' . bin2hex(random_bytes(6));
assert_true(mkdir($root, 0700), 'temporary restore test directory should be created');
$targets = [
    'storage_originals' => $root . DIRECTORY_SEPARATOR . 'originals',
    'public_large' => $root . DIRECTORY_SEPARATOR . 'large',
    'public_thumbnails' => $root . DIRECTORY_SEPARATOR . 'thumbnails',
];

try {
    foreach ($targets as $group => $target) {
        assert_true(mkdir($target, 0700), 'restore target should be created: ' . $group);
        assert_true(file_put_contents($target . DIRECTORY_SEPARATOR . 'old.txt', 'old-' . $group) !== false, 'old fixture');
        assert_true(file_put_contents($target . DIRECTORY_SEPARATOR . '.gitkeep', '') !== false, 'gitkeep fixture');
        if ($group !== 'storage_originals') {
            assert_true(
                file_put_contents($target . DIRECTORY_SEPARATOR . '.htaccess', 'Require all denied') !== false,
                'htaccess fixture'
            );
        }
    }

    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('CREATE TABLE schema_migrations (migration VARCHAR(255) PRIMARY KEY, run_at TEXT)');

    if (PHP_OS_FAMILY !== 'Windows') {
        $journalTarget = $root . DIRECTORY_SEPARATOR . 'journal-target.json';
        $journalLink = $root . DIRECTORY_SEPARATOR . 'journal-link.json';
        assert_true(file_put_contents($journalTarget, '{}') !== false, 'journal symlink target fixture should be written');
        if (@symlink($journalTarget, $journalLink)) {
            assert_throws(
                static fn () => restore_write_journal($journalLink, str_repeat('a', 32), '__mygallery_restore__' . str_repeat('a', 32), []),
                RuntimeException::class,
                'restore journal writer must reject symbolic links'
            );
            assert_throws(
                static fn () => restore_recover_interrupted_operation($pdo, $journalLink, $targets),
                RuntimeException::class,
                'restore recovery must reject symbolic links'
            );
            unlink($journalLink);
        }
        unlink($journalTarget);
    }

    // Fault after all directory swaps but before DB commit: absent marker must restore old media.
    $rollbackId = bin2hex(random_bytes(16));
    $rollbackMarker = '__mygallery_restore__' . $rollbackId;
    $rollbackJournal = $root . DIRECTORY_SEPARATOR . 'rollback-journal.json';
    $rollbackMappings = restore_build_mappings($rollbackId, $targets);
    restore_require_staging_capacity($rollbackMappings, [
        'storage_originals' => 1,
        'public_large' => 1,
        'public_thumbnails' => 1,
    ], 0);
    assert_throws(
        static fn () => restore_require_staging_capacity($rollbackMappings, [
            'storage_originals' => 1,
            'public_large' => 1,
            'public_thumbnails' => 1,
        ], 1000000000000000),
        RuntimeException::class,
        'restore must reject staging when its free-space reserve cannot be met'
    );
    restore_prepare_staging($rollbackMappings);
    if (PHP_OS_FAMILY !== 'Windows') {
        assert_equals(0700, fileperms($rollbackMappings['storage_originals']['stage']) & 0777, 'private originals staging directory must be 0700');
        assert_equals(0750, fileperms($rollbackMappings['public_large']['stage']) & 0777, 'public derivative staging directory must be 0750');
    }

    $mediaZipPath = $root . DIRECTORY_SEPARATOR . 'restore-media.zip';
    $mediaZip = new ZipArchive();
    assert_true($mediaZip->open($mediaZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 'restore media fixture should open');
    $mediaEntries = [];
    foreach (['storage_originals' => 'original', 'public_large' => 'large', 'public_thumbnails' => 'thumbnail'] as $group => $content) {
        $filename = str_repeat(substr($group, 0, 1), 32) . '.jpg';
        $entryName = 'fixture/' . $group . '/' . $filename;
        assert_true($mediaZip->addFromString($entryName, $content), 'restore media fixture entry should be added');
        $mediaEntries[$group] = [[
            'entry' => $entryName,
            'filename' => $filename,
            'size' => strlen($content),
            'sha256' => hash('sha256', $content),
        ]];
    }
    assert_true($mediaZip->close(), 'restore media fixture should close');
    assert_true($mediaZip->open($mediaZipPath) === true, 'restore media fixture should reopen');
    assert_equals(3, restore_extract_to_staging($mediaZip, $mediaEntries, $rollbackMappings), 'restore must extract all staged media');
    $mediaZip->close();
    if (PHP_OS_FAMILY !== 'Windows') {
        $privateFile = $rollbackMappings['storage_originals']['stage'] . DIRECTORY_SEPARATOR . $mediaEntries['storage_originals'][0]['filename'];
        $sharedFile = $rollbackMappings['public_large']['stage'] . DIRECTORY_SEPARATOR . $mediaEntries['public_large'][0]['filename'];
        assert_equals(0600, fileperms($privateFile) & 0777, 'restored original must be 0600');
        assert_equals(0640, fileperms($sharedFile) & 0777, 'restored derivative must be 0640');
    }
    foreach ($rollbackMappings as $group => $mapping) {
        assert_true(
            file_put_contents($mapping['stage'] . DIRECTORY_SEPARATOR . 'new.txt', 'new-' . $group) !== false,
            'new rollback fixture'
        );
    }
    restore_write_journal($rollbackJournal, $rollbackId, $rollbackMarker, $rollbackMappings);
    assert_true(filesystem_permissions_are_private($rollbackJournal), 'restore journal must be private before the media swap starts');
    restore_swap_directories($rollbackMappings, $rollbackJournal, $rollbackId, $rollbackMarker);
    restore_recover_interrupted_operation($pdo, $rollbackJournal, $targets);

    foreach ($targets as $target) {
        assert_true(is_file($target . DIRECTORY_SEPARATOR . 'old.txt'), 'rollback restores old media');
        assert_false(is_file($target . DIRECTORY_SEPARATOR . 'new.txt'), 'rollback removes uncommitted new media');
    }
    assert_false(is_file($rollbackJournal), 'rollback removes completed journal');

    // DB marker committed: recovery must keep staged media and remove old directories.
    $commitId = bin2hex(random_bytes(16));
    $commitMarker = '__mygallery_restore__' . $commitId;
    $commitJournal = $root . DIRECTORY_SEPARATOR . 'commit-journal.json';
    $commitMappings = restore_build_mappings($commitId, $targets);
    restore_prepare_staging($commitMappings);
    foreach ($commitMappings as $group => $mapping) {
        assert_true(
            file_put_contents($mapping['stage'] . DIRECTORY_SEPARATOR . 'new.txt', 'committed-' . $group) !== false,
            'new committed fixture'
        );
    }
    restore_write_journal($commitJournal, $commitId, $commitMarker, $commitMappings);
    assert_true(filesystem_permissions_are_private($commitJournal), 'updated restore journal must remain private');
    restore_swap_directories($commitMappings, $commitJournal, $commitId, $commitMarker);
    $insert = $pdo->prepare('INSERT INTO schema_migrations (migration) VALUES (?)');
    $insert->execute([$commitMarker]);
    restore_recover_interrupted_operation($pdo, $commitJournal, $targets);

    foreach ($targets as $target) {
        assert_true(is_file($target . DIRECTORY_SEPARATOR . 'new.txt'), 'committed recovery keeps new media');
        assert_false(is_file($target . DIRECTORY_SEPARATOR . 'old.txt'), 'committed recovery removes old media');
    }
    assert_false(is_file($commitJournal), 'committed recovery removes completed journal');
    $checkMarker = $pdo->prepare('SELECT 1 FROM schema_migrations WHERE migration = ?');
    $checkMarker->execute([$commitMarker]);
    assert_false($checkMarker->fetchColumn() !== false, 'committed recovery removes DB marker');
} finally {
    test_restore_remove_tree($root);
}
