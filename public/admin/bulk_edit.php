<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'auth.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'csrf.php';

require_admin();

$pageTitle = 'Масове редагування - ' . app_name();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/index.php');
}

require_csrf();

$photoIdsRaw = $_POST['photo_ids'] ?? [];
$action = $_POST['bulk_action'] ?? 'edit';

if (!is_array($photoIdsRaw) || empty($photoIdsRaw)) {
    set_flash('error', 'Ви не вибрали жодної фотографії для редагування.');
    redirect('admin/index.php');
}

$photoIds = array_map('intval', $photoIdsRaw);
$photoIds = array_filter($photoIds, fn($id) => $id > 0);

if (empty($photoIds)) {
    set_flash('error', 'Ви не вибрали жодної фотографії для редагування.');
    redirect('admin/index.php');
}

if ($action === 'delete') {
    require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'photo_service.php';
    try {
        $pdo = db();
        $successCount = 0;
        foreach ($photoIds as $pid) {
            $stmt = $pdo->prepare('SELECT * FROM photos WHERE id = ?');
            $stmt->execute([$pid]);
            $photo = $stmt->fetch();
            if ($photo) {
                delete_photo_with_trash($pdo, $pid, $photo);
                $successCount++;
            }
        }
        set_flash('success', 'Успішно переміщено в кошик: ' . $successCount . ' фото.');
        redirect('admin/index.php');
    } catch (Throwable $e) {
        app_log_exception($e, 'Bulk delete failed');
        set_flash('error', 'Не вдалося видалити деякі фотографії. Дивіться логи.');
        redirect('admin/index.php');
    }
} elseif ($action === 'save') {
    $newAlbumIdRaw = $_POST['album_id'] ?? '';
    $newTags = (string) ($_POST['tags'] ?? '');

    try {
        $pdo = db();
        $pdo->beginTransaction();

        // 1. Update album
        $placeholders = implode(',', array_fill(0, count($photoIds), '?'));
        if ($newAlbumIdRaw === 'null') {
            // Remove from album
            $stmtCover = $pdo->prepare("UPDATE albums SET cover_photo_id = NULL WHERE cover_photo_id IN ($placeholders)");
            $stmtCover->execute($photoIds);
            $stmt = $pdo->prepare("UPDATE photos SET album_id = NULL WHERE id IN ($placeholders)");
            $stmt->execute($photoIds);
        } elseif ($newAlbumIdRaw !== '') {
            // Move to specific album
            $albumId = (int) $newAlbumIdRaw;
            // Check if album exists
            $albumCheck = $pdo->prepare('SELECT id FROM albums WHERE id = ?');
            $albumCheck->execute([$albumId]);
            if (!$albumCheck->fetch()) {
                throw new InvalidArgumentException('Вибраний альбом не існує.');
            }
            $stmtCover = $pdo->prepare("UPDATE albums SET cover_photo_id = NULL WHERE id != ? AND cover_photo_id IN ($placeholders)");
            $stmtCover->execute(array_merge([$albumId], $photoIds));
            $params = array_merge([$albumId], $photoIds);
            $stmt = $pdo->prepare("UPDATE photos SET album_id = ? WHERE id IN ($placeholders)");
            $stmt->execute($params);
        }

        // 2. Add tags
        if ($newTags !== '') {
            $parsedTags = parse_tags_input($newTags);
            if (!empty($parsedTags)) {
                foreach ($photoIds as $pid) {
                    $currentTags = get_photo_tags($pid);
                    $currentTagNames = array_map(
                        static fn (array $tag): string => (string) ($tag['name'] ?? ''),
                        $currentTags
                    );
                    $combined = array_values(array_unique(array_filter(array_merge($currentTagNames, $parsedTags))));
                    sync_photo_tags($pid, $combined);
                }
            }
        }

        $pdo->commit();
        set_flash('success', 'Масове редагування успішно завершено для ' . count($photoIds) . ' фото.');
        redirect('admin/index.php');
    } catch (InvalidArgumentException $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $errors[] = $e->getMessage();
    } catch (Throwable $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        app_log_exception($e, 'Bulk edit failed');
        $errors[] = 'Не вдалося зберегти зміни.';
    }
}

// Show form (action === 'edit' or error during 'save')
try {
    $albumOptions = get_album_options(true, true);
} catch (Throwable $e) {
    app_log_exception($e, 'Failed to fetch albums for bulk edit');
    $albumOptions = [];
}

require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'header.php';
?>
<section class="admin-toolbar">
    <div>
        <h1>Масове редагування</h1>
        <p>Вибрано фотографій: <?= count($photoIds) ?></p>
    </div>
    <div>
        <a class="button secondary" href="<?= h(url('admin/index.php')) ?>">Скасувати</a>
    </div>
</section>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endforeach; ?>

<section class="form-panel">
    <form method="post" action="<?= h(url('admin/bulk_edit.php')) ?>" class="stacked-form">
        <?= csrf_field() ?>
        <input type="hidden" name="bulk_action" value="save">
        <?php foreach ($photoIds as $pid): ?>
            <input type="hidden" name="photo_ids[]" value="<?= h((string)$pid) ?>">
        <?php endforeach; ?>

        <fieldset>
            <legend>Зміна альбому</legend>
            <p class="form-note">
                Виберіть альбом, у який потрібно перемістити всі вибрані фотографії. 
                Якщо ви нічого не виберете, альбоми залишаться без змін.
            </p>
            <label>
                Новий альбом
                <select name="album_id">
                    <option value="">-- Без змін --</option>
                    <option value="null">-- Прибрати з альбому --</option>
                    <?php foreach ($albumOptions as $album): ?>
                        <option value="<?= h((string) $album['id']) ?>">
                            <?= h($album['name']) ?> (<?= h((string) (int) $album['photo_count']) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </fieldset>

        <fieldset class="bulk-fieldset">
            <legend>Додавання тегів</legend>
            <p class="form-note">
                Введіть теги через кому. Вони будуть <strong>додані</strong> до вже існуючих тегів кожної фотографії.
            </p>
            <label>
                Теги
                <input type="text" name="tags" placeholder="наприклад: відпустка, гори, 2026">
            </label>
        </fieldset>

        <div class="form-actions">
            <button class="button" type="submit">Застосувати зміни</button>
        </div>
    </form>
</section>

<?php require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
