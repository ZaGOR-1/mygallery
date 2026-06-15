<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'auth.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'csrf.php';

require_admin();

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'photo_service.php';

$pageTitle = 'Кошик - ' . app_name();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $action = (string) ($_POST['action'] ?? '');
    $operationId = trim((string) ($_POST['operation_id'] ?? ''));

    if (empty($errors)) {
        try {
            if ($action === 'restore') {
                if ($operationId === '' || !preg_match('/^[a-f0-9]{32}$/', $operationId)) {
                    throw new InvalidArgumentException('Некоректний ідентифікатор операції.');
                }
                restore_photo_from_trash(db(), $operationId);
                set_flash('success', 'Фотографію успішно відновлено.');
                redirect('admin/trash.php');
            } elseif ($action === 'purge') {
                if ($operationId === '' || !preg_match('/^[a-f0-9]{32}$/', $operationId)) {
                    throw new InvalidArgumentException('Некоректний ідентифікатор операції.');
                }
                purge_photo_from_trash($operationId);
                set_flash('success', 'Фотографію остаточно видалено з кошика.');
                redirect('admin/trash.php');
            } elseif ($action === 'purge_all') {
                purge_all_trash();
                set_flash('success', 'Кошик успішно очищено.');
                redirect('admin/trash.php');
            } else {
                $errors[] = 'Невідома дія.';
            }
        } catch (InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();
        } catch (Throwable $exception) {
            app_log_exception($exception, 'Trash action failed');
            $errors[] = 'Не вдалося виконати дію: ' . $exception->getMessage();
        }
    }
}

$trashItems = [];
try {
    $manifests = glob(trash_path('*.json')) ?: [];
    foreach ($manifests as $manifestPath) {
        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        if (is_array($manifest)) {
            $operationId = pathinfo($manifestPath, PATHINFO_FILENAME);
            $photoData = $manifest['photo_data'] ?? [];
            
            $deletedAt = $manifest['created_at'] ?? '';
            if ($deletedAt !== '') {
                $date = DateTime::createFromFormat(DateTime::ATOM, $deletedAt);
                if ($date === false) {
                    // Fallback parse
                    $date = date_create($deletedAt);
                }
                if ($date instanceof DateTime) {
                    $deletedAt = $date->format('d.m.Y H:i');
                }
            }

            $trashItems[] = [
                'operation_id' => $operationId,
                'photo_id' => $manifest['photo_id'] ?? null,
                'title' => $photoData['title'] ?? 'Без назви',
                'original_name' => $photoData['original_name'] ?? 'Невідомо',
                'file_size' => isset($photoData['file_size']) ? (int) $photoData['file_size'] : 0,
                'deleted_at' => $deletedAt,
            ];
        }
    }

    // Sort by deleted_at descending
    usort($trashItems, static function (array $a, array $b): int {
        return strcmp($b['deleted_at'], $a['deleted_at']);
    });
} catch (Throwable $exception) {
    app_log_exception($exception, 'Failed to load trash list');
    $errors[] = 'Не вдалося завантажити список видалених файлів.';
}

$perPage = 20;
$page = get_int('page') ?: 1;
$totalItems = count($trashItems);
$totalPages = max(1, (int) ceil($totalItems / $perPage));
$page = min(max($page, 1), $totalPages);
$offset = ($page - 1) * $perPage;
$pagedTrashItems = array_slice($trashItems, $offset, $perPage);

require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'header.php';
?>
<section class="admin-toolbar">
    <div>
        <h1>Кошик</h1>
        <p>Перегляд та відновлення раніше видалених фотографій. Файли в кошику зберігаються у безпечному місці.</p>
    </div>
    <div class="toolbar-actions">
        <a class="button secondary" href="<?= h(url('admin/index.php')) ?>">До адмінпанелі</a>
        <?php if (!empty($trashItems)): ?>
            <form method="post" action="<?= h(url('admin/trash.php')) ?>" data-confirm="Очистити кошик? Усі фотографії буде видалено безповоротно!">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="purge_all">
                <button class="button danger" type="submit">Очистити кошик</button>
            </form>
        <?php endif; ?>
    </div>
</section>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endforeach; ?>

<?php if (empty($trashItems)): ?>
    <section class="admin-empty-state">
        <h2>Кошик порожній</h2>
        <p>Видалені фотографії з'являтимуться тут. Ви зможете відновити їх у будь-який момент або очистити кошик для звільнення місця.</p>
    </section>
<?php else: ?>
    <div class="admin-list admin-trash-list">
        <?php foreach ($pagedTrashItems as $item): ?>
            <article class="admin-item">
                <div class="admin-item-media album-cover-preview">
                    <div class="admin-cover-empty">Кошик</div>
                </div>
                <div class="admin-item-body">
                    <h2><?= h($item['title']) ?></h2>
                    <div class="admin-meta">
                        <span>Файл: <?= h($item['original_name']) ?></span>
                        <span>Розмір: <?= h(bytes_for_display($item['file_size'])) ?></span>
                        <span>Видалено: <?= h($item['deleted_at']) ?></span>
                    </div>
                </div>
                <div class="admin-actions">
                    <form method="post" action="<?= h(url('admin/trash.php')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="restore">
                        <input type="hidden" name="operation_id" value="<?= h($item['operation_id']) ?>">
                        <button class="button" type="submit">Відновити</button>
                    </form>
                    <form method="post" action="<?= h(url('admin/trash.php')) ?>" data-confirm="Видалити цю фотографію назавжди? Цю дію неможливо скасувати!">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="purge">
                        <input type="hidden" name="operation_id" value="<?= h($item['operation_id']) ?>">
                        <button class="button danger" type="submit">Видалити назавжди</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </div>

    <?php if ($totalPages > 1): ?>
        <nav class="pagination" aria-label="Пагінація">
            <?php if ($page > 1): ?>
                <a href="<?= h(url('admin/trash.php?page=' . ($page - 1))) ?>">Назад</a>
            <?php endif; ?>

            <?php foreach (pagination_window($page, $totalPages) as $i): ?>
                <?php if ($i === null): ?>
                    <span class="pagination-gap">…</span>
                <?php elseif ($i === $page): ?>
                    <strong aria-current="page"><?= h((string) $i) ?></strong>
                <?php else: ?>
                    <a href="<?= h(url('admin/trash.php?page=' . $i)) ?>"><?= h((string) $i) ?></a>
                <?php endif; ?>
            <?php endforeach; ?>

            <?php if ($page < $totalPages): ?>
                <a href="<?= h(url('admin/trash.php?page=' . ($page + 1))) ?>">Вперед</a>
            <?php endif; ?>
        </nav>
    <?php endif; ?>
<?php endif; ?>

<?php require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
