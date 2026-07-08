<?php

declare(strict_types=1);

$root = dirname(__DIR__, 2);

$photoService = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'photo_service.php');
$editController = (string) file_get_contents($root . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'admin' . DIRECTORY_SEPARATOR . 'edit.php');

assert_true(
    str_contains($photoService, 'COALESCE(updated_at, created_at) AS lock_version'),
    'Photo edit optimistic lock must fall back to created_at when updated_at is NULL'
);

assert_true(
    str_contains($photoService, 'dominant_color, updated_at)') && str_contains($photoService, ':dominant_color, CURRENT_TIMESTAMP)'),
    'Photo upload must initialize updated_at for new rows'
);

assert_true(
    str_contains($photoService, "!isset(\$input['updated_at']) || \$input['updated_at'] === ''"),
    'Photo edit must reject missing optimistic lock tokens'
);

assert_true(
    str_contains($editController, "\$photo['created_at'] ?? ''"),
    'Photo edit form must use created_at as the hidden lock token when updated_at is NULL'
);
