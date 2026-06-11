<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'auth.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'csrf.php';

require_admin();

$pageTitle = 'Адмінпанель - ' . app_name();
$photos = [];

try {
    $stmt = db()->query('SELECT id, title, thumbnail_filename, original_name, camera_model, created_at FROM photos ORDER BY created_at DESC, id DESC');
    $photos = $stmt->fetchAll();
} catch (Throwable) {
    set_flash('error', 'Не вдалося завантажити список фотографій.');
}

require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'header.php';
?>
<section class="admin-toolbar">
    <div>
        <h1>Адмінпанель</h1>
        <p>Керуйте фотографіями, назвами, описами та файлами.</p>
    </div>
    <a class="button" href="<?= h(url('admin/upload.php')) ?>">Завантажити фото</a>
</section>

<?php if (empty($photos)): ?>
    <p class="empty-state">Фотографій ще немає.</p>
<?php else: ?>
    <div class="admin-list">
        <?php foreach ($photos as $photo): ?>
            <article class="admin-item">
                <img src="<?= h(uploads_url('thumbnails', $photo['thumbnail_filename'])) ?>" alt="<?= h($photo['title']) ?>" loading="lazy">
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
<?php endif; ?>
<?php require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
