<?php

declare(strict_types=1);

require_once __DIR__ . '/../../app/includes/functions.php';
require_once __DIR__ . '/../../app/includes/auth.php';
require_once __DIR__ . '/../../app/includes/csrf.php';

require_admin();

function share_expires_at_from_post(): ?string
{
    $days = request_int($_POST, 'expires_in', 30);
    $allowedDays = [1, 7, 30, 90, 0];
    if (!in_array($days, $allowedDays, true)) {
        $days = 30;
    }

    return $days === 0
        ? null
        : (new DateTimeImmutable('now'))->modify('+' . $days . ' days')->format('Y-m-d H:i:s');
}

function share_token_fingerprint(string $token): string
{
    return 'hint:' . share_token_hint($token) . ', sha256:' . substr(share_token_hash($token), 0, 12);
}

function share_audit_log(string $action, string $details): void
{
    $logFile = storage_path('logs' . DIRECTORY_SEPARATOR . 'share_audit.log');
    $adminUser = is_string($_SESSION['admin_username'] ?? null) ? $_SESSION['admin_username'] : 'unknown_admin';
    $timestamp = date('Y-m-d H:i:s');
    $ip = client_ip(default: 'unknown_ip');
    $message = str_replace(
        ["\r", "\n"],
        ' ',
        "[$timestamp] [IP: $ip] [Admin: $adminUser] Action: $action | $details"
    ) . PHP_EOL;
    if (!append_rotating_private_log($logFile, $message, 1048576, 5)) {
        app_log('Share audit fallback: ' . rtrim($message));
    }
}

function safe_share_return_path(string $path): string
{
    return preg_match('/\Aadmin\/(?:index\.php|edit\.php\?id=\d+|albums\.php\?edit=\d+)\z/', $path) === 1
        ? $path
        : 'admin/index.php';
}

if (!is_string($_SERVER['REQUEST_METHOD'] ?? null) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/index.php');
}

require_csrf();
$action = request_string($_POST, 'action', 40);

if ($action === 'create_photo_share') {
    $photoId = request_int($_POST, 'photo_id', null, 1);
    if ($photoId !== null) {
        $stmt = db()->prepare('SELECT id FROM photos WHERE id = ?');
        $stmt->execute([$photoId]);
        if (!$stmt->fetch()) {
            set_flash('error', 'Вказану фотографію не знайдено.');
            redirect('admin/index.php');
        }

        $token = bin2hex(random_bytes(16));
        $stmt = db()->prepare('INSERT INTO share_links (token_hash, token_hint, photo_id, expires_at) VALUES (?, ?, ?, ?)');
        $stmt->execute([share_token_hash($token), share_token_hint($token), $photoId, share_expires_at_from_post()]);
        set_one_time_secret('photo_share_' . $photoId, absolute_url('share.php?token=' . $token));
        share_audit_log('CREATE', "Created photo share link for photo_id=$photoId, token_fp=" . share_token_fingerprint($token));
        set_flash('success', 'Створено нове приватне посилання. Воно показується лише один раз.');
        redirect_to('admin/edit.php', ['id' => $photoId]);
    }
} elseif ($action === 'create_album_share') {
    $albumId = request_int($_POST, 'album_id', null, 1);
    if ($albumId !== null) {
        $stmt = db()->prepare('SELECT id FROM albums WHERE id = ?');
        $stmt->execute([$albumId]);
        if (!$stmt->fetch()) {
            set_flash('error', 'Вказаний альбом не знайдено.');
            redirect('admin/albums.php');
        }

        $token = bin2hex(random_bytes(16));
        $stmt = db()->prepare('INSERT INTO share_links (token_hash, token_hint, album_id, expires_at) VALUES (?, ?, ?, ?)');
        $stmt->execute([share_token_hash($token), share_token_hint($token), $albumId, share_expires_at_from_post()]);
        set_one_time_secret('album_share_' . $albumId, absolute_url('share.php?token=' . $token));
        share_audit_log('CREATE', "Created album share link for album_id=$albumId, token_fp=" . share_token_fingerprint($token));
        set_flash('success', 'Створено нове приватне посилання. Воно показується лише один раз.');
        redirect_to('admin/albums.php', ['edit' => $albumId]);
    }
} elseif ($action === 'revoke') {
    $id = request_int($_POST, 'id', null, 1);
    $returnTo = safe_share_return_path(request_raw_string($_POST, 'return_to', 'admin/index.php', 200));
    if ($id !== null) {
        $stmt = db()->prepare('SELECT token_hash, token_hint FROM share_links WHERE id = ?');
        $stmt->execute([$id]);
        $link = $stmt->fetch();
        if ($link) {
            $stmt = db()->prepare('DELETE FROM share_links WHERE id = ?');
            $stmt->execute([$id]);
            $fingerprint = 'hint:' . (string) ($link['token_hint'] ?? '') . ', sha256:' . substr((string) ($link['token_hash'] ?? ''), 0, 12);
            share_audit_log('REVOKE', "Revoked share link with id=$id, token_fp=$fingerprint");
            set_flash('success', 'Приватне посилання відкликано.');
        } else {
            set_flash('error', 'Посилання не знайдено.');
        }
        redirect($returnTo);
    }
}

set_flash('error', 'Некоректна операція з приватним посиланням.');
redirect('admin/index.php');
