<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';

if (PHP_SAPI !== 'cli' || $argc !== 2) {
    fwrite(STDERR, "Usage: php tests/fixtures/seed_backup_fixture.php /path/to/snapshot.json\n");
    exit(1);
}
if (app_env() !== 'test') {
    fwrite(STDERR, "Backup fixture may run only with APP_ENV=test.\n");
    exit(1);
}

$snapshotPath = $argv[1];
$filename = str_repeat('a', 32) . '.jpg';
$thumbnail = str_repeat('b', 32) . '.jpg';
$originalPath = originals_path($filename);
$largePath = uploads_path('large', $filename);
$thumbnailPath = uploads_path('thumbnails', $thumbnail);

ensure_upload_folders();
$image = imagecreatetruecolor(1600, 1000);
if (!$image instanceof GdImage) {
    throw new RuntimeException('Could not create fixture JPEG.');
}
for ($row = 0; $row < 1000; $row += 20) {
    $color = imagecolorallocate($image, ($row * 7) % 255, ($row * 13) % 255, ($row * 19) % 255);
    imagefilledrectangle($image, 0, $row, 1599, min(999, $row + 19), $color);
}
if (!imagejpeg($image, $originalPath, 95)) {
    imagedestroy($image);
    throw new RuntimeException('Could not write fixture original.');
}
imagedestroy($image);
// Keep a valid JPEG while exercising multi-MiB streaming/hash paths.
if (file_put_contents($originalPath, random_bytes(2 * 1024 * 1024), FILE_APPEND) === false
    || !copy($originalPath, $largePath)) {
    throw new RuntimeException('Could not expand/copy fixture original.');
}
create_thumbnail($originalPath, $thumbnailPath, 1);
create_webp_copy($largePath);
create_webp_copy($thumbnailPath);
create_avif_copy($largePath);
create_avif_copy($thumbnailPath);

$pdo = db();
$pdo->beginTransaction();
try {
    $admin = $pdo->prepare('INSERT INTO admins (username, password_hash, session_version) VALUES (?, ?, 1)');
    $admin->execute(['fixture_admin', password_hash('Fixture-password-2026!', PASSWORD_DEFAULT)]);
    $album = $pdo->prepare('INSERT INTO albums (name, is_private, sort_order) VALUES (?, 1, 10)');
    $album->execute(['Літній архів — Київ']);
    $albumId = (int) $pdo->lastInsertId();
    $photo = $pdo->prepare(
        'INSERT INTO photos
        (album_id, filename, thumbnail_filename, original_name, title, description, mime_type, file_size, width, height, camera_make, camera_model, original_sha256, dominant_color, lock_version)
        VALUES (?, ?, ?, ?, ?, ?, "image/jpeg", ?, 1600, 1000, ?, ?, ?, "#336699", 1)'
    );
    $photo->execute([
        $albumId,
        $filename,
        $thumbnail,
        'Київ — літо.jpg',
        'Київська ніч 🌃',
        'Unicode metadata для backup → restore.',
        filesize($originalPath),
        'Nikon',
        'D7100',
        hash_file('sha256', $originalPath),
    ]);
    $photoId = (int) $pdo->lastInsertId();
    $cover = $pdo->prepare('UPDATE albums SET cover_photo_id = ? WHERE id = ?');
    $cover->execute([$photoId, $albumId]);
    $tag = $pdo->prepare('INSERT INTO tags (name, slug) VALUES (?, ?)');
    $tag->execute(['Нічне місто', 'nichne-misto']);
    $tagId = (int) $pdo->lastInsertId();
    $photoTag = $pdo->prepare('INSERT INTO photo_tags (photo_id, tag_id) VALUES (?, ?)');
    $photoTag->execute([$photoId, $tagId]);
    $share = $pdo->prepare('INSERT INTO share_links (token, album_id, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 30 DAY))');
    $share->execute([str_repeat('c', 32), $albumId]);
    $attempt = $pdo->prepare('INSERT INTO login_attempts (username, ip_address, attempts) VALUES (?, ?, 1)');
    $attempt->execute(['fixture_admin', '203.0.113.10']);
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $exception;
}

$tables = ['admins', 'albums', 'photos', 'tags', 'photo_tags', 'login_attempts', 'share_links', 'schema_migrations'];
$counts = [];
foreach ($tables as $table) {
    $counts[$table] = (int) $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
}
$files = [];
foreach ([$originalPath, $largePath, $thumbnailPath, derivative_path($largePath, 'webp'), derivative_path($largePath, 'avif'), derivative_path($thumbnailPath, 'webp'), derivative_path($thumbnailPath, 'avif')] as $path) {
    if (is_file($path)) {
        $relative = str_replace('\\', '/', substr($path, strlen(project_root_path()) + 1));
        $files[$relative] = ['size' => filesize($path), 'sha256' => hash_file('sha256', $path)];
    }
}
ksort($files);
$snapshot = ['counts' => $counts, 'files' => $files, 'title' => 'Київська ніч 🌃'];
if (file_put_contents($snapshotPath, json_encode($snapshot, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), LOCK_EX) === false) {
    throw new RuntimeException('Could not write backup fixture snapshot.');
}
echo "Non-empty backup fixture seeded: " . count($files) . " media files.\n";
