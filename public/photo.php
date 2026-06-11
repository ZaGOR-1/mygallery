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
    $stmt = db()->prepare('SELECT * FROM photos WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $photo = $stmt->fetch();
} catch (Throwable) {
    $photo = false;
    set_flash('error', 'Не вдалося завантажити фотографію.');
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
} catch (Throwable) {
    $previousPhoto = false;
    $nextPhoto = false;
}

$pageTitle = $photo['title'] . ' - ' . app_name();
$exifRows = normalized_exif_for_display($photo['exif_json'], $photo);

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'header.php';
?>
<article class="photo-view">
    <header class="photo-view-header">
        <div>
            <h1><?= h($photo['title']) ?></h1>
            <p><?= h($photo['description'] ?: 'Без опису') ?></p>
        </div>
        <?php if (is_admin_logged_in()): ?>
            <a class="button secondary" href="<?= h(url('admin/edit.php?id=' . (int) $photo['id'])) ?>">Редагувати</a>
        <?php endif; ?>
    </header>

    <figure class="large-photo">
        <img src="<?= h(photo_display_url($photo)) ?>" alt="<?= h($photo['title']) ?>">
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
