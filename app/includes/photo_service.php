<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

function create_photo_from_upload(PDO $pdo, array $file, array $input): int
{
    $tmpName = (string) $file['tmp_name'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $tmpName) : false;

    if ($finfo) {
        finfo_close($finfo);
    }

    $imageInfo = @getimagesize($tmpName);

    if ($mime !== 'image/jpeg' || $imageInfo === false || ($imageInfo['mime'] ?? '') !== 'image/jpeg') {
        throw new RuntimeException('Дозволено завантажувати тільки JPG або JPEG.');
    }

    $limitErrors = validate_image_limits($imageInfo);
    if (!empty($limitErrors)) {
        throw new RuntimeException(implode(' ', $limitErrors));
    }

    $originalSha256 = hash_file('sha256', $tmpName);
    if ($originalSha256 !== false) {
        $stmt = $pdo->prepare('SELECT id FROM photos WHERE original_sha256 = ? LIMIT 1');
        $stmt->execute([$originalSha256]);
        if ($stmt->fetch()) {
            throw new RuntimeException('Ця фотографія вже була завантажена раніше (знайдено точний дублікат).');
        }
    } else {
        $originalSha256 = null;
    }

    $tagNames = parse_tags_input((string) ($input['tags'] ?? ''));

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

        $title = trim((string) ($input['title'] ?? ''));
        if ($title === '') {
            $title = pathinfo((string) $file['name'], PATHINFO_FILENAME);
        }
        $description = clean_description((string) ($input['description'] ?? ''));

        $pdo->beginTransaction();

        $albumId = null;
        $newAlbumName = clean_album_name((string) ($input['new_album_name'] ?? ''));
        if ($newAlbumName !== '') {
            $albumId = find_or_create_album($newAlbumName);
        } else {
            $rawAlbumId = $input['album_id'] ?? null;
            if ($rawAlbumId !== null && $rawAlbumId !== '') {
                $rawAlbumId = filter_var($rawAlbumId, FILTER_VALIDATE_INT);
                if ($rawAlbumId === false || $rawAlbumId < 1) {
                    throw new InvalidArgumentException('Некоректний альбом.');
                }
                if (!album_exists((int) $rawAlbumId)) {
                    throw new InvalidArgumentException('Обраний альбом не знайдено.');
                }
                $albumId = (int) $rawAlbumId;
            }
        }

        $stmt = $pdo->prepare(
            'INSERT INTO photos
            (album_id, filename, thumbnail_filename, original_name, title, description, mime_type, file_size, width, height, camera_make, camera_model, lens_model, taken_at, exif_json, original_sha256)
            VALUES
            (:album_id, :filename, :thumbnail_filename, :original_name, :title, :description, :mime_type, :file_size, :width, :height, :camera_make, :camera_model, :lens_model, :taken_at, :exif_json, :original_sha256)'
        );

        $stmt->execute([
            'album_id' => $albumId,
            'filename' => $filename,
            'thumbnail_filename' => $thumbnailFilename,
            'original_name' => safe_original_name((string) $file['name']),
            'title' => text_limit($title, 255),
            'description' => $description,
            'mime_type' => 'image/jpeg',
            'file_size' => $savedFileSize === false ? (int) $file['size'] : (int) $savedFileSize,
            'width' => $width,
            'height' => $height,
            'camera_make' => $exif['camera_make'],
            'camera_model' => $exif['camera_model'],
            'lens_model' => $exif['lens_model'],
            'taken_at' => $exif['taken_at'],
            'exif_json' => $exifJson === false ? null : $exifJson,
            'original_sha256' => $originalSha256,
        ]);

        $photoId = (int) $pdo->lastInsertId();
        sync_photo_tags($photoId, $tagNames);

        $pdo->commit();

        return $photoId;
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($originalPath !== null) unlink_file_with_log($originalPath, 'Upload cleanup');
        if ($largePath !== null) unlink_file_with_log($largePath, 'Upload cleanup');
        if ($thumbnailPath !== null) unlink_file_with_log($thumbnailPath, 'Upload cleanup');

        throw $exception;
    }
}

function update_photo_metadata(PDO $pdo, int $photoId, array $input): void
{
    $title = trim((string) ($input['title'] ?? ''));
    $description = clean_description((string) ($input['description'] ?? ''));
    $tagNames = parse_tags_input((string) ($input['tags'] ?? ''));

    if ($title === '') {
        throw new InvalidArgumentException('Назва не може бути порожньою.');
    }

    $albumId = null;
    $newAlbumName = clean_album_name((string) ($input['new_album_name'] ?? ''));
    if ($newAlbumName !== '') {
        $albumId = find_or_create_album($newAlbumName);
    } else {
        $rawAlbumId = $input['album_id'] ?? null;
        if ($rawAlbumId !== null && $rawAlbumId !== '') {
            $rawAlbumId = filter_var($rawAlbumId, FILTER_VALIDATE_INT);
            if ($rawAlbumId === false || $rawAlbumId < 1) {
                throw new InvalidArgumentException('Некоректний альбом.');
            }
            if (!album_exists((int) $rawAlbumId)) {
                throw new InvalidArgumentException('Обраний альбом не знайдено.');
            }
            $albumId = (int) $rawAlbumId;
        }
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('UPDATE photos SET album_id = :album_id, title = :title, description = :description WHERE id = :id');
        $stmt->execute([
            'album_id' => $albumId,
            'title' => text_limit($title, 255),
            'description' => $description,
            'id' => $photoId,
        ]);
        sync_photo_tags($photoId, $tagNames);
        prune_unused_tags();
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function delete_photo_with_trash(PDO $pdo, int $photoId, array $photo): void
{
    $fileErrors = validate_photo_files_deletable($photo);
    if (!empty($fileErrors)) {
        throw new RuntimeException(implode(' ', $fileErrors));
    }

    $folderErrors = ensure_upload_folders();
    if (!empty($folderErrors)) {
        throw new RuntimeException(implode(' ', $folderErrors));
    }

    $movedFiles = [];

    try {
        $movedFiles = move_photo_files_to_trash($photo);
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('DELETE FROM photos WHERE id = :id');
        $stmt->execute(['id' => $photoId]);
        prune_unused_tags();

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $restoreErrors = restore_moved_photo_files($movedFiles);
        $message = 'Не вдалося видалити фотографію. Файли залишено на місці.';
        if (!empty($restoreErrors)) {
            $message .= ' ' . implode(' ', $restoreErrors);
        }
        throw new RuntimeException($message, 0, $exception);
    }

    $fileErrors = remove_trashed_photo_files($movedFiles);
    if (!empty($fileErrors)) {
        app_log('Delete cleanup warning: ' . implode(' ', $fileErrors));
        // We throw an exception but specify it's a warning so the caller can handle it
        throw new RuntimeException('Запис із бази видалено, але частину тимчасових файлів не вдалося прибрати. Деталі записано в лог.');
    }
}

function update_album_with_validation(PDO $pdo, int $albumId, string $newName, ?int $coverPhotoId = null): void
{
    $newName = clean_album_name($newName);
    if ($newName === '') {
        throw new InvalidArgumentException('Назва альбому не може бути порожньою.');
    }

    if (!album_exists($albumId)) {
        throw new InvalidArgumentException('Альбом не знайдено.');
    }

    if ($coverPhotoId !== null) {
        $stmt = $pdo->prepare('SELECT id FROM photos WHERE id = ? AND album_id = ?');
        $stmt->execute([$coverPhotoId, $albumId]);
        if (!$stmt->fetch()) {
            throw new InvalidArgumentException('Обрана обкладинка має належати цьому альбому.');
        }
    }

    $stmt = $pdo->prepare('UPDATE albums SET name = :name, cover_photo_id = :cover_photo_id WHERE id = :id');
    $stmt->execute(['name' => $newName, 'cover_photo_id' => $coverPhotoId, 'id' => $albumId]);
}

function delete_album_with_validation(PDO $pdo, int $albumId): void
{
    if (!album_exists($albumId)) {
        throw new InvalidArgumentException('Альбом не знайдено.');
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('UPDATE photos SET album_id = NULL WHERE album_id = :id');
        $stmt->execute(['id' => $albumId]);

        $stmt = $pdo->prepare('DELETE FROM albums WHERE id = :id');
        $stmt->execute(['id' => $albumId]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw clone $exception;
    }
}
