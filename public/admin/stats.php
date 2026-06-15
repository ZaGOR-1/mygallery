<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'auth.php';

require_admin();

$pageTitle = 'Статистика - ' . app_name();

function stats_count(string $sql): int
{
    return (int) db()->query($sql)->fetchColumn();
}

function stats_scalar(string $sql): string
{
    $value = db()->query($sql)->fetchColumn();

    return $value === false || $value === null ? '—' : (string) $value;
}

function stats_dir_size(string $path): int
{
    if (!is_dir($path)) {
        return 0;
    }

    $size = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );

    foreach ($iterator as $file) {
        /** @var SplFileInfo $file */
        if (!$file->isFile()) {
            continue;
        }

        $name = $file->getFilename();
        if ($name === '.gitkeep' || $name === '.htaccess') {
            continue;
        }

        $size += max(0, $file->getSize());
    }

    return $size;
}

function stats_percent(int $part, int $total): string
{
    if ($total <= 0) {
        return '0%';
    }

    return rtrim(rtrim(number_format(($part / $total) * 100, 1, '.', ''), '0'), '.') . '%';
}

function stats_rows(string $sql, array $params = []): array
{
    $stmt = db()->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(is_int($key) ? $key + 1 : ':' . $key, $value);
    }
    $stmt->execute();

    return $stmt->fetchAll();
}

$summaryCards = [];
$topCameras = [];
$topLenses = [];
$topTags = [];
$monthlyRows = [];
$latestRows = [];
$qualityRows = [];
$storageRows = [];

try {
    $totalPhotos = stats_count('SELECT COUNT(*) FROM photos');
    $totalAlbums = stats_count('SELECT COUNT(*) FROM albums');
    $totalTags = stats_count('SELECT COUNT(*) FROM tags');
    $withoutAlbum = stats_count('SELECT COUNT(*) FROM photos WHERE album_id IS NULL');
    $withoutDescription = stats_count("SELECT COUNT(*) FROM photos WHERE description IS NULL OR description = ''");
    $withoutExif = stats_count("SELECT COUNT(*) FROM photos WHERE exif_json IS NULL OR exif_json = ''");
    $withoutTakenAt = stats_count('SELECT COUNT(*) FROM photos WHERE taken_at IS NULL');
    $dbOriginalBytes = (int) db()->query('SELECT COALESCE(SUM(file_size), 0) FROM photos')->fetchColumn();
    $storageOriginalBytes = stats_dir_size(originals_path());
    $largeBytes = stats_dir_size(uploads_path('large'));
    $thumbnailBytes = stats_dir_size(uploads_path('thumbnails'));

    $summaryCards = [
        ['label' => 'Фото', 'value' => (string) $totalPhotos, 'hint' => 'Усього записів у БД'],
        ['label' => 'Альбоми', 'value' => (string) $totalAlbums, 'hint' => 'Створені альбоми'],
        ['label' => 'Теги', 'value' => (string) $totalTags, 'hint' => 'Активні теги'],
        ['label' => 'Оригінали', 'value' => bytes_for_display($storageOriginalBytes), 'hint' => 'storage/originals'],
        ['label' => 'Large', 'value' => bytes_for_display($largeBytes), 'hint' => 'public/uploads/large'],
        ['label' => 'Thumbnails', 'value' => bytes_for_display($thumbnailBytes), 'hint' => 'public/uploads/thumbnails'],
    ];

    $qualityRows = [
        ['label' => 'Без альбому', 'count' => $withoutAlbum, 'percent' => stats_percent($withoutAlbum, $totalPhotos)],
        ['label' => 'Без опису', 'count' => $withoutDescription, 'percent' => stats_percent($withoutDescription, $totalPhotos)],
        ['label' => 'Без EXIF JSON', 'count' => $withoutExif, 'percent' => stats_percent($withoutExif, $totalPhotos)],
        ['label' => 'Без дати зйомки', 'count' => $withoutTakenAt, 'percent' => stats_percent($withoutTakenAt, $totalPhotos)],
    ];

    $storageRows = [
        ['label' => 'DB SUM(file_size)', 'value' => bytes_for_display($dbOriginalBytes)],
        ['label' => 'storage/originals', 'value' => bytes_for_display($storageOriginalBytes)],
        ['label' => 'public/uploads/large', 'value' => bytes_for_display($largeBytes)],
        ['label' => 'public/uploads/thumbnails', 'value' => bytes_for_display($thumbnailBytes)],
        ['label' => 'Разом media-файли', 'value' => bytes_for_display($storageOriginalBytes + $largeBytes + $thumbnailBytes)],
    ];

    $topCameras = stats_rows(
        "SELECT camera_model AS name, COUNT(*) AS photo_count
        FROM photos
        WHERE camera_model IS NOT NULL AND camera_model <> ''
        GROUP BY camera_model
        ORDER BY photo_count DESC, camera_model ASC
        LIMIT 10"
    );

    $topLenses = stats_rows(
        "SELECT lens_model AS name, COUNT(*) AS photo_count
        FROM photos
        WHERE lens_model IS NOT NULL AND lens_model <> ''
        GROUP BY lens_model
        ORDER BY photo_count DESC, lens_model ASC
        LIMIT 10"
    );

    $topTags = stats_rows(
        'SELECT tags.id, tags.name, COUNT(photo_tags.photo_id) AS photo_count
        FROM tags
        INNER JOIN photo_tags ON photo_tags.tag_id = tags.id
        GROUP BY tags.id, tags.name
        ORDER BY photo_count DESC, tags.name ASC
        LIMIT 15'
    );

    $monthlyRows = stats_rows(
        "SELECT DATE_FORMAT(COALESCE(taken_at, created_at), '%Y-%m') AS period, COUNT(*) AS photo_count
        FROM photos
        GROUP BY period
        ORDER BY period DESC
        LIMIT 12"
    );

    $latestRows = stats_rows(
        'SELECT id, title, created_at
        FROM photos
        ORDER BY created_at DESC, id DESC
        LIMIT 8'
    );
} catch (Throwable $exception) {
    app_http_error('Не вдалося завантажити статистику. Перевірте міграції бази даних.', 500, $exception);
}

require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'header.php';
?>
<section class="admin-toolbar">
    <div>
        <h1>Статистика</h1>
        <p>Огляд контенту, EXIF-заповнення, тегів і media-сховища.</p>
    </div>
    <div class="toolbar-actions">
        <a class="button secondary" href="<?= h(url('admin/health.php')) ?>">Health</a>
        <a class="button secondary" href="<?= h(url('admin/index.php')) ?>">Адмінпанель</a>
    </div>
</section>

<section class="stats-grid" aria-label="Коротка статистика">
    <?php foreach ($summaryCards as $card): ?>
        <article class="stat-card">
            <span><?= h($card['label']) ?></span>
            <strong><?= h($card['value']) ?></strong>
            <small><?= h($card['hint']) ?></small>
        </article>
    <?php endforeach; ?>
</section>

<section class="stats-columns">
    <article class="table-panel">
        <h2>Якість заповнення</h2>
        <table>
            <thead><tr><th>Показник</th><th>Кількість</th><th>Частка</th></tr></thead>
            <tbody>
                <?php foreach ($qualityRows as $row): ?>
                    <tr><td><?= h($row['label']) ?></td><td><?= h((string) $row['count']) ?></td><td><?= h($row['percent']) ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </article>

    <article class="table-panel">
        <h2>Media-сховище</h2>
        <table>
            <thead><tr><th>Папка / джерело</th><th>Розмір</th></tr></thead>
            <tbody>
                <?php foreach ($storageRows as $row): ?>
                    <tr><td><?= h($row['label']) ?></td><td><?= h($row['value']) ?></td></tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </article>
</section>

<section class="stats-columns">
    <article class="table-panel">
        <h2>Топ камер</h2>
        <?php if (empty($topCameras)): ?>
            <p class="empty-state">Даних камери поки немає.</p>
        <?php else: ?>
            <table>
                <thead><tr><th>Камера</th><th>Фото</th></tr></thead>
                <tbody>
                    <?php foreach ($topCameras as $row): ?>
                        <tr><td><?= h($row['name']) ?></td><td><?= h((string) $row['photo_count']) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </article>

    <article class="table-panel">
        <h2>Топ об’єктивів</h2>
        <?php if (empty($topLenses)): ?>
            <p class="empty-state">Даних об’єктива поки немає.</p>
        <?php else: ?>
            <table>
                <thead><tr><th>Об’єктив</th><th>Фото</th></tr></thead>
                <tbody>
                    <?php foreach ($topLenses as $row): ?>
                        <tr><td><?= h($row['name']) ?></td><td><?= h((string) $row['photo_count']) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </article>
</section>

<section class="stats-columns">
    <article class="table-panel">
        <h2>Топ тегів</h2>
        <?php if (empty($topTags)): ?>
            <p class="empty-state">Тегів поки немає.</p>
        <?php else: ?>
            <table>
                <thead><tr><th>Тег</th><th>Фото</th></tr></thead>
                <tbody>
                    <?php foreach ($topTags as $row): ?>
                        <tr>
                            <td><a class="muted-link" href="<?= h(url('admin/index.php?tag_id=' . (int) $row['id'])) ?>"><?= h($row['name']) ?></a></td>
                            <td><?= h((string) $row['photo_count']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </article>

    <article class="table-panel">
        <h2>Останні місяці</h2>
        <?php if (empty($monthlyRows)): ?>
            <p class="empty-state">Фото поки немає.</p>
        <?php else: ?>
            <table>
                <thead><tr><th>Період</th><th>Фото</th></tr></thead>
                <tbody>
                    <?php foreach ($monthlyRows as $row): ?>
                        <tr><td><?= h($row['period']) ?></td><td><?= h((string) $row['photo_count']) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </article>
</section>

<section class="table-panel">
    <h2>Останні завантаження</h2>
    <?php if (empty($latestRows)): ?>
        <p class="empty-state">Фото поки немає.</p>
    <?php else: ?>
        <table>
            <thead><tr><th>Назва</th><th>Дата додавання</th></tr></thead>
            <tbody>
                <?php foreach ($latestRows as $row): ?>
                    <tr>
                        <td><a class="muted-link" href="<?= h(url('photo.php?id=' . (int) $row['id'])) ?>"><?= h($row['title']) ?></a></td>
                        <td><?= h((string) $row['created_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
<?php require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
