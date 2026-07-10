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

    // Fault after all directory swaps but before DB commit: absent marker must restore old media.
    $rollbackId = bin2hex(random_bytes(16));
    $rollbackMarker = '__mygallery_restore__' . $rollbackId;
    $rollbackJournal = $root . DIRECTORY_SEPARATOR . 'rollback-journal.json';
    $rollbackMappings = restore_build_mappings($rollbackId, $targets);
    restore_prepare_staging($rollbackMappings);
    foreach ($rollbackMappings as $group => $mapping) {
        assert_true(
            file_put_contents($mapping['stage'] . DIRECTORY_SEPARATOR . 'new.txt', 'new-' . $group) !== false,
            'new rollback fixture'
        );
    }
    restore_write_journal($rollbackJournal, $rollbackId, $rollbackMarker, $rollbackMappings);
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
