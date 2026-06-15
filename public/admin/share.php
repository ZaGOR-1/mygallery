<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/includes/functions.php';
require_once __DIR__ . '/../../app/includes/auth.php';
require_once __DIR__ . '/../../app/includes/csrf.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/index.php');
}

if (!verify_csrf()) {
    set_flash('error', 'Помилка CSRF. Оновіть сторінку і спробуйте ще раз.');
    $returnTo = $_POST['return_to'] ?? 'admin/index.php';
    redirect($returnTo);
}

$action = $_POST['action'] ?? '';

if ($action === 'create_photo_share') {
    $photoId = get_int('photo_id');
    if ($photoId > 0) {
        $token = bin2hex(random_bytes(16));
        $stmt = db()->prepare('INSERT INTO share_links (token, photo_id) VALUES (?, ?)');
        $stmt->execute([$token, $photoId]);
        set_flash('success', 'Створено нове приватне посилання на фото.');
        redirect_to('admin/edit.php', ['id' => $photoId]);
    }
} elseif ($action === 'create_album_share') {
    $albumId = get_int('album_id');
    if ($albumId > 0) {
        $token = bin2hex(random_bytes(16));
        $stmt = db()->prepare('INSERT INTO share_links (token, album_id) VALUES (?, ?)');
        $stmt->execute([$token, $albumId]);
        set_flash('success', 'Створено нове приватне посилання на альбом.');
        redirect_to('admin/albums.php', ['id' => $albumId]);
    }
} elseif ($action === 'revoke') {
    $id = get_int('id');
    $returnTo = $_POST['return_to'] ?? 'admin/index.php';
    if ($id > 0) {
        $stmt = db()->prepare('DELETE FROM share_links WHERE id = ?');
        $stmt->execute([$id]);
        set_flash('success', 'Приватне посилання відкликано.');
        redirect($returnTo);
    }
}

redirect('admin/index.php');
