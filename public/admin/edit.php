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
    $albumOptions = get_album_options();
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

    if (!verify_csrf()) {
        $errors[] = 'Помилка CSRF-захисту. Оновіть сторінку і спробуйте ще раз.';
    }

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

$stmt = db()->prepare('SELECT id, token FROM share_links WHERE photo_id = ?');
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
        <img src="<?= h(uploads_url('thumbnails', $photo['thumbnail_filename'])) ?>" alt="<?= h($photo['title']) ?>" width="600" height="400">
    </div>

    <form method="post" action="<?= h(url('admin/edit.php')) ?>" class="stacked-form">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= h((string) $photo['id']) ?>">
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

<section class="form-panel" style="margin-top: 2rem;">
    <h2>Приватні посилання</h2>
    <?php if ($shareLinks): ?>
        <ul style="list-style: none; padding: 0;">
        <?php foreach ($shareLinks as $link): ?>
            <li style="margin-bottom: 1rem; padding: 1rem; background: var(--bg-hover); border-radius: var(--radius-sm); display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
                <a href="<?= h(url('share.php?token=' . $link['token'])) ?>" target="_blank" style="word-break: break-all;">
                    <?= h(url('share.php?token=' . $link['token'])) ?>
                </a>
                <form method="post" action="<?= h(url('admin/share.php')) ?>" style="display:inline;">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="revoke">
                    <input type="hidden" name="id" value="<?= h((string)$link['id']) ?>">
                    <input type="hidden" name="return_to" value="admin/edit.php?id=<?= h((string)$id) ?>">
                    <button class="button button-danger" type="submit" onclick="return confirm('Видалити посилання?');">Відкликати</button>
                </form>
            </li>
        <?php endforeach; ?>
        </ul>
    <?php else: ?>
        <p>Немає активних приватних посилань для цього фото.</p>
    <?php endif; ?>
    
    <form method="post" action="<?= h(url('admin/share.php')) ?>" style="margin-top: 1rem;">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create_photo_share">
        <input type="hidden" name="photo_id" value="<?= h((string)$id) ?>">
        <button class="button button-secondary" type="submit">Створити нове посилання</button>
    </form>
</section>

<?php require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
