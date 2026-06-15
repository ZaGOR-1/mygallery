<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';

$pageTitle = app_name();

try {
    $stmt = db()->query(
        'SELECT photos.id, photos.title, photos.filename, photos.thumbnail_filename, photos.width, photos.camera_model, photos.taken_at, photos.created_at, albums.name AS album_name
        FROM photos
        LEFT JOIN albums ON albums.id = photos.album_id
        ORDER BY photos.created_at DESC, photos.id DESC
        LIMIT 8'
    );
    $latestPhotos = $stmt->fetchAll();

    $stats = [
        'photos' => (int) db()->query('SELECT COUNT(*) FROM photos')->fetchColumn(),
        'albums' => (int) db()->query('SELECT COUNT(*) FROM albums')->fetchColumn(),
        'cameras' => (int) db()->query("SELECT COUNT(DISTINCT camera_model) FROM photos WHERE camera_model IS NOT NULL AND camera_model <> ''")->fetchColumn(),
        'latest' => (string) (db()->query('SELECT MAX(created_at) FROM photos')->fetchColumn() ?: ''),
    ];
} catch (Throwable $exception) {
    app_http_error('Не вдалося завантажити головну сторінку. Перевірте підключення до бази даних.', 500, $exception);
}

$heroPhoto = $latestPhotos[0] ?? null;
$latestLabel = 'Немає фото';
if ($stats['latest'] !== '') {
    $latestDate = DateTime::createFromFormat('Y-m-d H:i:s', $stats['latest']);
    $latestLabel = $latestDate instanceof DateTime ? $latestDate->format('d.m.Y') : $stats['latest'];
}

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'header.php';
?>
<section class="hero <?= $heroPhoto ? 'hero-with-photo' : 'hero-empty' ?>">
    <?php if ($heroPhoto): ?>
        <img
            class="hero-image"
            src="<?= h(photo_display_url($heroPhoto)) ?>"
            srcset="<?= h(photo_cover_srcset($heroPhoto)) ?>"
            sizes="(max-width: 900px) 100vw, 1180px"
            alt="<?= h($heroPhoto['title']) ?>"
            width="1600"
            height="900"
            loading="eager"
        >
    <?php endif; ?>
    <div class="hero-content">
        <p class="eyebrow">JPEG · EXIF · приватні оригінали</p>
        <h1>Персональна фотогалерея</h1>
        <p>Темна мінімалістична галерея для зберігання фотографій, перегляду EXIF-даних і простого адміністрування.</p>
        <div class="hero-actions">
            <a class="button" href="<?= h(url('gallery.php')) ?>">Переглянути галерею</a>
            <a class="button secondary" href="<?= h(url('albums.php')) ?>">Всі альбоми</a>
        </div>
        <?php if ($heroPhoto): ?>
            <p class="hero-caption">
                Останнє фото: <a href="<?= h(url('photo.php?id=' . (int) $heroPhoto['id'])) ?>"><?= h($heroPhoto['title']) ?></a>
            </p>
        <?php endif; ?>
    </div>
</section>

<section class="home-stats" aria-label="Статистика галереї">
    <div>
        <span><?= h((string) $stats['photos']) ?></span>
        <p>фото</p>
    </div>
    <div>
        <span><?= h((string) $stats['albums']) ?></span>
        <p>альбомів</p>
    </div>
    <div>
        <span><?= h((string) $stats['cameras']) ?></span>
        <p>камер</p>
    </div>
    <div>
        <span><?= h($latestLabel) ?></span>
        <p>останнє оновлення</p>
    </div>
</section>

<?php if (empty($latestPhotos)): ?>
    <p class="empty-state">Фотографій поки немає. Увійдіть в адмінпанель і завантажте перший JPEG-файл.</p>
<?php else: ?>
    <section class="section-heading home-section-heading">
        <div>
            <h2>Останні фотографії</h2>
            <p>Свіжі кадри з галереї, альбомів і камер.</p>
        </div>
        <a href="<?= h(url('gallery.php')) ?>">Усі фото</a>
    </section>

    <div class="gallery-grid home-photo-grid">
        <?php foreach ($latestPhotos as $photo): ?>
            <article class="photo-card home-photo-card">
                <a href="<?= h(url('photo.php?id=' . (int) $photo['id'])) ?>">
                    <img
                        src="<?= h(uploads_url('thumbnails', $photo['thumbnail_filename'])) ?>"
                        srcset="<?= h(photo_responsive_srcset($photo)) ?>"
                        sizes="<?= h(photo_card_sizes()) ?>"
                        alt="<?= h($photo['title']) ?>"
                        width="600"
                        height="400"
                        loading="lazy"
                    >
                    <span><?= h($photo['title']) ?></span>
                </a>
                <p><?= h($photo['album_name'] ?: ($photo['taken_at'] ?: ($photo['camera_model'] ?: 'Немає даних'))) ?></p>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
