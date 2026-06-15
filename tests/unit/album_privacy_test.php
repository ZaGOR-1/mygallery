<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';

// 1. Check if column exists in the database
$stmt = db()->query("SHOW COLUMNS FROM albums");
$columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
assert_true(in_array('is_private', $columns, true), 'albums table must contain is_private column');

// 2. Test get_album_options and get_public_albums_with_covers behavior with a temporary album
$db = db();
$db->beginTransaction();

try {
    // Create a private album
    $stmt = $db->prepare("INSERT INTO albums (name, is_private) VALUES (:name, 1)");
    $privateAlbumName = 'Temp Private Album ' . bin2hex(random_bytes(8));
    $stmt->execute(['name' => $privateAlbumName]);
    $privateAlbumId = (int) $db->lastInsertId();

    // Create a public album
    $stmt = $db->prepare("INSERT INTO albums (name, is_private) VALUES (:name, 0)");
    $publicAlbumName = 'Temp Public Album ' . bin2hex(random_bytes(8));
    $stmt->execute(['name' => $publicAlbumName]);
    $publicAlbumId = (int) $db->lastInsertId();

    // Check get_album_options(false, false) -> should NOT include private, but include public
    $optionsPublic = get_album_options(false, false);
    $foundPrivate = false;
    $foundPublic = false;
    foreach ($optionsPublic as $opt) {
        if ((int)$opt['id'] === $privateAlbumId) $foundPrivate = true;
        if ((int)$opt['id'] === $publicAlbumId) $foundPublic = true;
    }
    assert_false($foundPrivate, 'get_album_options(false, false) must not return private albums');
    assert_true($foundPublic, 'get_album_options(false, false) must return public albums');

    // Check get_album_options(false, true) -> should include both
    $optionsAll = get_album_options(false, true);
    $foundPrivate = false;
    $foundPublic = false;
    foreach ($optionsAll as $opt) {
        if ((int)$opt['id'] === $privateAlbumId) $foundPrivate = true;
        if ((int)$opt['id'] === $publicAlbumId) $foundPublic = true;
    }
    assert_true($foundPrivate, 'get_album_options(false, true) must return private albums');
    assert_true($foundPublic, 'get_album_options(false, true) must return public albums');

    // Rollback to clean up database
    $db->rollBack();
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    throw $e;
}
