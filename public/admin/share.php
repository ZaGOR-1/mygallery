<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/includes/functions.php';
require_once __DIR__ . '/../../app/includes/auth.php';
require_once __DIR__ . '/../../app/includes/csrf.php';

require_admin();

function share_expires_at_from_post(): ?string
{
    $days = (int) ($_POST['expires_in'] ?? 30);
    $allowedDays = [1, 7, 30, 90, 0];

    if (!in_array($days, $allowedDays, true)) {
        $days = 30;
    }

    if ($days === 0) {
        return null;
    }

    return (new DateTimeImmutable('now'))->modify('+' . $days . ' days')->format('Y-m-d H:i:s');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/index.php');
}

function share_audit_log(string $action, string $details): void
{
    $logFile = storage_path('logs' . DIRECTORY_SEPARATOR . 'share_audit.log');
    $logDir = dirname($logFile);
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }
    
    $adminUser = $_SESSION['admin_username'] ?? 'unknown_admin';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown_ip';
    $message = "[$timestamp] [IP: $ip] [Admin: $adminUser] Action: $action | $details\n";
    
    @file_put_contents($logFile, $message, FILE_APPEND | LOCK_EX);
}

require_csrf();

$action = $_POST['action'] ?? '';

if ($action === 'create_photo_share') {
    $photoId = get_int('photo_id');
    if ($photoId > 0) {
        $stmt = db()->prepare('SELECT id FROM photos WHERE id = ?');
        $stmt->execute([$photoId]);
        if (!$stmt->fetch()) {
            set_flash('error', 'Вказану фотографію не знайдено.');
            redirect('admin/index.php');
        }

        $token = bin2hex(random_bytes(16));
        $stmt = db()->prepare('INSERT INTO share_links (token, photo_id, expires_at) VALUES (?, ?, ?)');
        $stmt->execute([$token, $photoId, share_expires_at_from_post()]);
        share_audit_log('CREATE', "Created photo share link for photo_id=$photoId, token=$token");
        set_flash('success', 'Створено нове приватне посилання на фото.');
        redirect_to('admin/edit.php', ['id' => $photoId]);
    }
} elseif ($action === 'create_album_share') {
    $albumId = get_int('album_id');
    if ($albumId > 0) {
        $stmt = db()->prepare('SELECT id FROM albums WHERE id = ?');
        $stmt->execute([$albumId]);
        if (!$stmt->fetch()) {
            set_flash('error', 'Вказаний альбом не знайдено.');
            redirect('admin/albums.php');
        }

        $token = bin2hex(random_bytes(16));
        $stmt = db()->prepare('INSERT INTO share_links (token, album_id, expires_at) VALUES (?, ?, ?)');
        $stmt->execute([$token, $albumId, share_expires_at_from_post()]);
        share_audit_log('CREATE', "Created album share link for album_id=$albumId, token=$token");
        set_flash('success', 'Створено нове приватне посилання на альбом.');
        redirect_to('admin/albums.php', ['edit' => $albumId]);
    }
} elseif ($action === 'revoke') {
    $id = get_int('id');
    $returnTo = $_POST['return_to'] ?? 'admin/index.php';
    if ($id > 0) {
        $stmt = db()->prepare('SELECT token FROM share_links WHERE id = ?');
        $stmt->execute([$id]);
        $link = $stmt->fetch();
        if ($link) {
            $stmt = db()->prepare('DELETE FROM share_links WHERE id = ?');
            $stmt->execute([$id]);
            share_audit_log('REVOKE', "Revoked share link with id=$id, token=" . $link['token']);
            set_flash('success', 'Приватне посилання відкликано.');
        } else {
            set_flash('error', 'Посилання не знайдено.');
        }
        redirect($returnTo);
    }
}

redirect('admin/index.php');
