<?php

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

require_once __DIR__ . '/../app/includes/db.php';
require_once __DIR__ . '/../app/includes/functions.php';

try {
    $pdo = db();
    $stmt = $pdo->query("SELECT id, filename FROM photos WHERE original_sha256 IS NULL");
    $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($photos) . " photos without original_sha256.\n";

    $updated = 0;
    $missing = 0;

    $updateStmt = $pdo->prepare("UPDATE photos SET original_sha256 = :sha256 WHERE id = :id");

    foreach ($photos as $photo) {
        $path = originals_path($photo['filename']);
        if (file_exists($path)) {
            $sha256 = hash_file('sha256', $path);
            if ($sha256 !== false) {
                $updateStmt->execute(['sha256' => $sha256, 'id' => $photo['id']]);
                $updated++;
            }
        } else {
            $missing++;
        }
    }

    echo "Backfill complete.\n";
    echo "Updated: $updated\n";
    if ($missing > 0) {
        echo "Missing original files: $missing\n";
    }

} catch (Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
