<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';

$pageTitle = $pageTitle ?? app_name();
$flashMessages = get_flash_messages();
?>
<!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= h(local_url('assets/css/style.css')) ?>">
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <a class="logo" href="<?= h(url()) ?>"><?= h(app_name()) ?></a>
        <nav class="main-nav" aria-label="Головна навігація">
            <a href="<?= h(url('gallery.php')) ?>">Галерея</a>
            <?php if (is_admin_logged_in()): ?>
                <a href="<?= h(url('admin/index.php')) ?>">Адмінпанель</a>
                <a href="<?= h(url('admin/logout.php')) ?>">Вийти</a>
            <?php else: ?>
                <a href="<?= h(url('admin/login.php')) ?>">Вхід</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<main class="site-main">
    <div class="container">
        <?php foreach ($flashMessages as $message): ?>
            <div class="alert alert-<?= h($message['type']) ?>">
                <?= h($message['message']) ?>
            </div>
        <?php endforeach; ?>
