<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

$photoService = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'photo_service.php');
$editController = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'edit.php');

assert_true(
    str_contains($photoService, 'lock_version = lock_version + 1') && str_contains($photoService, 'AND lock_version = :lock_version'),
    'Photo edit optimistic lock must use an atomic integer revision compare/increment'
);

assert_true(
    str_contains((string) file_get_contents($root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'schema.sql'), '`lock_version` INT UNSIGNED NOT NULL DEFAULT 1'),
    'Photo schema must define integer lock_version'
);

assert_true(
    str_contains($photoService, "request_int(\$input, 'lock_version', null, 1)"),
    'Photo edit must reject missing optimistic lock tokens'
);

assert_true(
    str_contains($photoService, 'SELECT album_id, lock_version FROM photos WHERE id = ? FOR UPDATE')
        && str_contains($photoService, 'find_or_create_album($newAlbumName, 0, $pdo)'),
    'Photo edit must lock/check its revision before transactionally creating a requested album'
);
assert_true(
    str_contains($photoService, 'SELECT album_id FROM photos WHERE id = ? FOR UPDATE')
        && str_contains($photoService, 'SELECT id FROM albums WHERE id = ? FOR UPDATE'),
    'Album cover updates must lock the cover photo and album before revalidating membership'
);

assert_true(
    str_contains($editController, 'name="lock_version"'),
    'Photo edit form must submit the integer lock token'
);

$lockMigration = (string) file_get_contents(
    $root . DIRECTORY_SEPARATOR . 'database' . DIRECTORY_SEPARATOR . 'migrations' . DIRECTORY_SEPARATOR . '2026_07_10_add_photo_lock_version.sql'
);
assert_true(str_contains($lockMigration, 'information_schema.COLUMNS'), 'lock_version migration must be idempotent');
assert_true(str_contains($lockMigration, '@column_exists = 0'), 'lock_version migration must guard ALTER TABLE');

if (defined('TESTS_DB_AVAILABLE') && TESTS_DB_AVAILABLE) {
    require_once $root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'photo_service.php';
    $pdo = db();
    $filename = bin2hex(random_bytes(16)) . '.jpg';
    $thumbnail = bin2hex(random_bytes(16)) . '.jpg';
    $insert = $pdo->prepare(
        'INSERT INTO photos (filename, thumbnail_filename, original_name, title, mime_type, file_size)
         VALUES (?, ?, ?, ?, "image/jpeg", 1)'
    );
    $insert->execute([$filename, $thumbnail, 'lock-test.jpg', 'Lock test']);
    $photoId = (int) $pdo->lastInsertId();
    try {
        $input = ['title' => 'First edit', 'description' => '', 'tags' => '', 'album_id' => '', 'lock_version' => 1];
        update_photo_metadata($pdo, $photoId, $input);
        assert_throws(
            static fn () => update_photo_metadata(db(), $photoId, $input),
            InvalidArgumentException::class,
            'the second same-revision update must be rejected'
        );
        $orphanAlbum = 'stale-album-' . bin2hex(random_bytes(6));
        $staleInput = $input;
        $staleInput['new_album_name'] = $orphanAlbum;
        assert_throws(
            static fn () => update_photo_metadata(db(), $photoId, $staleInput),
            InvalidArgumentException::class,
            'a stale editor requesting a new album must be rejected'
        );
        $albumCheck = $pdo->prepare('SELECT COUNT(*) FROM albums WHERE name = ?');
        $albumCheck->execute([$orphanAlbum]);
        assert_equals(0, (int) $albumCheck->fetchColumn(), 'stale photo edit must not leave an empty album side effect');
    } finally {
        $delete = $pdo->prepare('DELETE FROM photos WHERE id = ?');
        $delete->execute([$photoId]);
    }
}
