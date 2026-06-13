<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';

$pageTitle = 'Галерея - ' . app_name();
$perPage = (int) app_config()['PHOTOS_PER_PAGE'];
$page = max(1, get_int('page') ?? 1);
$offset = ($page - 1) * $perPage;
$photos = [];
$cameraOptions = [];
$albumOptions = [];
$totalPhotos = 0;
$totalPages = 1;
$search = get_query_string('q', 120);
$camera = get_query_string('camera', 150);
$albumId = get_album_id_from_request('album_id');
$dateFrom = get_query_string('date_from', 10);
$dateTo = get_query_string('date_to', 10);
$sort = get_query_string('sort', 30);
$dateFrom = normalize_date_query($dateFrom);
$dateTo = normalize_date_query($dateTo);

if ($dateFrom !== '' && $dateTo !== '' && $dateFrom > $dateTo) {
    [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
}

$sortOptions = [
    'newest' => 'Новіші спочатку',
    'oldest' => 'Старіші спочатку',
    'taken_newest' => 'Дата зйомки: новіші',
    'taken_oldest' => 'Дата зйомки: старіші',
    'title_az' => 'Назва: А-Я',
    'title_za' => 'Назва: Я-А',
];
$sortSql = [
    'newest' => 'photos.created_at DESC, photos.id DESC',
    'oldest' => 'photos.created_at ASC, photos.id ASC',
    'taken_newest' => 'photos.taken_at IS NULL ASC, photos.taken_at DESC, photos.created_at DESC, photos.id DESC',
    'taken_oldest' => 'photos.taken_at IS NULL ASC, photos.taken_at ASC, photos.created_at ASC, photos.id ASC',
    'title_az' => 'photos.title ASC, photos.id ASC',
    'title_za' => 'photos.title DESC, photos.id DESC',
];
$sort = array_key_exists($sort, $sortOptions) ? $sort : 'newest';
$filterParams = [
    'q' => $search,
    'camera' => $camera,
    'album_id' => $albumId,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'sort' => $sort === 'newest' ? '' : $sort,
];
$hasFilters = $search !== '' || $camera !== '' || $albumId !== null || $dateFrom !== '' || $dateTo !== '' || $sort !== 'newest';

try {
    $albumOptions = get_album_options(true);
    $cameraOptions = db()
        ->query("SELECT DISTINCT camera_model FROM photos WHERE camera_model IS NOT NULL AND camera_model <> '' ORDER BY camera_model ASC")
        ->fetchAll(PDO::FETCH_COLUMN);

    $where = [];
    $params = [];

    $searchCondition = photo_search_condition($search, false, $params);
    if ($searchCondition !== '') {
        $where[] = $searchCondition;
    }

    if ($camera !== '') {
        $where[] = 'photos.camera_model = :camera';
        $params['camera'] = $camera;
    }

    if ($albumId !== null) {
        $where[] = 'photos.album_id = :album_id';
        $params['album_id'] = $albumId;
    }

    if ($dateFrom !== '') {
        $where[] = 'photos.taken_at >= :date_from';
        $params['date_from'] = $dateFrom . ' 00:00:00';
    }

    if ($dateTo !== '') {
        $where[] = 'photos.taken_at <= :date_to';
        $params['date_to'] = $dateTo . ' 23:59:59';
    }

    $whereSql = empty($where) ? '' : ' WHERE ' . implode(' AND ', $where);

    $countStmt = db()->prepare('SELECT COUNT(*) FROM photos' . $whereSql);
    foreach ($params as $key => $value) {
        $countStmt->bindValue(':' . $key, $value);
    }
    $countStmt->execute();
    $totalPhotos = (int) $countStmt->fetchColumn();
    $totalPages = max(1, (int) ceil($totalPhotos / $perPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $stmt = db()->prepare(
        'SELECT photos.id, photos.filename, photos.thumbnail_filename, photos.width, photos.title, photos.camera_model, photos.taken_at, albums.name AS album_name
        FROM photos
        LEFT JOIN albums ON albums.id = photos.album_id' . $whereSql . '
        ORDER BY ' . $sortSql[$sort] . '
        LIMIT :limit OFFSET :offset'
    );
    foreach ($params as $key => $value) {
        $stmt->bindValue(':' . $key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $photos = $stmt->fetchAll();
} catch (Throwable $exception) {
    app_http_error('Не вдалося завантажити галерею. Перевірте підключення до бази даних.', 500, $exception);
}

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'header.php';
?>
<section class="page-title">
    <h1>Галерея</h1>
    <p>Знайдено <?= h((string) $totalPhotos) ?> фото. Сторінка <?= h((string) $page) ?> з <?= h((string) $totalPages) ?>.</p>
</section>

<form class="filter-panel" method="get" action="<?= h(url('gallery.php')) ?>">
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

<?php if (empty($photos)): ?>
    <p class="empty-state"><?= $hasFilters ? 'За цими фільтрами фотографій не знайдено.' : 'Фотографій поки немає.' ?></p>
<?php else: ?>
    <div class="gallery-grid">
        <?php foreach ($photos as $photo): ?>
            <article class="photo-card">
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

    <?php if ($totalPages > 1): ?>
        <nav class="pagination" aria-label="Пагінація">
            <?php if ($page > 1): ?>
                <a href="<?= h(url_with_query('gallery.php', array_merge($filterParams, ['page' => $page - 1]))) ?>">Назад</a>
            <?php endif; ?>

            <?php foreach (pagination_window($page, $totalPages) as $i): ?>
                <?php if ($i === null): ?>
                    <span class="pagination-gap">…</span>
                <?php else: ?>
                    <a class="<?= $i === $page ? 'active' : '' ?>" href="<?= h(url_with_query('gallery.php', array_merge($filterParams, ['page' => $i]))) ?>"><?= h((string) $i) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if ($page < $totalPages): ?>
                <a href="<?= h(url_with_query('gallery.php', array_merge($filterParams, ['page' => $page + 1]))) ?>">Вперед</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
<?php require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
