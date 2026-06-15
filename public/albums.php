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
<section class="page-title album-page-title">
    <h1>Всі альбоми</h1>
    <p>Оберіть альбом, щоб переглянути фотографії з цієї серії.</p>
</section>

<?php if (isset($error)): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php elseif (empty($albums)): ?>
    <p class="empty-state">Альбомів поки немає.</p>
<?php else: ?>
    <div class="album-grid">
        <?php foreach ($albums as $album): ?>
            <a href="<?= h(url('gallery.php?album_id=' . (int) $album['id'])) ?>" class="album-card">
                <?php if (!empty($album['thumbnail_filename'])): ?>
                    <picture>
                        <?php
                        $avifSrcset = photo_cover_srcset_next_gen($album, 'avif');
                        if ($avifSrcset !== ''): ?>
                            <source srcset="<?= h($avifSrcset) ?>" type="image/avif" sizes="(max-width: 760px) 100vw, (max-width: 1180px) 50vw, 560px">
                        <?php endif; ?>
                        <?php
                        $webpSrcset = photo_cover_srcset_next_gen($album, 'webp');
                        if ($webpSrcset !== ''): ?>
                            <source srcset="<?= h($webpSrcset) ?>" type="image/webp" sizes="(max-width: 760px) 100vw, (max-width: 1180px) 50vw, 560px">
                        <?php endif; ?>
                        <img
                            data-dominant-color="<?= h((string) ($album['dominant_color'] ?? '')) ?>"
                            src="<?= h(photo_display_url($album)) ?>"
                            srcset="<?= h(photo_cover_srcset($album)) ?>"
                            sizes="(max-width: 760px) 100vw, (max-width: 1180px) 50vw, 560px"
                            alt="<?= h($album['cover_title'] ?: $album['name']) ?>"
                            width="1200"
                            height="720"
                            loading="lazy"
                        >
                    </picture>
                <?php else: ?>
                    <div class="album-card-empty">
                        Без обкладинки
                    </div>
                <?php endif; ?>
                <div class="album-card-info">
                    <span class="album-card-kicker"><?= h((string) (int) $album['photo_count']) ?> фото</span>
                    <h2><?= h($album['name']) ?></h2>
                    <span class="album-card-link">Відкрити альбом</span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
