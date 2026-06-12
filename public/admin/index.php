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
$cameraOptions = [];
$albumOptions = [];
$search = get_query_string('q', 120);
$camera = get_query_string('camera', 150);
$albumId = get_album_id_from_request('album_id');
$dateFrom = get_query_string('date_from', 10);
$dateTo = get_query_string('date_to', 10);
$sort = get_query_string('sort', 30);
$datePattern = '/^\d{4}-\d{2}-\d{2}$/';
$dateFrom = preg_match($datePattern, $dateFrom) ? $dateFrom : '';
$dateTo = preg_match($datePattern, $dateTo) ? $dateTo : '';

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

    if ($search !== '') {
        $where[] = '(photos.title LIKE :search_title OR photos.description LIKE :search_description OR photos.original_name LIKE :search_original)';
        $params['search_title'] = '%' . $search . '%';
        $params['search_description'] = '%' . $search . '%';
        $params['search_original'] = '%' . $search . '%';
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
        'SELECT photos.id, photos.title, photos.thumbnail_filename, photos.original_name, photos.camera_model, photos.created_at, albums.name AS album_name
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
} catch (Throwable) {
    set_flash('error', 'Не вдалося завантажити список фотографій.');
}

require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'header.php';
?>
<section class="admin-toolbar">
    <div>
        <h1>Адмінпанель</h1>
        <p>Керуйте фотографіями, назвами, описами та файлами. Знайдено <?= h((string) $totalPhotos) ?> фото. Сторінка <?= h((string) $page) ?> з <?= h((string) $totalPages) ?>.</p>
    </div>
    <div class="toolbar-actions">
        <a class="button secondary" href="<?= h(url('admin/albums.php')) ?>">Альбоми</a>
        <a class="button" href="<?= h(url('admin/upload.php')) ?>">Завантажити фото</a>
    </div>
</section>

<form class="filter-panel" method="get" action="<?= h(url('admin/index.php')) ?>">
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

<?php if (empty($photos)): ?>
    <p class="empty-state"><?= $hasFilters ? 'За цими фільтрами фотографій не знайдено.' : 'Фотографій ще немає.' ?></p>
<?php else: ?>
    <div class="admin-list">
        <?php foreach ($photos as $photo): ?>
            <article class="admin-item">
                <img src="<?= h(uploads_url('thumbnails', $photo['thumbnail_filename'])) ?>" alt="<?= h($photo['title']) ?>" width="600" height="400" loading="lazy">
                <div>
                    <h2><?= h($photo['title']) ?></h2>
                    <p><?= h($photo['original_name']) ?></p>
                    <p><?= h($photo['album_name'] ?: 'Без альбому') ?></p>
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
                <a href="<?= h(url_with_query('admin/index.php', array_merge($filterParams, ['page' => $page - 1]))) ?>">Назад</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a class="<?= $i === $page ? 'active' : '' ?>" href="<?= h(url_with_query('admin/index.php', array_merge($filterParams, ['page' => $i]))) ?>"><?= h((string) $i) ?></a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="<?= h(url_with_query('admin/index.php', array_merge($filterParams, ['page' => $page + 1]))) ?>">Вперед</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
<?php require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
