<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';

$pageTitle = 'Галерея - ' . app_name();
$perPage = (int) app_config()['PHOTOS_PER_PAGE'];
$page = max(1, get_int('page') ?? 1);
$offset = ($page - 1) * $perPage;
$photos = [];
$cameraOptions = [];
$totalPhotos = 0;
$totalPages = 1;
$search = get_query_string('q', 120);
$camera = get_query_string('camera', 150);
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
    'newest' => 'created_at DESC, id DESC',
    'oldest' => 'created_at ASC, id ASC',
    'taken_newest' => 'taken_at IS NULL ASC, taken_at DESC, created_at DESC, id DESC',
    'taken_oldest' => 'taken_at IS NULL ASC, taken_at ASC, created_at ASC, id ASC',
    'title_az' => 'title ASC, id ASC',
    'title_za' => 'title DESC, id DESC',
];
$sort = array_key_exists($sort, $sortOptions) ? $sort : 'newest';
$filterParams = [
    'q' => $search,
    'camera' => $camera,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
    'sort' => $sort === 'newest' ? '' : $sort,
];
$hasFilters = $search !== '' || $camera !== '' || $dateFrom !== '' || $dateTo !== '' || $sort !== 'newest';

try {
    $cameraOptions = db()
        ->query("SELECT DISTINCT camera_model FROM photos WHERE camera_model IS NOT NULL AND camera_model <> '' ORDER BY camera_model ASC")
        ->fetchAll(PDO::FETCH_COLUMN);

    $where = [];
    $params = [];

    if ($search !== '') {
        $where[] = '(title LIKE :search_title OR description LIKE :search_description)';
        $params['search_title'] = '%' . $search . '%';
        $params['search_description'] = '%' . $search . '%';
    }

    if ($camera !== '') {
        $where[] = 'camera_model = :camera';
        $params['camera'] = $camera;
    }

    if ($dateFrom !== '') {
        $where[] = 'taken_at >= :date_from';
        $params['date_from'] = $dateFrom . ' 00:00:00';
    }

    if ($dateTo !== '') {
        $where[] = 'taken_at <= :date_to';
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
        'SELECT id, title, thumbnail_filename, camera_model, taken_at
        FROM photos' . $whereSql . '
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
    set_flash('error', 'Не вдалося завантажити галерею. Перевірте підключення до бази даних.');
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
                    <img src="<?= h(uploads_url('thumbnails', $photo['thumbnail_filename'])) ?>" alt="<?= h($photo['title']) ?>" width="600" height="400" loading="lazy">
                    <span><?= h($photo['title']) ?></span>
                </a>
                <p><?= h($photo['taken_at'] ?: ($photo['camera_model'] ?: 'Немає даних')) ?></p>
            </article>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="pagination" aria-label="Пагінація">
            <?php if ($page > 1): ?>
                <a href="<?= h(url_with_query('gallery.php', array_merge($filterParams, ['page' => $page - 1]))) ?>">Назад</a>
            <?php endif; ?>

            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <a class="<?= $i === $page ? 'active' : '' ?>" href="<?= h(url_with_query('gallery.php', array_merge($filterParams, ['page' => $i]))) ?>"><?= h((string) $i) ?></a>
            <?php endfor; ?>

            <?php if ($page < $totalPages): ?>
                <a href="<?= h(url_with_query('gallery.php', array_merge($filterParams, ['page' => $page + 1]))) ?>">Вперед</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>
<?php require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
