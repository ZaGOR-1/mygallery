<?php
ini_set('display_errors', '1');
error_reporting(E_ALL);
require 'app/includes/functions.php';
require 'app/includes/db.php';
$pdo = db();
$sql = file_get_contents('database/migrations/2026_06_15_add_album_covers.sql');
try {
    $pdo->exec($sql);
    echo "Migration applied\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
var_dump($pdo->query("SHOW COLUMNS FROM albums LIKE 'cover_photo_id'")->fetch());
