<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'auth.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'csrf.php';

require_admin();

$pageTitle = 'Альбоми - ' . app_name();
$errors = [];
$editingAlbumId = get_album_id_from_request('edit');
$editingAlbum = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Помилка CSRF-захисту. Оновіть сторінку і спробуйте ще раз.';
    }

    $action = (string) ($_POST['action'] ?? '');
    $albumId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $albumName = clean_album_name((string) ($_POST['name'] ?? ''));

    if (empty($errors)) {
        try {
            if ($action === 'create') {
                if ($albumName === '') {
                    throw new InvalidArgumentException('Назва альбому не може бути порожньою.');
                }

                find_or_create_album($albumName);
                set_flash('success', 'Альбом створено.');
                redirect('admin/albums.php');
            }

            if ($action === 'update') {
                if ($albumId === false || $albumId === null || $albumId < 1) {
                    throw new InvalidArgumentException('Некоректний альбом.');
                }

                if ($albumName === '') {
                    throw new InvalidArgumentException('Назва альбому не може бути порожньою.');
                }

                $stmt = db()->prepare('UPDATE albums SET name = :name WHERE id = :id');
                $stmt->execute([
                    'name' => $albumName,
                    'id' => $albumId,
                ]);

                set_flash('success', 'Альбом оновлено.');
                redirect('admin/albums.php');
            }

            if ($action === 'delete') {
                if ($albumId === false || $albumId === null || $albumId < 1) {
                    throw new InvalidArgumentException('Некоректний альбом.');
                }

                db()->beginTransaction();

                $stmt = db()->prepare('UPDATE photos SET album_id = NULL WHERE album_id = :id');
                $stmt->execute(['id' => $albumId]);

                $stmt = db()->prepare('DELETE FROM albums WHERE id = :id');
                $stmt->execute(['id' => $albumId]);

                db()->commit();

                set_flash('success', 'Альбом видалено. Фотографії залишилися без альбому.');
                redirect('admin/albums.php');
            }

            $errors[] = 'Невідома дія.';
        } catch (InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();
        } catch (Throwable $exception) {
            if (db()->inTransaction()) {
                db()->rollBack();
            }

            app_log_exception($exception, 'Album admin action failed');
            $errors[] = 'Не вдалося зберегти альбом. Перевірте, чи назва не дублюється.';
        }
    }
}

try {
    $albums = get_album_options(true);

    if ($editingAlbumId !== null) {
        foreach ($albums as $album) {
            if ((int) $album['id'] === $editingAlbumId) {
                $editingAlbum = $album;
                break;
            }
        }
    }
} catch (Throwable $exception) {
    app_log_exception($exception, 'Albums page failed');
    $albums = [];
    $errors[] = 'Не вдалося завантажити альбоми. Перевірте схему бази даних.';
}

require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'header.php';
?>
<section class="admin-toolbar">
    <div>
        <h1>Альбоми</h1>
        <p>Створюйте прості альбоми й прив’язуйте до них фотографії.</p>
    </div>
    <a class="button secondary" href="<?= h(url('admin/index.php')) ?>">До адмінпанелі</a>
</section>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endforeach; ?>

<section class="form-panel">
    <h2><?= $editingAlbum ? 'Редагувати альбом' : 'Новий альбом' ?></h2>
    <form method="post" class="stacked-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $editingAlbum ? 'update' : 'create' ?>">
        <?php if ($editingAlbum): ?>
            <input type="hidden" name="id" value="<?= h((string) $editingAlbum['id']) ?>">
        <?php endif; ?>
        <label>
            Назва
            <input type="text" name="name" value="<?= h((string) ($editingAlbum['name'] ?? '')) ?>" maxlength="100" required>
        </label>
        <div class="filter-actions">
            <button class="button" type="submit"><?= $editingAlbum ? 'Зберегти' : 'Створити' ?></button>
            <?php if ($editingAlbum): ?>
                <a class="button secondary" href="<?= h(url('admin/albums.php')) ?>">Скасувати</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<?php if (empty($albums)): ?>
    <p class="empty-state">Альбомів поки немає.</p>
<?php else: ?>
    <div class="admin-list">
        <?php foreach ($albums as $album): ?>
            <article class="admin-item album-item">
                <div>
                    <h2><?= h($album['name']) ?></h2>
                    <p><?= h((string) (int) $album['photo_count']) ?> фото</p>
                </div>
                <div class="admin-actions">
                    <a class="button secondary" href="<?= h(url('gallery.php?album_id=' . (int) $album['id'])) ?>">Перегляд</a>
                    <a class="button secondary" href="<?= h(url('admin/albums.php?edit=' . (int) $album['id'])) ?>">Редагувати</a>
                    <form method="post" action="<?= h(url('admin/albums.php')) ?>" data-confirm="Видалити альбом? Фотографії залишаться без альбому.">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= h((string) $album['id']) ?>">
                        <button class="button danger" type="submit">Видалити</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
