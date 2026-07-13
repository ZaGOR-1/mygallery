<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'photo_service.php';

$testDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mygallery_trash_manifest_' . bin2hex(random_bytes(6));
assert_true(mkdir($testDirectory, 0700), 'trash manifest test directory should be created');
$manifestPath = $testDirectory . DIRECTORY_SEPARATOR . str_repeat('a', 32) . '.json';
$entry = [
    'area' => 'storage',
    'folder' => 'originals',
    'filename' => str_repeat('b', 32) . '.jpg',
    'trash_filename' => str_repeat('a', 32) . '-0-' . str_repeat('b', 32) . '.jpg',
];
try {
    assert_true(file_put_contents($manifestPath, json_encode(['operation_id' => str_repeat('a', 32), 'files' => [$entry]])) !== false, 'trash manifest fixture should be written');
    assert_true(rewrite_trash_manifest_unresolved($manifestPath, [$entry], 'purge_partial'), 'partial purge must durably rewrite its manifest');
    $updated = json_decode((string) file_get_contents($manifestPath), true);
    assert_equals('purge_partial', $updated['status'] ?? null, 'partial manifest must expose recovery status');
    assert_equals([$entry], $updated['files'] ?? null, 'partial manifest must retain unresolved entries only');

    $previousPath = $manifestPath . '.previous';
    assert_true(rename($manifestPath, $previousPath), 'interrupted manifest fixture should retain the previous version');
    assert_true(recover_interrupted_trash_manifest_update($manifestPath), 'interrupted manifest replacement must recover its previous version');
    assert_true(is_file($manifestPath), 'recovered manifest must return to its canonical path');
    assert_false(is_file($previousPath), 'recovered manifest must not leave its previous-version marker');
} finally {
    foreach (glob($testDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
        unlink($file);
    }
    rmdir($testDirectory);
}

$fileFunctions = (string) file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'file_functions.php');
assert_true(str_contains($fileFunctions, "'rollback_partial'"), 'rollback failures must preserve a recovery manifest');
assert_true(str_contains($fileFunctions, "'purge_partial'"), 'purge failures must preserve a recovery manifest');
$photoService = (string) file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'photo_service.php');
assert_true(str_contains($photoService, "'restore_in_progress'"), 'trash restore must durably record its in-progress state before moving files');
assert_true(str_contains($photoService, "'restore_committed'"), 'trash restore must durably record its committed state');
assert_false(str_contains($photoService, '$rollbackUnresolved'), 'trash restore must resume forward instead of attempting a crash-unsafe file rollback');
assert_true(str_contains($photoService, 'SELECT * FROM photos WHERE id = :id FOR UPDATE'), 'delete must lock the current DB row before moving media');
assert_true(str_contains($photoService, 'if ($stmt->rowCount() !== 1)'), 'delete must verify its atomic DB mutation');
$recoverTool = (string) file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'recover_trash.php');
assert_true(str_contains($recoverTool, 'restore_photo_from_trash_unlocked(db(), $operationId)'), 'CLI recovery must resume interrupted restore state under its exclusive maintenance lock');

$stateDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mygallery_trash_state_' . bin2hex(random_bytes(6));
assert_true(mkdir($stateDirectory, 0700), 'trash state test directory should be created');
$livePath = $stateDirectory . DIRECTORY_SEPARATOR . 'live.jpg';
$trashPath = $stateDirectory . DIRECTORY_SEPARATOR . 'trash.jpg';
try {
    assert_equals('missing', trash_restore_file_state($livePath, $trashPath), 'missing restore copies must be detected');
    assert_equals(5, file_put_contents($trashPath, 'bytes'), 'trash-only fixture should be written');
    assert_equals('trash_only', trash_restore_file_state($livePath, $trashPath), 'trash-only restore state must be resumable');
    assert_true(rename($trashPath, $livePath), 'simulated interrupted restore move should succeed');
    assert_equals('live_only', trash_restore_file_state($livePath, $trashPath), 'a moved live-only file must be accepted on retry');
    assert_equals(5, file_put_contents($trashPath, 'bytes'), 'equal duplicate fixture should be written');
    assert_equals('both_equal', trash_restore_file_state($livePath, $trashPath), 'equal live/trash copies must be recognized');
    finalize_trash_restore_file(['from' => $livePath, 'trash' => $trashPath, 'filename' => 'fixture.jpg']);
    assert_equals('live_only', trash_restore_file_state($livePath, $trashPath), 'finalization must remove only a hash-verified trash duplicate');
    assert_equals(9, file_put_contents($trashPath, 'different'), 'conflicting duplicate fixture should be written');
    assert_equals('conflict', trash_restore_file_state($livePath, $trashPath), 'different live/trash bytes must fail closed');
} finally {
    foreach (glob($stateDirectory . DIRECTORY_SEPARATOR . '*') ?: [] as $file) {
        unlink($file);
    }
    rmdir($stateDirectory);
}

$photo = [
    'filename' => str_repeat('b', 32) . '.jpg',
    'thumbnail_filename' => str_repeat('c', 32) . '.jpg',
];
$operationId = str_repeat('d', 32);
$requiredFiles = [
    ['area' => 'storage', 'folder' => 'originals', 'filename' => $photo['filename'], 'trash_filename' => $operationId . '-0-' . $photo['filename']],
    ['area' => 'public', 'folder' => 'large', 'filename' => $photo['filename'], 'trash_filename' => $operationId . '-1-' . $photo['filename']],
    ['area' => 'public', 'folder' => 'thumbnails', 'filename' => $photo['thumbnail_filename'], 'trash_filename' => $operationId . '-2-' . $photo['thumbnail_filename']],
];
assert_true(trash_manifest_contains_required_photo_files($photo, $requiredFiles), 'complete canonical trash media set must be accepted');
array_pop($requiredFiles);
assert_false(trash_manifest_contains_required_photo_files($photo, $requiredFiles), 'trash manifest without thumbnail must be rejected');
assert_false(trash_manifest_contains_required_photo_files($photo, []), 'empty trash manifest must be rejected');
