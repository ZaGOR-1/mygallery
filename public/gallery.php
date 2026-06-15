<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';

$isSharedView = $isSharedView ?? false;

$pageTitle = 'Галерея - ' . app_name();
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
    $options = fetch_filter_options(db());
    $albumOptions = $options['albums'];
    $tagOptions = $options['tags'];
    $cameraOptions = $options['cameras'];

    $totalPhotos = count_photos(db(), $filters, false);
    $totalPages = max(1, (int) ceil($totalPhotos / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $photos = fetch_photos(db(), $filters, $perPage, $offset, false);
    $tagsByPhoto = get_photo_tags_map(array_column($photos, 'id'));
} catch (Throwable $exception) {
    app_http_error('Не вдалося завантажити галерею. Перевірте підключення до бази даних.', 500, $exception);
}

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'header.php';
?>
<?php
$selectedAlbumName = null;
if ($albumId !== null) {
    try {
        $stmt = db()->prepare('SELECT name FROM albums WHERE id = ?');
        $stmt->execute([$albumId]);
        $albumRow = $stmt->fetch();
        if ($albumRow) {
            $selectedAlbumName = (string) $albumRow['name'];
        }
    } catch (Throwable $e) {
        // Ignore
    }
}
?>
<section class="<?= ($albumId !== null && $totalPhotos > 0) ? 'page-title-has-actions' : 'page-title' ?>">
    <div>
        <h1><?= h($selectedAlbumName ?? 'Галерея') ?></h1>
        <p>Знайдено <?= h((string) $totalPhotos) ?> фото. Сторінка <?= h((string) $page) ?> з <?= h((string) $totalPages) ?>.</p>
    </div>
    <?php if ($albumId !== null && $totalPhotos > 0): ?>
        <div class="page-title-actions">
            <?php if ($isSharedView): ?>
                <a class="button" href="<?= h(url('download_album.php?token=' . urlencode($token))) ?>">Завантажити альбом (ZIP)</a>
            <?php else: ?>
                <a class="button" href="<?= h(url('download_album.php?album_id=' . (int) $albumId)) ?>">Завантажити альбом (ZIP)</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</section>

<?php if (!$isSharedView): ?>
<details class="filter-drawer" <?= $hasFilters ? 'open' : '' ?>>
    <summary class="filter-drawer-summary">
        <span>Пошук та фільтри</span>
        <span class="filter-badge"><?= $hasFilters ? '· Активні' : '' ?></span>
    </summary>
    <form class="filter-panel-inner" method="get" action="<?= h(url('gallery.php')) ?>">
        <label>
            Пошук
            <input type="search" name="q" value="<?= h($search) ?>" placeholder="Назва або опис">
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
                <a class="button secondary" href="<?= h(url('gallery.php')) ?>">Скинути</a>
            <?php endif; ?>
        </div>
    </form>
</details>
<?php endif; ?>

<?php if (empty($photos)): ?>
    <p class="empty-state"><?= $hasFilters ? 'За цими фільтрами фотографій не знайдено.' : 'Фотографій поки немає.' ?></p>
<?php else: ?>
    <div class="gallery-grid">
        <?php foreach ($photos as $photo): ?>
            <article class="photo-card">
                <?php if ($isSharedView): ?>
                    <a href="<?= h(url('share.php?token=' . $token . '&view_photo=' . (int) $photo['id'])) ?>">
                <?php else: ?>
                    <a href="<?= h(url('photo.php?id=' . (int) $photo['id'])) ?>">
                <?php endif; ?>
                    <picture>
                        <?php
                        $avifSrcset = photo_responsive_srcset_next_gen($photo, 'avif');
                        if ($avifSrcset !== ''): ?>
                            <source srcset="<?= h($avifSrcset) ?>" type="image/avif" sizes="<?= h(photo_card_sizes()) ?>">
                        <?php endif; ?>
                        <?php
                        $webpSrcset = photo_responsive_srcset_next_gen($photo, 'webp');
                        if ($webpSrcset !== ''): ?>
                            <source srcset="<?= h($webpSrcset) ?>" type="image/webp" sizes="<?= h(photo_card_sizes()) ?>">
                        <?php endif; ?>
                        <img
                            data-dominant-color="<?= h((string) ($photo['dominant_color'] ?? '')) ?>"
                            src="<?= h(uploads_url('thumbnails', $photo['thumbnail_filename'])) ?>"
                            srcset="<?= h(photo_responsive_srcset($photo)) ?>"
                            sizes="<?= h(photo_card_sizes()) ?>"
                            alt="<?= h($photo['title']) ?>"
                            width="600"
                            height="400"
                            loading="lazy"
                            onerror="this.style.opacity=0"
                        >
                    </picture>
                    <span><?= h($photo['title']) ?></span>
                </a>
                <p><?= h($photo['album_name'] ?: ($photo['taken_at'] ?: ($photo['camera_model'] ?: 'Немає даних'))) ?></p>
                <?php $photoTags = $tagsByPhoto[(int) $photo['id']] ?? []; ?>
                <?php if (!empty($photoTags)): ?>
                    <div class="tag-list card-tags" aria-label="Теги фотографії">
                        <?php foreach ($photoTags as $tag): ?>
                            <?php if ($isSharedView): ?>
                                <span class="tag-pill tag-pill-static"><?= h($tag['name']) ?></span>
                            <?php else: ?>
                                <a class="tag-pill" href="<?= h(url('gallery.php?tag_id=' . (int) $tag['id'])) ?>"><?= h($tag['name']) ?></a>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="pagination" aria-label="Пагінація">
            <?php if ($page > 1): ?>
                <?php if ($isSharedView): ?>
                    <a href="<?= h(url('share.php?token=' . $token . '&page=' . ($page - 1))) ?>">Назад</a>
                <?php else: ?>
                    <a href="<?= h(url_with_query('gallery.php', array_merge($filterParams, ['page' => $page - 1]))) ?>">Назад</a>
                <?php endif; ?>
            <?php endif; ?>

            <?php foreach (pagination_window($page, $totalPages) as $i): ?>
                <?php if ($i === null): ?>
                    <span class="pagination-gap">…</span>
                <?php elseif ($i === $page): ?>
                    <strong aria-current="page"><?= h((string) $i) ?></strong>
                <?php else: ?>
                    <?php if ($isSharedView): ?>
                        <a href="<?= h(url('share.php?token=' . $token . '&page=' . $i)) ?>"><?= h((string) $i) ?></a>
                    <?php else: ?>
                        <a href="<?= h(url_with_query('gallery.php', array_merge($filterParams, ['page' => $i]))) ?>"><?= h((string) $i) ?></a>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if ($page < $totalPages): ?>
                <?php if ($isSharedView): ?>
                    <a href="<?= h(url('share.php?token=' . $token . '&page=' . ($page + 1))) ?>">Вперед</a>
                <?php else: ?>
                    <a href="<?= h(url_with_query('gallery.php', array_merge($filterParams, ['page' => $page + 1]))) ?>">Вперед</a>
                <?php endif; ?>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
<?php require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
