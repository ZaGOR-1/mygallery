<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';

if (!defined('TESTS_DB_AVAILABLE') || !TESTS_DB_AVAILABLE) {
    skip_test('DB not available.');
}

// Перевірка формату токена (32 символи hex)
$token = bin2hex(random_bytes(16));
assert_equals(32, strlen($token), 'Generated token must be 32 characters long');
assert_true(ctype_xdigit($token), 'Generated token must be hex characters only');

// Перевірка наявності таблиці в базі даних
$stmt = db()->query("SHOW TABLES LIKE 'share_links'");
assert_true($stmt->fetch() !== false, 'Database must contain share_links table');

// Перевірка колонок
$stmt = db()->query("SHOW COLUMNS FROM share_links");
$columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
assert_true(in_array('token', $columns, true), 'share_links must have token column');
assert_true(in_array('photo_id', $columns, true), 'share_links must have photo_id column');
assert_true(in_array('album_id', $columns, true), 'share_links must have album_id column');
assert_true(in_array('expires_at', $columns, true), 'share_links must have expires_at column');

$stmt = db()->prepare(
    "SELECT COUNT(*)
    FROM information_schema.TABLE_CONSTRAINTS
    WHERE CONSTRAINT_SCHEMA = DATABASE()
      AND TABLE_NAME = 'share_links'
      AND CONSTRAINT_NAME = 'chk_share_links_exactly_one_target'
      AND CONSTRAINT_TYPE = 'CHECK'"
);
$stmt->execute();
assert_equals(1, (int) $stmt->fetchColumn(), 'share_links must enforce exactly one target with a CHECK constraint');

$invalidToken = bin2hex(random_bytes(16));
$invalidTargetRejected = false;
try {
    $stmt = db()->prepare('INSERT INTO share_links (token, photo_id, album_id) VALUES (?, NULL, NULL)');
    $stmt->execute([$invalidToken]);
} catch (PDOException) {
    $invalidTargetRejected = true;
} finally {
    $stmt = db()->prepare('DELETE FROM share_links WHERE token = ?');
    $stmt->execute([$invalidToken]);
}
assert_true($invalidTargetRejected, 'share_links CHECK must reject a row without a target');
