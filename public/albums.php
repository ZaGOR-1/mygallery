<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';

$pageTitle = 'Всі альбоми - ' . app_name();

try {
    $albums = get_public_albums_with_covers();
} catch (Throwable $exception) {
    app_log_exception($exception, 'Public albums page failed');
    $albums = [];
    $error = 'Не вдалося завантажити альбоми.';
}

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'header.php';
?>
<div class="gallery-toolbar">
    <h1>Всі альбоми</h1>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php elseif (empty($albums)): ?>
    <p class="empty-state">Альбомів поки немає.</p>
<?php else: ?>
    <div class="photo-grid">
        <?php foreach ($albums as $album): ?>
            <a href="<?= h(url('gallery.php?album_id=' . (int) $album['id'])) ?>" class="photo-card" style="text-decoration: none;">
                <?php if ($album['thumbnail_filename']): ?>
                    <img src="<?= h(url('uploads/thumbnails/' . $album['thumbnail_filename'])) ?>" alt="<?= h($album['name']) ?>" loading="lazy" style="width: 100%; aspect-ratio: 1; object-fit: cover; display: block;">
                <?php else: ?>
                    <div style="width: 100%; aspect-ratio: 1; background: var(--bg-hover); display: flex; align-items: center; justify-content: center; color: var(--text-muted);">
                        Без обкладинки
                    </div>
                <?php endif; ?>
                <div class="photo-info" style="border-top: 1px solid var(--border-color);">
                    <div class="photo-title"><?= h($album['name']) ?></div>
                    <div class="photo-meta"><?= h((string) (int) $album['photo_count']) ?> фото</div>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
