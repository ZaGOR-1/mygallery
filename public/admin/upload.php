<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'auth.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'csrf.php';

require_admin();

$pageTitle = 'Завантаження фото - ' . app_name();
$errors = [];
$albumOptions = [];
$selectedAlbumId = null;
$newAlbumName = '';
$serverUploadLimit = upload_server_limit();
$appUploadLimit = (int) app_config()['UPLOAD_MAX_SIZE'];
$maxUploadSize = $serverUploadLimit > 0 ? min($appUploadLimit, $serverUploadLimit) : $appUploadLimit;

try {
    $albumOptions = get_album_options();
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
    $titleInput = trim((string) ($_POST['title'] ?? ''));
    $descriptionInput = clean_description((string) ($_POST['description'] ?? ''));
    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    $requestTooLarge = $serverUploadLimit > 0 && $contentLength > $serverUploadLimit;

    if ($requestTooLarge) {
        $errors[] = 'Файл завеликий для поточних налаштувань PHP. Максимальний розмір зараз - ' . bytes_for_display($serverUploadLimit) . '.';
    } elseif (!verify_csrf()) {
        $errors[] = 'Помилка CSRF-захисту. Оновіть сторінку і спробуйте ще раз.';
    }

    if (!$requestTooLarge) {
        $errors = array_merge($errors, ensure_upload_folders());
        $file = $_FILES['photo'] ?? null;

        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $errors[] = 'Оберіть JPEG-файл для завантаження.';
        } elseif (($file['error'] ?? UPLOAD_ERR_OK) === UPLOAD_ERR_INI_SIZE || ($file['error'] ?? UPLOAD_ERR_OK) === UPLOAD_ERR_FORM_SIZE) {
            $errors[] = 'Файл завеликий для поточних налаштувань PHP. Максимальний розмір зараз - ' . bytes_for_display($maxUploadSize) . '.';
        } elseif (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $errors[] = 'Не вдалося завантажити файл. Спробуйте ще раз.';
        } elseif ((int) $file['size'] > $appUploadLimit) {
            $errors[] = 'Файл завеликий. Максимальний розмір - ' . bytes_for_display($appUploadLimit) . '.';
        } elseif (!is_uploaded_file((string) $file['tmp_name'])) {
            $errors[] = 'Файл не пройшов перевірку завантаження.';
        }
    }

    if (empty($errors)) {
        $tmpName = (string) $file['tmp_name'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = $finfo ? finfo_file($finfo, $tmpName) : false;

        if ($finfo) {
            finfo_close($finfo);
        }

        $imageInfo = @getimagesize($tmpName);

        if ($mime !== 'image/jpeg' || $imageInfo === false || ($imageInfo['mime'] ?? '') !== 'image/jpeg') {
            $errors[] = 'Дозволено завантажувати тільки JPG або JPEG.';
        } else {
            $errors = array_merge($errors, validate_image_limits($imageInfo));
        }
    }

    if (empty($errors)) {
        $originalPath = null;
        $largePath = null;
        $thumbnailPath = null;

        try {
            $filename = random_photo_name();
            $thumbnailFilename = random_photo_name();
            $originalPath = originals_path($filename);
            $largePath = uploads_path('large', $filename);
            $thumbnailPath = uploads_path('thumbnails', $thumbnailFilename);
            move_uploaded_original($tmpName, $originalPath);

            $exif = read_photo_exif($originalPath);
            [$width, $height] = oriented_image_dimensions((int) $imageInfo[0], (int) $imageInfo[1], $exif['orientation']);
            create_large_image($originalPath, $largePath, $exif['orientation']);
            create_thumbnail($originalPath, $thumbnailPath, $exif['orientation']);
            $savedFileSize = is_file($originalPath) ? filesize($originalPath) : false;
            $exifJson = json_encode($exif['raw'], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $title = $titleInput;
            if ($title === '') {
                $title = pathinfo((string) $file['name'], PATHINFO_FILENAME);
            }

            $pdo = db();
            $pdo->beginTransaction();

            try {
                $albumId = resolve_album_id_from_post();
                $stmt = $pdo->prepare(
                    'INSERT INTO photos
                    (album_id, filename, thumbnail_filename, original_name, title, description, mime_type, file_size, width, height, camera_make, camera_model, lens_model, taken_at, exif_json)
                    VALUES
                    (:album_id, :filename, :thumbnail_filename, :original_name, :title, :description, :mime_type, :file_size, :width, :height, :camera_make, :camera_model, :lens_model, :taken_at, :exif_json)'
                );

                $stmt->execute([
                    'album_id' => $albumId,
                    'filename' => $filename,
                    'thumbnail_filename' => $thumbnailFilename,
                    'original_name' => safe_original_name((string) $file['name']),
                    'title' => text_limit($title, 255),
                    'description' => $descriptionInput,
                    'mime_type' => 'image/jpeg',
                    'file_size' => $savedFileSize === false ? (int) $file['size'] : (int) $savedFileSize,
                    'width' => $width,
                    'height' => $height,
                    'camera_make' => $exif['camera_make'],
                    'camera_model' => $exif['camera_model'],
                    'lens_model' => $exif['lens_model'],
                    'taken_at' => $exif['taken_at'],
                    'exif_json' => $exifJson === false ? null : $exifJson,
                ]);

                $pdo->commit();
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                throw $exception;
            }

            set_flash('success', 'Фотографію завантажено.');
            redirect('admin/index.php');
        } catch (Throwable $exception) {
            app_log_exception($exception, 'Upload failed');

            unlink_file_with_log($originalPath, 'Upload cleanup');
            unlink_file_with_log($largePath, 'Upload cleanup');
            unlink_file_with_log($thumbnailPath, 'Upload cleanup');

            $errors[] = 'Не вдалося зберегти фотографію. Перевірте права на uploads і налаштування PHP GD.';
        }
    }
}

require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'header.php';
?>
<section class="form-panel">
    <h1>Завантажити фотографію</h1>

    <?php foreach ($errors as $error): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
    <?php endforeach; ?>

    <form method="post" enctype="multipart/form-data" class="stacked-form">
        <?= csrf_field() ?>
        <label>
            Назва
            <input type="text" name="title" value="<?= h($titleInput ?? '') ?>" maxlength="255">
        </label>
        <label>
            Опис
            <textarea name="description" rows="5" maxlength="<?= h((string) description_max_length()) ?>"><?= h((string) ($descriptionInput ?? '')) ?></textarea>
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
            JPEG-файл
            <input type="file" name="photo" accept="image/jpeg" required>
        </label>
        <?php if ($maxUploadSize > 0): ?>
            <p class="form-hint">Максимальний розмір файла: <?= h(bytes_for_display($maxUploadSize)) ?></p>
        <?php endif; ?>
        <button class="button" type="submit">Завантажити</button>
    </form>
</section>
<?php require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
