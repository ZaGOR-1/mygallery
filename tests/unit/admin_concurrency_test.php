<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$tagService = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'tag_service.php');
$tagController = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'tags.php');
$albumController = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'albums.php');
$bulkController = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'bulk_edit.php');
$photoService = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'photo_service.php');

assert_true(str_contains($tagService, 'FOR UPDATE'), 'tag mutations must lock tag/relationship rows');
assert_true(str_contains($tagService, 'lock_version = lock_version + 1'), 'tag mutations must invalidate stale photo edit forms');
assert_true(str_contains($tagController, 'rename_tag_with_locking'), 'tag rename controller must use locking service');
assert_true(str_contains($tagController, 'merge_tags_with_locking'), 'tag merge controller must use locking service');
assert_true(str_contains($tagController, 'delete_tag_with_locking'), 'tag delete controller must use locking service');
assert_true(str_contains($albumController, 'create_album_strict($albumName, $isPrivate)'), 'admin album create must reject duplicates instead of find-or-create');
assert_true(str_contains($bulkController, 'ORDER BY id FOR UPDATE'), 'bulk album moves must lock photo rows before touching album cover rows');
assert_true(
    str_contains($photoService, 'ORDER BY sort_order ASC, name ASC, id ASC FOR UPDATE'),
    'album reorder must serialize the complete ordering set in a stable lock order'
);

if (defined('TESTS_DB_AVAILABLE') && TESTS_DB_AVAILABLE) {
    $pdo = db();
    $suffix = bin2hex(random_bytes(6));
    $filename = bin2hex(random_bytes(16)) . '.jpg';
    $thumbnail = bin2hex(random_bytes(16)) . '.jpg';
    $photoId = 0;
    $sourceId = 0;
    $targetId = 0;
    $albumId = 0;
    try {
        $insertPhoto = $pdo->prepare(
            'INSERT INTO photos (filename, thumbnail_filename, original_name, title, mime_type, file_size)
             VALUES (?, ?, ?, ?, "image/jpeg", 1)'
        );
        $insertPhoto->execute([$filename, $thumbnail, 'concurrency.jpg', 'Concurrency test']);
        $photoId = (int) $pdo->lastInsertId();
        $sourceId = find_or_create_tag('Source ' . $suffix);
        $targetId = find_or_create_tag('Target ' . $suffix);
        $link = $pdo->prepare('INSERT INTO photo_tags (photo_id, tag_id) VALUES (?, ?)');
        $link->execute([$photoId, $sourceId]);

        $revision = $pdo->prepare('SELECT lock_version FROM photos WHERE id = ?');

        rename_tag_with_locking($pdo, $sourceId, 'Renamed ' . $suffix);
        $revision->execute([$photoId]);
        assert_equals(2, (int) $revision->fetchColumn(), 'tag rename must bump photo revision');
        merge_tags_with_locking($pdo, $sourceId, $targetId);
        $revision->execute([$photoId]);
        assert_equals(3, (int) $revision->fetchColumn(), 'tag merge must bump photo revision');
        delete_tag_with_locking($pdo, $targetId);
        $revision->execute([$photoId]);
        assert_equals(4, (int) $revision->fetchColumn(), 'tag delete must bump photo revision');

        $albumName = 'Strict Album ' . $suffix;
        $albumId = create_album_strict($albumName, 0);
        assert_throws(
            static fn () => create_album_strict($albumName, 1),
            InvalidArgumentException::class,
            'duplicate strict album create must fail instead of reusing public album'
        );
        $privacy = $pdo->prepare('SELECT is_private FROM albums WHERE id = ?');
        $privacy->execute([$albumId]);
        assert_equals(0, (int) $privacy->fetchColumn(), 'duplicate private request must not mutate existing album privacy');
    } finally {
        if ($photoId > 0) {
            $stmt = $pdo->prepare('DELETE FROM photos WHERE id = ?');
            $stmt->execute([$photoId]);
        }
        foreach ([$sourceId, $targetId] as $tagId) {
            if ($tagId > 0) {
                $stmt = $pdo->prepare('DELETE FROM tags WHERE id = ?');
                $stmt->execute([$tagId]);
            }
        }
        if ($albumId > 0) {
            $stmt = $pdo->prepare('DELETE FROM albums WHERE id = ?');
            $stmt->execute([$albumId]);
        }
    }
}
