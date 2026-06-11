<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'auth.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'csrf.php';

require_admin();

$pageTitle = 'Завантаження фото - ' . app_name();
$errors = [];
$serverUploadLimit = upload_server_limit();
$appUploadLimit = (int) app_config()['UPLOAD_MAX_SIZE'];
$maxUploadSize = $serverUploadLimit > 0 ? min($appUploadLimit, $serverUploadLimit) : $appUploadLimit;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);

    if ($serverUploadLimit > 0 && $contentLength > $serverUploadLimit) {
        $errors[] = 'Файл завеликий для поточних налаштувань PHP. Максимальний розмір зараз - ' . bytes_for_display($serverUploadLimit) . '.';
    } elseif (!verify_csrf()) {
        $errors[] = 'Помилка CSRF-захисту. Оновіть сторінку і спробуйте ще раз.';
    }

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
        }
    }

    if (empty($errors)) {
        $originalPath = null;
        $thumbnailPath = null;

        try {
            $exif = read_photo_exif($tmpName);
            $filename = random_photo_name();
            $thumbnailFilename = random_photo_name();
            $originalPath = uploads_path('originals', $filename);
            $thumbnailPath = uploads_path('thumbnails', $thumbnailFilename);
            [$width, $height] = save_corrected_original($tmpName, $originalPath, $exif['orientation']);
            create_thumbnail($originalPath, $thumbnailPath);
            $exifJson = json_encode($exif['raw'], JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            $title = trim((string) ($_POST['title'] ?? ''));
            if ($title === '') {
                $title = pathinfo((string) $file['name'], PATHINFO_FILENAME);
            }

            $stmt = db()->prepare(
                'INSERT INTO photos
                (filename, thumbnail_filename, original_name, title, description, mime_type, file_size, width, height, camera_make, camera_model, lens_model, taken_at, exif_json)
                VALUES
                (:filename, :thumbnail_filename, :original_name, :title, :description, :mime_type, :file_size, :width, :height, :camera_make, :camera_model, :lens_model, :taken_at, :exif_json)'
            );

            $stmt->execute([
                'filename' => $filename,
                'thumbnail_filename' => $thumbnailFilename,
                'original_name' => safe_original_name((string) $file['name']),
                'title' => text_limit($title, 255),
                'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
                'mime_type' => 'image/jpeg',
                'file_size' => (int) $file['size'],
                'width' => $width,
                'height' => $height,
                'camera_make' => $exif['camera_make'],
                'camera_model' => $exif['camera_model'],
                'lens_model' => $exif['lens_model'],
                'taken_at' => $exif['taken_at'],
                'exif_json' => $exifJson === false ? null : $exifJson,
            ]);

            set_flash('success', 'Фотографію завантажено.');
            redirect('admin/index.php');
        } catch (Throwable) {
            if (is_string($originalPath) && is_file($originalPath)) {
                unlink($originalPath);
            }

            if (is_string($thumbnailPath) && is_file($thumbnailPath)) {
                unlink($thumbnailPath);
            }

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
            <input type="text" name="title" maxlength="255">
        </label>
        <label>
            Опис
            <textarea name="description" rows="5"></textarea>
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
