<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';
require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'auth.php';

$id = get_int('id');

if ($id === null || $id < 1) {
    http_response_code(404);
    require __DIR__ . DIRECTORY_SEPARATOR . '404.php';
    exit;
}

try {
    $photo = fetch_photo_by_id(db(), $id);
} catch (Throwable $exception) {
    app_http_error('Не вдалося завантажити фотографію.', 500, $exception);
}

if (!$photo) {
    http_response_code(404);
    require __DIR__ . DIRECTORY_SEPARATOR . '404.php';
    exit;
}

$isPrivatePhoto = (int) ($photo['album_is_private'] ?? 0) === 1;
if ($isPrivatePhoto && !is_admin_logged_in()) {
    http_response_code(404);
    require __DIR__ . DIRECTORY_SEPARATOR . '404.php';
    exit;
}

if ($isPrivatePhoto) {
    $GLOBALS['mygallery_referrer_policy'] = 'no-referrer';
    send_private_cache_headers();
}

try {
    if (is_admin_logged_in()) {
        $prevStmt = db()->prepare(
            'SELECT id, title
            FROM photos
            WHERE created_at > :created_at_after OR (created_at = :created_at_same_after AND id > :id_after)
            ORDER BY created_at ASC, id ASC
            LIMIT 1'
        );
        $prevStmt->execute([
            'created_at_after' => $photo['created_at'],
            'created_at_same_after' => $photo['created_at'],
            'id_after' => $id,
        ]);
        $previousPhoto = $prevStmt->fetch();

        $nextStmt = db()->prepare(
            'SELECT id, title
            FROM photos
            WHERE created_at < :created_at_before OR (created_at = :created_at_same_before AND id < :id_before)
            ORDER BY created_at DESC, id DESC
            LIMIT 1'
        );
        $nextStmt->execute([
            'created_at_before' => $photo['created_at'],
            'created_at_same_before' => $photo['created_at'],
            'id_before' => $id,
        ]);
        $nextPhoto = $nextStmt->fetch();
    } else {
        $prevStmt = db()->prepare(
            'SELECT photos.id, photos.title
            FROM photos
            LEFT JOIN albums ON albums.id = photos.album_id
            WHERE (albums.is_private IS NULL OR albums.is_private = 0)
              AND (photos.created_at > :created_at_after OR (photos.created_at = :created_at_same_after AND photos.id > :id_after))
            ORDER BY photos.created_at ASC, photos.id ASC
            LIMIT 1'
        );
        $prevStmt->execute([
            'created_at_after' => $photo['created_at'],
            'created_at_same_after' => $photo['created_at'],
            'id_after' => $id,
        ]);
        $previousPhoto = $prevStmt->fetch();

        $nextStmt = db()->prepare(
            'SELECT photos.id, photos.title
            FROM photos
            LEFT JOIN albums ON albums.id = photos.album_id
            WHERE (albums.is_private IS NULL OR albums.is_private = 0)
              AND (photos.created_at < :created_at_before OR (photos.created_at = :created_at_same_before AND photos.id < :id_before))
            ORDER BY photos.created_at DESC, photos.id DESC
            LIMIT 1'
        );
        $nextStmt->execute([
            'created_at_before' => $photo['created_at'],
            'created_at_same_before' => $photo['created_at'],
            'id_before' => $id,
        ]);
        $nextPhoto = $nextStmt->fetch();
    }
} catch (Throwable $exception) {
    app_log_exception($exception, 'Photo navigation failed');
    $previousPhoto = false;
    $nextPhoto = false;
}

$pageTitle = $photo['title'] . ' - ' . app_name();
$exifRows = normalized_exif_for_display($photo['exif_json'], $photo);
$photoImageUrl = photo_display_url($photo);
$photoSrcset = photo_responsive_srcset($photo);

$largeWebpUrl = '';
$largeAvifUrl = '';
$filename = (string) ($photo['filename'] ?? '');
if ($filename !== '') {
    $nextGenWebp = preg_replace('/\.jpe?g$/i', '.webp', $filename);
    if (safe_existing_upload_file_path('large', $nextGenWebp) !== null) {
        $largeWebpUrl = photo_media_url($photo, 'large', 'webp');
    }
    $nextGenAvif = preg_replace('/\.jpe?g$/i', '.avif', $filename);
    if (safe_existing_upload_file_path('large', $nextGenAvif) !== null) {
        $largeAvifUrl = photo_media_url($photo, 'large', 'avif');
    }
}

try {
    $photoTags = get_photo_tags((int) $photo['id']);
} catch (Throwable $exception) {
    app_log_exception($exception, 'Photo tags failed');
    $photoTags = [];
}

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'header.php';
?>
<article class="photo-view">
    <header class="photo-view-header">
        <div>
            <h1><?= h($photo['title']) ?></h1>
            <?php if (!empty($photo['album_name'])): ?>
                <p><a class="muted-link" href="<?= h(url('gallery.php?album_id=' . (int) $photo['album_id'])) ?>"><?= h($photo['album_name']) ?></a></p>
            <?php endif; ?>
            <p><?= h($photo['description'] ?: 'Без опису') ?></p>
            <?php if (!empty($photoTags)): ?>
                <div class="tag-list" aria-label="Теги photo">
                    <?php foreach ($photoTags as $tag): ?>
                        <a class="tag-pill" href="<?= h(url('gallery.php?tag_id=' . (int) $tag['id'])) ?>"><?= h($tag['name']) ?></a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <?php if (is_admin_logged_in()): ?>
            <a class="button secondary" href="<?= h(url('admin/edit.php?id=' . (int) $photo['id'])) ?>">Редагувати</a>
        <?php endif; ?>
    </header>

    <figure class="large-photo">
        <a
            class="large-photo-link"
            href="<?= h($photoImageUrl) ?>"
            data-lightbox-src="<?= h($photoImageUrl) ?>"
            <?php if ($largeWebpUrl !== ''): ?>
                data-lightbox-src-webp="<?= h($largeWebpUrl) ?>"
            <?php endif; ?>
            <?php if ($largeAvifUrl !== ''): ?>
                data-lightbox-src-avif="<?= h($largeAvifUrl) ?>"
            <?php endif; ?>
            data-lightbox-title="<?= h($photo['title']) ?>"
        >
            <picture>
                <?php
                $avifSrcset = photo_responsive_srcset_next_gen($photo, 'avif');
                if ($avifSrcset !== ''): ?>
                    <source srcset="<?= h($avifSrcset) ?>" type="image/avif" sizes="<?= h(photo_view_sizes()) ?>">
                <?php endif; ?>
                <?php
                $webpSrcset = photo_responsive_srcset_next_gen($photo, 'webp');
                if ($webpSrcset !== ''): ?>
                    <source srcset="<?= h($webpSrcset) ?>" type="image/webp" sizes="<?= h(photo_view_sizes()) ?>">
                <?php endif; ?>
                <img
                    data-dominant-color="<?= h((string) ($photo['dominant_color'] ?? '')) ?>"
                    src="<?= h($photoImageUrl) ?>"
                    <?php if ($photoSrcset !== ''): ?>
                        srcset="<?= h($photoSrcset) ?>"
                        sizes="<?= h(photo_view_sizes()) ?>"
                    <?php endif; ?>
                    alt="<?= h($photo['title']) ?>"
                    <?php if ((int) $photo['width'] > 0 && (int) $photo['height'] > 0): ?>
                        width="<?= h((string) (int) $photo['width']) ?>"
                        height="<?= h((string) (int) $photo['height']) ?>"
                    <?php endif; ?>
                    data-hide-on-error="true"
                >
            </picture>
        </a>
    </figure>

    <nav class="photo-nav" aria-label="Навігація між фотографіями">
        <?php if ($previousPhoto): ?>
            <a href="<?= h(url('photo.php?id=' . (int) $previousPhoto['id'])) ?>">← <?= h($previousPhoto['title']) ?></a>
        <?php else: ?>
            <span></span>
        <?php endif; ?>

        <?php if ($nextPhoto): ?>
            <a href="<?= h(url('photo.php?id=' . (int) $nextPhoto['id'])) ?>"><?= h($nextPhoto['title']) ?> →</a>
        <?php endif; ?>
    </nav>

    <section class="exif-panel">
        <h2>EXIF-дані</h2>
        <dl>
            <?php foreach ($exifRows as $label => $value): ?>
                <div>
                    <dt><?= h($label) ?></dt>
                    <dd><?= h($value) ?></dd>
                </div>
            <?php endforeach; ?>
        </dl>
    </section>
</article>
<?php require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
