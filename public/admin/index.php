<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'auth.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'csrf.php';

require_admin();

$pageTitle = 'Адмінпанель - ' . app_name();
$perPage = (int) app_config()['PHOTOS_PER_PAGE'];
$filters = normalize_gallery_filters($_GET);
$page = $filters['page'];
$search = $filters['q'];
$camera = $filters['camera'];
$albumId = $filters['album_id'];
$tagId = $filters['tag_id'];
$dateFrom = $filters['date_from'];
$dateTo = $filters['date_to'];
$sort = $filters['sort'];

$sortOptions = [
    'newest' => 'Новіші спочатку',
    'oldest' => 'Старіші спочатку',
    'taken_newest' => 'Дата зйомки: новіші',
    'taken_oldest' => 'Дата зйомки: старіші',
    'title_az' => 'Назва: А-Я',
    'title_za' => 'Назва: Я-А',
];

$filterParams = [
    'q' => $search,
    'camera' => $camera,
    'album_id' => $albumId,
    'tag_id' => $tagId,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'sort' => $sort === 'newest' ? '' : $sort,
];
$hasFilters = $search !== '' || $camera !== '' || $albumId !== null || $tagId !== null || $dateFrom !== '' || $dateTo !== '' || $sort !== 'newest';

$photos = [];
$cameraOptions = [];
$albumOptions = [];
$tagOptions = [];
$tagsByPhoto = [];
$totalPhotos = 0;
$totalPages = 1;

try {
    $options = fetch_filter_options(db(), true);
    $albumOptions = $options['albums'];
    $tagOptions = $options['tags'];
    $cameraOptions = $options['cameras'];

    $totalPhotos = count_photos(db(), $filters, true);
    $totalPages = max(1, (int) ceil($totalPhotos / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $photos = fetch_photos(db(), $filters, $perPage, $offset, true);
    $tagsByPhoto = get_photo_tags_map(array_column($photos, 'id'));
} catch (Throwable $exception) {
    app_http_error('Не вдалося завантажити список фотографій.', 500, $exception);
}

require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'header.php';
?>
<section class="admin-toolbar">
    <div>
        <h1>Адмінпанель</h1>
        <p>Керуйте фотографіями, назвами, описами та файлами. Знайдено <?= h((string) $totalPhotos) ?> фото. Сторінка <?= h((string) $page) ?> з <?= h((string) $totalPages) ?>.</p>
    </div>
    <div class="toolbar-actions">
        <a class="button secondary" href="<?= h(url('admin/stats.php')) ?>">Статистика</a>
        <a class="button secondary" href="<?= h(url('admin/albums.php')) ?>">Альбоми</a>
        <a class="button secondary" href="<?= h(url('admin/trash.php')) ?>">Кошик</a>
        <a class="button" href="<?= h(url('admin/upload.php')) ?>">Завантажити фото</a>
    </div>
</section>

<details class="filter-drawer" <?= $hasFilters ? 'open' : '' ?>>
    <summary class="filter-drawer-summary">
        <span>Пошук та фільтри</span>
        <span class="filter-badge"><?= $hasFilters ? '· Активні' : '' ?></span>
    </summary>
    <form class="filter-panel-inner" method="get" action="<?= h(url('admin/index.php')) ?>">
        <label>
            Пошук
            <input type="search" name="q" value="<?= h($search) ?>" placeholder="Назва, опис або файл">
        </label>
        <label>
            Камера
            <select name="camera">
                <option value="">Усі камери</option>
                <?php foreach ($cameraOptions as $cameraOption): ?>
                    <option value="<?= h((string) $cameraOption) ?>" <?= (string) $cameraOption === $camera ? 'selected' : '' ?>><?= h((string) $cameraOption) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Альбом
            <select name="album_id">
                <option value="">Усі альбоми</option>
                <?php foreach ($albumOptions as $album): ?>
                    <option value="<?= h((string) $album['id']) ?>" <?= (int) $album['id'] === $albumId ? 'selected' : '' ?>>
                        <?= h($album['name'] . ' (' . (int) $album['photo_count'] . ')') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Тег
            <select name="tag_id">
                <option value="">Усі теги</option>
                <?php foreach ($tagOptions as $tag): ?>
                    <option value="<?= h((string) $tag['id']) ?>" <?= (int) $tag['id'] === $tagId ? 'selected' : '' ?>>
                        <?= h($tag['name'] . ' (' . (int) $tag['photo_count'] . ')') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Від дати зйомки
            <input type="date" name="date_from" value="<?= h($dateFrom) ?>">
        </label>
        <label>
            До дати зйомки
            <input type="date" name="date_to" value="<?= h($dateTo) ?>">
        </label>
        <label>
            Сортування
            <select name="sort">
                <?php foreach ($sortOptions as $sortValue => $sortLabel): ?>
                    <option value="<?= h($sortValue) ?>" <?= $sortValue === $sort ? 'selected' : '' ?>><?= h($sortLabel) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="filter-actions">
            <button class="button" type="submit">Застосувати</button>
            <?php if ($hasFilters): ?>
                <a class="button secondary" href="<?= h(url('admin/index.php')) ?>">Скинути</a>
            <?php endif; ?>
        </div>
    </form>
</details>

<?php if (empty($photos)): ?>
    <p class="empty-state"><?= $hasFilters ? 'За цими фільтрами фотографій не знайдено.' : 'Фотографій ще немає.' ?></p>
<?php else: ?>
    <form method="post" action="<?= h(url('admin/bulk_edit.php')) ?>" id="bulk-edit-form">
        <?= csrf_field() ?>
        <div class="bulk-edit-bar">
            <label>
                <input type="checkbox" id="select-all-photos">
                Вибрати всі на сторінці
            </label>
            <button class="button" type="submit" name="bulk_action" value="edit">Масове редагування</button>
            <button class="button danger" type="submit" name="bulk_action" value="delete" onclick="return confirm('Ви дійсно хочете перемістити вибрані фотографії в кошик?')">Масове видалення</button>
        </div>
        <div class="admin-list admin-photo-list">
        <?php foreach ($photos as $photo): ?>
            <article class="admin-item admin-photo-item">
                <div class="admin-item-media">
                    <label class="photo-checkbox-label" aria-label="Вибрати фото <?= h($photo['title']) ?>">
                        <input type="checkbox" name="photo_ids[]" value="<?= h((string) $photo['id']) ?>" class="photo-checkbox">
                    </label>
                    <picture>
                        <?php
                        $avifSrcset = photo_responsive_srcset_next_gen($photo, 'avif');
                        if ($avifSrcset !== ''): ?>
                            <source srcset="<?= h($avifSrcset) ?>" type="image/avif" sizes="160px">
                        <?php endif; ?>
                        <?php
                        $webpSrcset = photo_responsive_srcset_next_gen($photo, 'webp');
                        if ($webpSrcset !== ''): ?>
                            <source srcset="<?= h($webpSrcset) ?>" type="image/webp" sizes="160px">
                        <?php endif; ?>
                        <img
                            src="<?= h(uploads_url('thumbnails', $photo['thumbnail_filename'])) ?>"
                            srcset="<?= h(photo_responsive_srcset($photo)) ?>"
                            sizes="160px"
                            alt="<?= h($photo['title']) ?>"
                            width="600"
                            height="400"
                            loading="lazy"
                        >
                    </picture>
                </div>
                <div class="admin-item-body">
                    <h2><?= h($photo['title']) ?></h2>
                    <div class="admin-meta">
                        <span><?= h($photo['original_name']) ?></span>
                        <span><?= h($photo['album_name'] ?: 'Без альбому') ?></span>
                        <span><?= h($photo['camera_model'] ?: 'Немає даних') ?></span>
                    </div>
                    <?php $photoTags = $tagsByPhoto[(int) $photo['id']] ?? []; ?>
                    <?php if (!empty($photoTags)): ?>
                        <div class="tag-list" aria-label="Теги фотографії">
                            <?php foreach ($photoTags as $tag): ?>
                                <a class="tag-pill" href="<?= h(url('admin/index.php?tag_id=' . (int) $tag['id'])) ?>"><?= h($tag['name']) ?></a>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="admin-actions">
                    <a class="button secondary" href="<?= h(url('photo.php?id=' . (int) $photo['id'])) ?>">Перегляд</a>
                    <a class="button secondary" href="<?= h(url('admin/download.php?id=' . (int) $photo['id'])) ?>">Оригінал</a>
                    <a class="button secondary" href="<?= h(url('admin/edit.php?id=' . (int) $photo['id'])) ?>">Редагувати</a>
                    <button class="button danger" type="submit" form="delete-form-<?= (int)$photo['id'] ?>">Видалити</button>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
    </form>

    <?php foreach ($photos as $photo): ?>
        <form id="delete-form-<?= (int)$photo['id'] ?>" method="post" action="<?= h(url('admin/delete.php')) ?>" data-confirm="Видалити фотографію?">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= h((string) $photo['id']) ?>">
        </form>
    <?php endforeach; ?>

    <?php if ($totalPages > 1): ?>
        <nav class="pagination" aria-label="Пагінація адмінпанелі">
            <?php if ($page > 1): ?>
                <a href="<?= h(url_with_query('admin/index.php', array_merge($filterParams, ['page' => $page - 1]))) ?>">Назад</a>
            <?php endif; ?>

            <?php foreach (pagination_window($page, $totalPages) as $i): ?>
                <?php if ($i === null): ?>
                    <span class="pagination-gap">…</span>
                <?php else: ?>
                    <a class="<?= $i === $page ? 'active' : '' ?>" href="<?= h(url_with_query('admin/index.php', array_merge($filterParams, ['page' => $i]))) ?>"><?= h((string) $i) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if ($page < $totalPages): ?>
                <a href="<?= h(url_with_query('admin/index.php', array_merge($filterParams, ['page' => $page + 1]))) ?>">Вперед</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
<?php require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
