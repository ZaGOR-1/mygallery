<?php

declare(strict_types=1);

require_once __DIR__ . DIRECTORY_SEPARATOR . 'auth.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'csrf.php';

send_security_headers();

$pageTitle = $pageTitle ?? app_name();
$flashMessages = get_flash_messages();
?>
<!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($pageTitle) ?></title>
    <script>
        (function() {
            try {
                var savedTheme = localStorage.getItem('theme');
                if (savedTheme === 'light' || savedTheme === 'dark') {
                    document.documentElement.setAttribute('data-theme', savedTheme);
                }
            } catch (e) {}
        })();
    </script>
    <link rel="stylesheet" href="<?= h(local_url('assets/css/style.css')) ?>">
    <link rel="icon" href="<?= h(local_url('assets/favicon.svg')) ?>" type="image/svg+xml">
</head>
<body>
<header class="site-header">
    <div class="container header-inner">
        <a class="logo" href="<?= h(url()) ?>"><?= h(app_name()) ?></a>
        <nav class="main-nav" aria-label="Головна навігація">
            <a href="<?= h(url('gallery.php')) ?>">Галерея</a>
            <a href="<?= h(url('albums.php')) ?>">Всі альбоми</a>
            <button class="nav-button theme-toggle" id="theme-toggle" aria-label="Перемкнути тему" title="Перемкнути тему">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="theme-icon-light"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="theme-icon-dark"><path d="M21 12.79A9 9 9 0 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
            </button>
            <?php if (is_admin_logged_in()): ?>
                <a href="<?= h(url('admin/index.php')) ?>">Адмінпанель</a>
                <a href="<?= h(url('admin/albums.php')) ?>">Альбоми</a>
                <a href="<?= h(url('admin/tags.php')) ?>">Теги</a>
                <a href="<?= h(url('admin/stats.php')) ?>">Статистика</a>
                <a href="<?= h(url('admin/health.php')) ?>">Health</a>
                <form class="logout-form" method="post" action="<?= h(url('admin/logout.php')) ?>">
                    <?= csrf_field() ?>
                    <button class="nav-button" type="submit">Вийти</button>
                </form>
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
