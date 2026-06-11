<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';

$pageTitle = 'Галерея - ' . app_name();
$perPage = (int) app_config()['PHOTOS_PER_PAGE'];
$page = max(1, get_int('page') ?? 1);
$offset = ($page - 1) * $perPage;
$photos = [];
$totalPhotos = 0;
$totalPages = 1;

try {
    $totalPhotos = (int) db()->query('SELECT COUNT(*) FROM photos')->fetchColumn();
    $totalPages = max(1, (int) ceil($totalPhotos / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $stmt = db()->prepare('SELECT id, title, thumbnail_filename, camera_model, taken_at FROM photos ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset');
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $photos = $stmt->fetchAll();
} catch (Throwable) {
    set_flash('error', 'Не вдалося завантажити галерею. Перевірте підключення до бази даних.');
}

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'header.php';
?>
<section class="page-title">
    <h1>Галерея</h1>
    <p>Сторінка <?= h((string) $page) ?> з <?= h((string) $totalPages) ?></p>
</section>

<?php if (empty($photos)): ?>
    <p class="empty-state">Фотографій поки немає.</p>
<?php else: ?>
    <div class="gallery-grid">
        <?php foreach ($photos as $photo): ?>
            <article class="photo-card">
                <a href="<?= h(url('photo.php?id=' . (int) $photo['id'])) ?>">
                    <img src="<?= h(uploads_url('thumbnails', $photo['thumbnail_filename'])) ?>" alt="<?= h($photo['title']) ?>" loading="lazy">
                    <span><?= h($photo['title']) ?></span>
                </a>
                <p><?= h($photo['taken_at'] ?: ($photo['camera_model'] ?: 'Немає даних')) ?></p>
            </article>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="pagination" aria-label="Пагінація">
            <?php if ($page > 1): ?>
                <a href="<?= h(url('gallery.php?page=' . ($page - 1))) ?>">Назад</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a class="<?= $i === $page ? 'active' : '' ?>" href="<?= h(url('gallery.php?page=' . $i)) ?>"><?= h((string) $i) ?></a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="<?= h(url('gallery.php?page=' . ($page + 1))) ?>">Вперед</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
<?php require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
