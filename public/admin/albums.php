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
    require_csrf();

    $action = request_string($_POST, 'action', 32);
    $albumId = request_int($_POST, 'id', null, 1);
    $albumName = request_string($_POST, 'name', 100);
    $coverPhotoId = request_int($_POST, 'cover_photo_id', null, 1);
    $isPrivate = isset($_POST['is_private']) && $_POST['is_private'] === '1' ? 1 : 0;

    if (empty($errors)) {
        try {
            if ($action === 'create') {
                if ($albumName === '') {
                    throw new InvalidArgumentException('Назва альбому не може бути порожньою.');
                }

                create_album_strict($albumName, $isPrivate);
                set_flash('success', 'Альбом створено.');
                redirect('admin/albums.php');
            } elseif ($action === 'update') {
                if ($albumId === null) {
                    throw new InvalidArgumentException('Некоректний альбом.');
                }
                
                update_album_with_validation(db(), $albumId, $albumName, $coverPhotoId, $isPrivate);
                
                set_flash('success', 'Альбом оновлено.');
                redirect('admin/albums.php');
            } elseif ($action === 'delete') {
                if ($albumId === null) {
                    throw new InvalidArgumentException('Некоректний альбом.');
                }

                delete_album_with_validation(db(), $albumId);
                
                set_flash('success', 'Альбом видалено. Фотографії залишилися без альбому.');
                redirect('admin/albums.php');
            } elseif ($action === 'move_up') {
                if ($albumId === null) {
                    throw new InvalidArgumentException('Некоректний альбом.');
                }

                reorder_album(db(), $albumId, 'up');
                set_flash('success', 'Порядок альбому змінено.');
                redirect('admin/albums.php');
            } elseif ($action === 'move_down') {
                if ($albumId === null) {
                    throw new InvalidArgumentException('Некоректний альбом.');
                }

                reorder_album(db(), $albumId, 'down');
                set_flash('success', 'Порядок альбому змінено.');
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
    $albums = get_public_albums_with_covers(true);

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
    <div class="toolbar-actions">
        <a class="button secondary" href="<?= h(url('admin/index.php')) ?>">До адмінпанелі</a>
    </div>
</section>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endforeach; ?>

<section class="form-panel admin-form-panel">
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
        <label class="checkbox-label">
            <input type="checkbox" name="is_private" value="1" <?= (!empty($editingAlbum['is_private'])) ? 'checked' : '' ?>>
            <span>Приватний альбом (приховати з публічної галереї)</span>
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
$stmt = db()->prepare('SELECT id, token_hint, expires_at FROM share_links WHERE album_id = ? ORDER BY created_at DESC, id DESC');
$stmt->execute([$editingAlbum['id']]);
$albumShares = $stmt->fetchAll();
$newAlbumShareUrl = pull_one_time_secret('album_share_' . (int) $editingAlbum['id']);
?>
<section class="form-panel admin-share-panel">
    <h2>Приватні посилання на альбом</h2>
    <?php if ($newAlbumShareUrl !== null): ?>
        <div class="alert alert-success">
            <strong>Нове посилання — скопіюйте зараз:</strong><br>
            <a href="<?= h($newAlbumShareUrl) ?>" target="_blank" rel="noopener noreferrer"><?= h($newAlbumShareUrl) ?></a>
            <button class="button secondary btn-copy" data-copy-text="<?= h($newAlbumShareUrl) ?>" type="button">Копіювати</button>
            <div class="form-hint">Raw token не зберігається у базі й після оновлення сторінки більше не показуватиметься.</div>
        </div>
    <?php endif; ?>
    <?php if ($albumShares): ?>
        <ul class="admin-share-list">
        <?php foreach ($albumShares as $link): ?>
            <?php
            $expiresAt = (string) ($link['expires_at'] ?? '');
            $isExpired = $expiresAt !== '' && strtotime($expiresAt) < time();
            ?>
            <li>
                <div class="share-link-details">
                    <strong>Посилання …<?= h((string) ($link['token_hint'] ?? '')) ?></strong>
                    <span class="share-link-status <?= $isExpired ? 'is-expired' : '' ?>">
                        <?= $expiresAt === '' ? 'Без строку дії' : ($isExpired ? 'Застаріло: ' : 'Діє до: ') . h($expiresAt) ?>
                    </span>
                    <span class="form-hint">Повний token не зберігається. Для нового URL створіть нове посилання.</span>
                </div>
                <div class="admin-actions">
                    <form method="post" action="<?= h(url('admin/share.php')) ?>" data-confirm="Видалити посилання?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="revoke">
                        <input type="hidden" name="id" value="<?= h((string)$link['id']) ?>">
                        <input type="hidden" name="return_to" value="admin/albums.php?edit=<?= h((string)$editingAlbum['id']) ?>">
                        <button class="button danger" type="submit">Відкликати</button>
                    </form>
                </div>
            </li>
        <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>Немає активних приватних посилань для цього альбому.</p>
    <?php endif; ?>
    
    <form method="post" action="<?= h(url('admin/share.php')) ?>" class="admin-share-create-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_album_share">
        <input type="hidden" name="album_id" value="<?= h((string)$editingAlbum['id']) ?>">
        <label class="share-expiry-field">
            Строк дії
            <select name="expires_in">
                <option value="1">1 день</option>
                <option value="7">7 днів</option>
                <option value="30" selected>30 днів</option>
                <option value="90">90 днів</option>
                <option value="0">Без строку дії</option>
            </select>
        </label>
        <button class="button secondary" type="submit">Створити нове посилання</button>
    </form>
</section>
<?php endif; ?>


<?php if (empty($albums)): ?>
    <section class="admin-empty-state">
        <h2>Альбомів поки немає</h2>
        <p>Створіть перший альбом вище, а потім прив'яжіть до нього фотографії під час upload або редагування.</p>
    </section>
<?php else: ?>
    <div class="admin-list admin-collection-list">
        <?php foreach ($albums as $index => $album): ?>
            <article class="admin-item album-item">
                <div class="admin-item-media album-cover-preview">
                    <?php if (!empty($album['thumbnail_filename'])): ?>
                        <picture>
                            <?php
                            $avifSrcset = photo_cover_srcset_next_gen($album, 'avif');
                            if ($avifSrcset !== ''): ?>
                                <source srcset="<?= h($avifSrcset) ?>" type="image/avif" sizes="120px">
                            <?php endif; ?>
                            <?php
                            $webpSrcset = photo_cover_srcset_next_gen($album, 'webp');
                            if ($webpSrcset !== ''): ?>
                                <source srcset="<?= h($webpSrcset) ?>" type="image/webp" sizes="120px">
                            <?php endif; ?>
                            <img
                                src="<?= h(photo_display_url($album)) ?>"
                                srcset="<?= h(photo_cover_srcset($album)) ?>"
                                sizes="120px"
                                alt="<?= h($album['cover_title'] ?: $album['name']) ?>"
                                width="600"
                                height="400"
                                loading="lazy"
                            >
                        </picture>
                    <?php else: ?>
                        <div class="admin-cover-empty">Без обкладинки</div>
                    <?php endif; ?>
                </div>
                <div class="admin-item-body">
                    <h2><?= h($album['name']) ?></h2>
                    <div class="admin-meta">
                        <span><?= h((string) (int) $album['photo_count']) ?> фото</span>
                        <span><?= !empty($album['cover_photo_id']) ? 'Обкладинку задано' : 'Автообкладинка' ?></span>
                        <span>Порядок: <?= h((string) (int) $album['sort_order']) ?></span>
                        <span><?= !empty($album['is_private']) ? '🔒 Приватний' : '🌐 Публічний' ?></span>
                    </div>
                </div>
                <div class="admin-actions">
                    <?php if ($index > 0): ?>
                        <form method="post" action="<?= h(url('admin/albums.php')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="move_up">
                            <input type="hidden" name="id" value="<?= h((string) $album['id']) ?>">
                            <button class="button secondary" type="submit" title="Перемістити вгору">&#9650;</button>
                        </form>
                    <?php endif; ?>
                    <?php if ($index < count($albums) - 1): ?>
                        <form method="post" action="<?= h(url('admin/albums.php')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="move_down">
                            <input type="hidden" name="id" value="<?= h((string) $album['id']) ?>">
                            <button class="button secondary" type="submit" title="Перемістити вниз">&#9660;</button>
                        </form>
                    <?php endif; ?>
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
