<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'auth.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'csrf.php';

require_admin();

$id = get_int('id');

if ($id === null || $id < 1) {
    redirect('admin/index.php');
}

try {
    $photo = fetch_photo_by_id(db(), $id);
} catch (Throwable $exception) {
    app_log_exception($exception, 'Edit fetch failed');
    set_flash('error', 'Не вдалося завантажити фотографію для редагування.');
    redirect('admin/index.php');
}

if (!$photo) {
    set_flash('error', 'Фотографію не знайдено.');
    redirect('admin/index.php');
}

$pageTitle = 'Редагування - ' . app_name();
$errors = [];
$albumOptions = [];
$selectedAlbumId = isset($photo['album_id']) ? (int) $photo['album_id'] : null;
$newAlbumName = '';
$tagsInput = '';
$tagNames = [];

try {
    $albumOptions = get_album_options(false, true);
    $tagsInput = tags_for_input(get_photo_tags($id));
} catch (Throwable $exception) {
    app_log_exception($exception, 'Album options failed');
    $errors[] = 'Не вдалося завантажити список альбомів. Перевірте схему бази даних.';
}

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'photo_service.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newAlbumName = clean_album_name((string) ($_POST['new_album_name'] ?? ''));
    $tagsInput = (string) ($_POST['tags'] ?? '');
    
    $rawAlbumId = $_POST['album_id'] ?? null;
    if ($rawAlbumId !== null && $rawAlbumId !== '') {
        $selectedAlbumId = (int) $rawAlbumId;
    }

    require_csrf();

    $title = trim((string) ($_POST['title'] ?? ''));
    $description = clean_description((string) ($_POST['description'] ?? ''));

    if ($title === '') {
        $errors[] = 'Назва не може бути порожньою.';
    }

    if (empty($errors)) {
        try {
            update_photo_metadata(db(), $id, $_POST);
            set_flash('success', 'Фотографію оновлено.');
            redirect('admin/index.php');
        } catch (InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();
        } catch (Throwable $exception) {
            app_log_exception($exception, 'Photo edit failed');
            $errors[] = 'Не вдалося оновити фотографію.';
        }
    }
}

$stmt = db()->prepare('SELECT id, token, expires_at FROM share_links WHERE photo_id = ? ORDER BY created_at DESC, id DESC');
$stmt->execute([$id]);
$shareLinks = $stmt->fetchAll();

require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'header.php';
?>
<section class="form-panel">
    <h1>Редагування фотографії</h1>

    <?php foreach ($errors as $error): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
    <?php endforeach; ?>

    <div class="edit-preview">
        <img src="<?= h(photo_media_url($photo, 'thumbnail')) ?>" alt="<?= h($photo['title']) ?>" width="600" height="400">
    </div>

    <form method="post" action="<?= h(url('admin/edit.php')) ?>" class="stacked-form">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= h((string) $photo['id']) ?>">
        <input type="hidden" name="updated_at" value="<?= h((string) ($_SERVER['REQUEST_METHOD'] === 'POST' ? ($_POST['updated_at'] ?? '') : ($photo['updated_at'] ?? ''))) ?>">
        <label>
            Назва
            <input type="text" name="title" value="<?= h((string) ($title ?? $photo['title'])) ?>" maxlength="255" required>
        </label>
        <label>
            Опис
            <textarea name="description" rows="6" maxlength="<?= h((string) description_max_length()) ?>"><?= h((string) ($description ?? $photo['description'])) ?></textarea>
        </label>
        <label>
            Альбом
            <select name="album_id">
                <option value="">Без альбому</option>
                <?php foreach ($albumOptions as $album): ?>
                    <option value="<?= h((string) $album['id']) ?>" <?= (int) $album['id'] === $selectedAlbumId ? 'selected' : '' ?>><?= h($album['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            Новий альбом
            <input type="text" name="new_album_name" value="<?= h($newAlbumName) ?>" maxlength="100" placeholder="Заповніть, якщо треба створити новий">
        </label>
        <label>
            Теги
            <input type="text" name="tags" value="<?= h($tagsInput) ?>" maxlength="500" placeholder="портрет, місто, Nikon D7100">
            <span class="form-hint">Розділяйте теги комами. Максимум 20 тегів на фото.</span>
        </label>
        <button class="button" type="submit">Зберегти</button>
    </form>
</section>

<section class="form-panel admin-share-panel">
    <h2>Приватні посилання</h2>
    <?php if ($shareLinks): ?>
        <ul class="admin-share-list">
        <?php foreach ($shareLinks as $link): ?>
            <?php
            $expiresAt = (string) ($link['expires_at'] ?? '');
            $isExpired = $expiresAt !== '' && strtotime($expiresAt) < time();
            ?>
            <li>
                <div class="share-link-details">
                    <a href="<?= h(url('share.php?token=' . $link['token'])) ?>" target="_blank">
                        <?= h(url('share.php?token=' . $link['token'])) ?>
                    </a>
                    <span class="share-link-status <?= $isExpired ? 'is-expired' : '' ?>">
                        <?= $expiresAt === '' ? 'Без строку дії' : ($isExpired ? 'Застаріло: ' : 'Діє до: ') . h($expiresAt) ?>
                    </span>
                </div>
                <div class="admin-actions">
                    <button class="button secondary btn-copy" data-copy-text="<?= h(url('share.php?token=' . $link['token'])) ?>" type="button">Копіювати</button>
                    <form method="post" action="<?= h(url('admin/share.php')) ?>" data-confirm="Видалити посилання?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="revoke">
                        <input type="hidden" name="id" value="<?= h((string)$link['id']) ?>">
                        <input type="hidden" name="return_to" value="admin/edit.php?id=<?= h((string)$id) ?>">
                        <button class="button danger" type="submit">Відкликати</button>
                    </form>
                </div>
            </li>
        <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>Немає активних приватних посилань для цього фото.</p>
    <?php endif; ?>
    
    <form method="post" action="<?= h(url('admin/share.php')) ?>" class="admin-share-create-form">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_photo_share">
        <input type="hidden" name="photo_id" value="<?= h((string)$id) ?>">
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

<?php require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
