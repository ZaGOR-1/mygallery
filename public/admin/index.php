<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'auth.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'csrf.php';

require_admin();

$pageTitle = 'Адмінпанель - ' . app_name();
$photos = [];
$perPage = (int) app_config()['PHOTOS_PER_PAGE'];
$page = max(1, get_int('page') ?? 1);
$offset = ($page - 1) * $perPage;
$totalPhotos = 0;
$totalPages = 1;

try {
    $totalPhotos = (int) db()->query('SELECT COUNT(*) FROM photos')->fetchColumn();
    $totalPages = max(1, (int) ceil($totalPhotos / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $stmt = db()->prepare('SELECT id, title, thumbnail_filename, original_name, camera_model, created_at FROM photos ORDER BY created_at DESC, id DESC LIMIT :limit OFFSET :offset');
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $photos = $stmt->fetchAll();
} catch (Throwable) {
    set_flash('error', 'Не вдалося завантажити список фотографій.');
}

require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'header.php';
?>
<section class="admin-toolbar">
    <div>
        <h1>Адмінпанель</h1>
        <p>Керуйте фотографіями, назвами, описами та файлами. Сторінка <?= h((string) $page) ?> з <?= h((string) $totalPages) ?>.</p>
    </div>
    <a class="button" href="<?= h(url('admin/upload.php')) ?>">Завантажити фото</a>
</section>

<?php if (empty($photos)): ?>
    <p class="empty-state">Фотографій ще немає.</p>
<?php else: ?>
    <div class="admin-list">
        <?php foreach ($photos as $photo): ?>
            <article class="admin-item">
                <img src="<?= h(uploads_url('thumbnails', $photo['thumbnail_filename'])) ?>" alt="<?= h($photo['title']) ?>" width="600" height="400" loading="lazy">
                <div>
                    <h2><?= h($photo['title']) ?></h2>
                    <p><?= h($photo['original_name']) ?></p>
                    <p><?= h($photo['camera_model'] ?: 'Немає даних') ?></p>
                </div>
                <div class="admin-actions">
                    <a class="button secondary" href="<?= h(url('photo.php?id=' . (int) $photo['id'])) ?>">Перегляд</a>
                    <a class="button secondary" href="<?= h(url('admin/edit.php?id=' . (int) $photo['id'])) ?>">Редагувати</a>
                    <form method="post" action="<?= h(url('admin/delete.php')) ?>" data-confirm="Видалити фотографію?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= h((string) $photo['id']) ?>">
                        <button class="button danger" type="submit">Видалити</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="pagination" aria-label="Пагінація адмінпанелі">
            <?php if ($page > 1): ?>
                <a href="<?= h(url('admin/index.php?page=' . ($page - 1))) ?>">Назад</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a class="<?= $i === $page ? 'active' : '' ?>" href="<?= h(url('admin/index.php?page=' . $i)) ?>"><?= h((string) $i) ?></a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="<?= h(url('admin/index.php?page=' . ($page + 1))) ?>">Вперед</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
<?php require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
