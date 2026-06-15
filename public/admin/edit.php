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
    $stmt = db()->prepare('SELECT * FROM photos WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $photo = $stmt->fetch();
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $selectedAlbumId = get_album_id_from_post('album_id');
    } catch (InvalidArgumentException $exception) {
        $selectedAlbumId = null;
        $errors[] = $exception->getMessage();
    }

    $newAlbumName = clean_album_name((string) ($_POST['new_album_name'] ?? ''));
    $tagsInput = (string) ($_POST['tags'] ?? '');

    try {
        $tagNames = parse_tags_input($tagsInput);
    } catch (InvalidArgumentException $exception) {
        $tagNames = [];
        $errors[] = $exception->getMessage();
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
        $pdo = db();

        try {
            $pdo->beginTransaction();
            $albumId = resolve_album_id_from_post();
            $stmt = $pdo->prepare('UPDATE photos SET album_id = :album_id, title = :title, description = :description WHERE id = :id');
            $stmt->execute([
                'album_id' => $albumId,
                'title' => text_limit($title, 255),
                'description' => $description,
                'id' => $id,
            ]);
            sync_photo_tags($id, $tagNames);
            prune_unused_tags();
            $pdo->commit();

            set_flash('success', 'Фотографію оновлено.');
            redirect('admin/index.php');
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            app_log_exception($exception, 'Photo edit failed');
            $errors[] = 'Не вдалося оновити фотографію.';
        }
    }
}

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

    <form method="post" class="stacked-form">
        <?= csrf_field() ?>
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
<?php require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
