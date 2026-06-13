<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';

$id = get_int('id');

if ($id === null || $id < 1) {
    http_response_code(404);
    require __DIR__ . DIRECTORY_SEPARATOR . '404.php';
    exit;
}

try {
    $stmt = db()->prepare(
        'SELECT photos.*, albums.name AS album_name
        FROM photos
        LEFT JOIN albums ON albums.id = photos.album_id
        WHERE photos.id = :id'
    );
    $stmt->execute(['id' => $id]);
    $photo = $stmt->fetch();
} catch (Throwable $exception) {
    app_http_error('Не вдалося завантажити фотографію.', 500, $exception);
}

if (!$photo) {
    http_response_code(404);
    require __DIR__ . DIRECTORY_SEPARATOR . '404.php';
    exit;
}

try {
    $prevStmt = db()->prepare(
        'SELECT id, title
        FROM photos
        WHERE created_at > :created_at OR (created_at = :created_at AND id > :id)
        ORDER BY created_at ASC, id ASC
        LIMIT 1'
    );
    $prevStmt->execute([
        'created_at' => $photo['created_at'],
        'id' => $id,
    ]);
    $previousPhoto = $prevStmt->fetch();

    $nextStmt = db()->prepare(
        'SELECT id, title
        FROM photos
        WHERE created_at < :created_at OR (created_at = :created_at AND id < :id)
        ORDER BY created_at DESC, id DESC
        LIMIT 1'
    );
    $nextStmt->execute([
        'created_at' => $photo['created_at'],
        'id' => $id,
    ]);
    $nextPhoto = $nextStmt->fetch();
} catch (Throwable $exception) {
    app_log_exception($exception, 'Photo navigation failed');
    $previousPhoto = false;
    $nextPhoto = false;
}

$pageTitle = $photo['title'] . ' - ' . app_name();
$exifRows = normalized_exif_for_display($photo['exif_json'], $photo);
$photoImageUrl = photo_display_url($photo);
$photoSrcset = photo_responsive_srcset($photo);

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
            data-lightbox-title="<?= h($photo['title']) ?>"
        >
            <img
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
            >
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
