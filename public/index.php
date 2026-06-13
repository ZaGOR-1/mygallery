<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';

$pageTitle = app_name();

try {
    $stmt = db()->query('SELECT id, title, thumbnail_filename, camera_model, taken_at FROM photos ORDER BY created_at DESC, id DESC LIMIT 6');
    $latestPhotos = $stmt->fetchAll();
} catch (Throwable $exception) {
    app_http_error('Не вдалося завантажити головну сторінку. Перевірте підключення до бази даних.', 500, $exception);
}

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'header.php';
?>
<section class="hero">
    <div class="hero-content">
        <p class="eyebrow">JPG / JPEG · EXIF · Nikon D7100</p>
        <h1>Персональна фотогалерея</h1>
        <p>Темна мінімалістична галерея для зберігання фотографій, перегляду EXIF-даних і простого адміністрування.</p>
        <a class="button" href="<?= h(url('gallery.php')) ?>">Переглянути галерею</a>
    </div>
</section>

<section class="section-heading">
    <h2>Останні фотографії</h2>
    <a href="<?= h(url('gallery.php')) ?>">Усі фото</a>
</section>

<?php if (empty($latestPhotos)): ?>
    <p class="empty-state">Фотографій поки немає. Увійдіть в адмінпанель і завантажте перший JPEG-файл.</p>
<?php else: ?>
    <div class="gallery-grid">
        <?php foreach ($latestPhotos as $photo): ?>
            <article class="photo-card">
                <a href="<?= h(url('photo.php?id=' . (int) $photo['id'])) ?>">
                    <img src="<?= h(uploads_url('thumbnails', $photo['thumbnail_filename'])) ?>" alt="<?= h($photo['title']) ?>" width="600" height="400" loading="lazy">
                    <span><?= h($photo['title']) ?></span>
                </a>
                <p><?= h($photo['camera_model'] ?: 'Немає даних') ?></p>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
