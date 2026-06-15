<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'auth.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'csrf.php';

require_admin();

$pageTitle = 'Альбоми - ' . app_name();
$errors = [];
$editingAlbumId = get_album_id_from_request('edit');
$editingAlbum = null;

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'photo_service.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Помилка CSRF-захисту. Оновіть сторінку і спробуйте ще раз.';
    }

    $action = (string) ($_POST['action'] ?? '');
    $albumId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $albumName = (string) ($_POST['name'] ?? '');
    $coverPhotoId = filter_input(INPUT_POST, 'cover_photo_id', FILTER_VALIDATE_INT);
    if ($coverPhotoId === false || $coverPhotoId < 1) {
        $coverPhotoId = null;
    }

    if (empty($errors)) {
        try {
            if ($action === 'create') {
                if ($albumName === '') {
                    throw new InvalidArgumentException('Назва альбому не може бути порожньою.');
                }

                find_or_create_album($albumName);
                set_flash('success', 'Альбом створено.');
                redirect('admin/albums.php');
            } elseif ($action === 'update') {
                if ($albumId === false || $albumId === null || $albumId < 1) {
                    throw new InvalidArgumentException('Некоректний альбом.');
                }
                
                update_album_with_validation(db(), $albumId, $albumName, $coverPhotoId);
                
                set_flash('success', 'Альбом оновлено.');
                redirect('admin/albums.php');
            } elseif ($action === 'delete') {
                if ($albumId === false || $albumId === null || $albumId < 1) {
                    throw new InvalidArgumentException('Некоректний альбом.');
                }

                delete_album_with_validation(db(), $albumId);
                
                set_flash('success', 'Альбом видалено. Фотографії залишилися без альбому.');
                redirect('admin/albums.php');
            } else {
                $errors[] = 'Невідома дія.';
            }
        } catch (InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();
        } catch (Throwable $exception) {
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
    <form method="post" action="<?= h(url('admin/albums.php')) ?>" class="stacked-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $editingAlbum ? 'update' : 'create' ?>">
        <?php if ($editingAlbum): ?>
            <input type="hidden" name="id" value="<?= h((string) $editingAlbum['id']) ?>">
        <?php endif; ?>
        <label>
            Назва
            <input type="text" name="name" value="<?= h((string) ($editingAlbum['name'] ?? '')) ?>" maxlength="100" required>
        </label>
        <?php if ($editingAlbum): ?>
            <label>
                Обкладинка альбому
                <select name="cover_photo_id">
                    <option value="">Без обкладинки</option>
                    <?php
                    $photosStmt = db()->prepare('SELECT id, title, original_name FROM photos WHERE album_id = ? ORDER BY created_at DESC');
                    $photosStmt->execute([$editingAlbum['id']]);
                    $albumPhotos = $photosStmt->fetchAll();
                    ?>
                    <?php foreach ($albumPhotos as $photo): ?>
                        <option value="<?= h((string) $photo['id']) ?>" <?= (int) $photo['id'] === (int) ($editingAlbum['cover_photo_id'] ?? 0) ? 'selected' : '' ?>>
                            <?= h($photo['title'] !== '' ? $photo['title'] : $photo['original_name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <span class="form-hint">Ви можете обрати одну з фотографій цього альбому як обкладинку.</span>
            </label>
        <?php endif; ?>
        <div class="filter-actions">
            <button class="button" type="submit"><?= $editingAlbum ? 'Зберегти' : 'Створити' ?></button>
            <?php if ($editingAlbum): ?>
                <a class="button secondary" href="<?= h(url('admin/albums.php')) ?>">Скасувати</a>
            <?php endif; ?>
        </div>
    </form>
</section>

<?php if ($editingAlbum): ?>
<?php
$stmt = db()->prepare('SELECT id, token FROM share_links WHERE album_id = ?');
$stmt->execute([$editingAlbum['id']]);
$albumShares = $stmt->fetchAll();
?>
<section class="form-panel" style="margin-top: 2rem;">
    <h2>Приватні посилання на альбом</h2>
    <?php if ($albumShares): ?>
        <ul style="list-style: none; padding: 0;">
        <?php foreach ($albumShares as $link): ?>
            <li style="margin-bottom: 1rem; padding: 1rem; background: var(--bg-hover); border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
                <a href="<?= h(url('share.php?token=' . $link['token'])) ?>" target="_blank" style="word-break: break-all;">
                    <?= h(url('share.php?token=' . $link['token'])) ?>
                </a>
                <form method="post" action="<?= h(url('admin/share.php')) ?>" style="display:inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="revoke">
                    <input type="hidden" name="id" value="<?= h((string)$link['id']) ?>">
                    <input type="hidden" name="return_to" value="admin/albums.php?edit=<?= h((string)$editingAlbum['id']) ?>">
                    <button class="button button-danger" type="submit" onclick="return confirm('Видалити посилання?');">Відкликати</button>
                </form>
            </li>
        <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>Немає активних приватних посилань для цього альбому.</p>
    <?php endif; ?>
    
    <form method="post" action="<?= h(url('admin/share.php')) ?>" style="margin-top: 1rem;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_album_share">
        <input type="hidden" name="album_id" value="<?= h((string)$editingAlbum['id']) ?>">
        <button class="button button-secondary" type="submit">Створити нове посилання</button>
    </form>
</section>
<?php endif; ?>


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
