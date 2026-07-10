<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'auth.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'csrf.php';

require_admin();

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'photo_service.php';

$pageTitle = 'Завантаження фото - ' . app_name();
$errors = [];
$albumOptions = [];
$selectedAlbumId = null;
$newAlbumName = '';
$tagsInput = '';
$tagNames = [];
$postMaxSize = size_to_bytes((string) ini_get('post_max_size'));
$uploadMaxFilesize = size_to_bytes((string) ini_get('upload_max_filesize'));
$appUploadLimit = (int) app_config()['UPLOAD_MAX_SIZE'];
$maxSingleFileSize = $uploadMaxFilesize > 0 ? min($appUploadLimit, $uploadMaxFilesize) : $appUploadLimit;
$maxTotalPostSize = $postMaxSize > 0 ? $postMaxSize : 0;

try {
    $albumOptions = get_album_options(false, true);
} catch (Throwable $exception) {
    app_log_exception($exception, 'Album options failed');
    $errors[] = 'Не вдалося завантажити список альбомів. Перевірте схему бази даних.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newAlbumName = clean_album_name((string) ($_POST['new_album_name'] ?? ''));
    $titleInput = trim((string) ($_POST['title'] ?? ''));
    $descriptionInput = clean_description((string) ($_POST['description'] ?? ''));
    $tagsInput = (string) ($_POST['tags'] ?? '');
    
    $rawAlbumId = $_POST['album_id'] ?? null;
    if ($rawAlbumId !== null && $rawAlbumId !== '') {
        $selectedAlbumId = (int) $rawAlbumId;
    }

    $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    $requestTooLarge = $maxTotalPostSize > 0 && $contentLength > $maxTotalPostSize;

    if ($requestTooLarge) {
        $errors[] = 'Загальний об’єм файлів завеликий для сервера. Максимальний розмір пакета - ' . bytes_for_display($maxTotalPostSize) . '.';
    } else {
        require_csrf();
    }

    if (!$requestTooLarge) {
        $errors = array_merge($errors, ensure_upload_folders());
        $filesPost = $_FILES['photo'] ?? null;
        $files = [];

        if (is_array($filesPost) && isset($filesPost['name'])) {
            if (is_array($filesPost['name'])) {
                $count = count($filesPost['name']);
                for ($i = 0; $i < $count; $i++) {
                    if (($filesPost['error'][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                        $files[] = [
                            'name' => (string) $filesPost['name'][$i],
                            'type' => (string) $filesPost['type'][$i],
                            'tmp_name' => (string) $filesPost['tmp_name'][$i],
                            'error' => (int) $filesPost['error'][$i],
                            'size' => (int) $filesPost['size'][$i],
                        ];
                    }
                }
            } else {
                if (($filesPost['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
                    $files[] = $filesPost;
                }
            }
        }

        if (empty($files)) {
            $errors[] = 'Оберіть JPEG-файли для завантаження.';
        }
    }

    if (empty($errors)) {
        $successCount = 0;
        $fileErrors = [];

        foreach ($files as $file) {
            $fileNameDisplay = safe_original_name((string) $file['name']);
            if ($file['error'] === UPLOAD_ERR_INI_SIZE || $file['error'] === UPLOAD_ERR_FORM_SIZE) {
                $fileErrors[] = "$fileNameDisplay: Файл завеликий.";
                continue;
            } elseif ($file['error'] !== UPLOAD_ERR_OK) {
                $fileErrors[] = "$fileNameDisplay: Не вдалося завантажити файл (код {$file['error']}).";
                continue;
            } elseif ((int) $file['size'] > $appUploadLimit) {
                $fileErrors[] = "$fileNameDisplay: Файл завеликий.";
                continue;
            } elseif (!is_uploaded_file((string) $file['tmp_name'])) {
                $fileErrors[] = "$fileNameDisplay: Файл не пройшов перевірку.";
                continue;
            }

            try {
                create_photo_from_upload(db(), $file, $_POST);
                $successCount++;
            } catch (InvalidArgumentException $exception) {
                $fileErrors[] = "$fileNameDisplay: " . $exception->getMessage();
            } catch (RuntimeException $exception) {
                $fileErrors[] = "$fileNameDisplay: " . $exception->getMessage();
            } catch (Throwable $exception) {
                app_log_exception($exception, "Upload failed for $fileNameDisplay");
                $fileErrors[] = "$fileNameDisplay: Не вдалося зберегти фотографію.";
            }
        }

        if ($successCount > 0) {
            set_flash('success', "Успішно завантажено $successCount фотографій.");
            foreach ($fileErrors as $err) {
                set_flash('error', $err);
            }
            redirect('admin/index.php');
        } else {
            foreach ($fileErrors as $err) {
                $errors[] = $err;
            }
        }
    }
}

require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'header.php';
?>
<section class="form-panel">
    <h1>Завантажити фотографії</h1>

    <?php foreach ($errors as $error): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
    <?php endforeach; ?>

    <form method="post" action="<?= h(url('admin/upload.php')) ?>" enctype="multipart/form-data" class="stacked-form" data-max-single-file="<?= h((string) $maxSingleFileSize) ?>" data-max-total-size="<?= h((string) $maxTotalPostSize) ?>" id="upload-form">
        <?= csrf_field() ?>
        <label>
            Назва
            <input type="text" name="title" value="<?= h($titleInput ?? '') ?>" maxlength="255">
            <span class="form-hint">Залиште порожнім, щоб використати назви файлів. Якщо заповнити, всі файли отримають однакову назву.</span>
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
            Теги
            <input type="text" name="tags" value="<?= h($tagsInput) ?>" maxlength="500" placeholder="портрет, місто, Nikon D7100">
            <span class="form-hint">Розділяйте теги комами. Максимум 20 тегів на фото.</span>
        </label>
        <label class="file-upload-label">
            JPEG-файли для завантаження
            <div class="drop-zone" id="upload-drop-zone">
                <span class="drop-zone-text">Перетягніть фотографії сюди або натисніть для вибору</span>
                <input type="file" name="photo[]" id="photo-input" accept="image/jpeg" multiple required>
            </div>
        </label>
        <?php if ($maxSingleFileSize > 0): ?>
            <p class="form-hint">
                Максимальний розмір <b>одного файла</b>: <?= h(bytes_for_display($maxSingleFileSize)) ?><br>
                <?php if ($maxTotalPostSize > 0): ?>
                Максимальний розмір <b>всього пакета (разом)</b>: <?= h(bytes_for_display($maxTotalPostSize)) ?>
                <?php endif; ?>
            </p>
        <?php endif; ?>
        <button class="button" type="submit">Завантажити</button>
    </form>
</section>
<?php require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
