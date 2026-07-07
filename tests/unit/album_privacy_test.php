<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';

if (!defined('TESTS_DB_AVAILABLE') || !TESTS_DB_AVAILABLE) {
    echo "  [SKIP] DB not available. ";
    return;
}

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

    $privatePhotoFilename = bin2hex(random_bytes(16)) . '.jpg';
    $privateThumbFilename = bin2hex(random_bytes(16)) . '.jpg';
    $publicPhotoFilename = bin2hex(random_bytes(16)) . '.jpg';
    $publicThumbFilename = bin2hex(random_bytes(16)) . '.jpg';
    $privateCamera = 'Private Camera ' . bin2hex(random_bytes(4));
    $publicCamera = 'Public Camera ' . bin2hex(random_bytes(4));

    $stmt = $db->prepare(
        'INSERT INTO photos (album_id, filename, thumbnail_filename, original_name, title, mime_type, file_size, camera_model)
        VALUES (?, ?, ?, ?, ?, "image/jpeg", 1, ?)'
    );
    $stmt->execute([$privateAlbumId, $privatePhotoFilename, $privateThumbFilename, 'private.jpg', 'Private photo', $privateCamera]);
    $privatePhotoId = (int) $db->lastInsertId();
    $stmt->execute([$publicAlbumId, $publicPhotoFilename, $publicThumbFilename, 'public.jpg', 'Public photo', $publicCamera]);
    $publicPhotoId = (int) $db->lastInsertId();

    $privateTag = 'Private Tag ' . bin2hex(random_bytes(4));
    $publicTag = 'Public Tag ' . bin2hex(random_bytes(4));
    $privateTagId = find_or_create_tag($privateTag);
    $publicTagId = find_or_create_tag($publicTag);

    $stmt = $db->prepare('INSERT INTO photo_tags (photo_id, tag_id) VALUES (?, ?)');
    $stmt->execute([$privatePhotoId, $privateTagId]);
    $stmt->execute([$publicPhotoId, $publicTagId]);

    $publicFilters = fetch_filter_options($db, false);
    assert_false(in_array($privateCamera, $publicFilters['cameras'], true), 'Public camera filters must not include private-only cameras');
    assert_true(in_array($publicCamera, $publicFilters['cameras'], true), 'Public camera filters must include public cameras');

    $publicTagNames = array_map(static fn (array $tag): string => (string) $tag['name'], $publicFilters['tags']);
    assert_false(in_array($privateTag, $publicTagNames, true), 'Public tag filters must not include private-only tags');
    assert_true(in_array($publicTag, $publicTagNames, true), 'Public tag filters must include public tags');

    // Rollback to clean up database
    $db->rollBack();
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    throw $e;
}
