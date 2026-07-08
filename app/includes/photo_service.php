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
        create_webp_copy($largePath);
        create_avif_copy($largePath);

        create_thumbnail($originalPath, $thumbnailPath, $exif['orientation']);
        create_webp_copy($thumbnailPath);
        create_avif_copy($thumbnailPath);
        $dominantColor = get_image_dominant_color($originalPath);

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
            (album_id, filename, thumbnail_filename, original_name, title, description, mime_type, file_size, width, height, camera_make, camera_model, lens_model, taken_at, exif_json, original_sha256, dominant_color, updated_at)
            VALUES
            (:album_id, :filename, :thumbnail_filename, :original_name, :title, :description, :mime_type, :file_size, :width, :height, :camera_make, :camera_model, :lens_model, :taken_at, :exif_json, :original_sha256, :dominant_color, CURRENT_TIMESTAMP)'
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
            'dominant_color' => $dominantColor,
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
        if ($largePath !== null) {
            unlink_file_with_log($largePath, 'Upload cleanup');
            unlink_file_with_log(derivative_path($largePath, 'webp'), 'Upload cleanup');
            unlink_file_with_log(derivative_path($largePath, 'avif'), 'Upload cleanup');
        }
        if ($thumbnailPath !== null) {
            unlink_file_with_log($thumbnailPath, 'Upload cleanup');
            unlink_file_with_log(derivative_path($thumbnailPath, 'webp'), 'Upload cleanup');
            unlink_file_with_log(derivative_path($thumbnailPath, 'avif'), 'Upload cleanup');
        }

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
        
        $stmt = $pdo->prepare('SELECT album_id, COALESCE(updated_at, created_at) AS lock_version FROM photos WHERE id = ? FOR UPDATE');
        $stmt->execute([$photoId]);
        $photoData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$photoData) {
            throw new InvalidArgumentException('Фотографію не знайдено.');
        }

        if (!isset($input['updated_at']) || $input['updated_at'] === '' || (string) $input['updated_at'] !== (string) $photoData['lock_version']) {
            throw new InvalidArgumentException('Фотографію було змінено іншим адміністратором. Будь ласка, оновіть сторінку і спробуйте знову.');
        }
        
        $oldAlbumId = $photoData['album_id'];
        
        $stmt = $pdo->prepare('UPDATE photos SET album_id = :album_id, title = :title, description = :description WHERE id = :id');
        $stmt->execute([
            'album_id' => $albumId,
            'title' => text_limit($title, 255),
            'description' => $description,
            'id' => $photoId,
        ]);
        
        if ($oldAlbumId !== false && $oldAlbumId !== null && (int)$oldAlbumId !== $albumId) {
            $stmtCover = $pdo->prepare('UPDATE albums SET cover_photo_id = NULL WHERE id = ? AND cover_photo_id = ?');
            $stmtCover->execute([(int)$oldAlbumId, $photoId]);
        }
        
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

}

function update_album_with_validation(PDO $pdo, int $albumId, string $newName, ?int $coverPhotoId = null, int $isPrivate = 0): void
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

    $stmt = $pdo->prepare('UPDATE albums SET name = :name, cover_photo_id = :cover_photo_id, is_private = :is_private WHERE id = :id');
    $stmt->execute(['name' => $newName, 'cover_photo_id' => $coverPhotoId, 'is_private' => $isPrivate, 'id' => $albumId]);
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
        throw $exception;
    }
}

function restore_photo_from_trash(PDO $pdo, string $operationId): void
{
    $manifestPath = trash_path($operationId . '.json');
    if (!is_file($manifestPath)) {
        throw new InvalidArgumentException('Журнал видалення не знайдено.');
    }

    $manifest = json_decode((string) file_get_contents($manifestPath), true);
    if (!is_array($manifest)) {
        throw new RuntimeException('Некоректний журнал видалення.');
    }

    $photoData = $manifest['photo_data'] ?? null;
    if (!$photoData) {
        throw new RuntimeException('У журналі немає метаданих фотографії для відновлення.');
    }

    $files = $manifest['files'] ?? [];
    
    // Check if all files in trash exist before moving them back
    foreach ($files as $file) {
        $resolved = resolve_trash_manifest_entry((array) $file);
        if ($resolved === null || !is_file($resolved['trash'])) {
            throw new RuntimeException('Частина файлів у кошику відсутня. Відновлення неможливе.');
        }
    }

    // Move files back
    $movedBack = [];
    try {
        foreach ($files as $file) {
            $resolved = resolve_trash_manifest_entry((array) $file);
            if (!rename($resolved['trash'], $resolved['from'])) {
                throw new RuntimeException('Не вдалося перемістити файл із кошика: ' . basename($resolved['from']));
            }
            $movedBack[] = $resolved;
        }

        $pdo->beginTransaction();

        // Check album exists
        $albumId = isset($photoData['album_id']) ? (int) $photoData['album_id'] : null;
        if ($albumId !== null && !album_exists($albumId)) {
            $albumId = null;
        }

        $stmt = $pdo->prepare(
            'INSERT INTO photos
            (id, album_id, filename, thumbnail_filename, original_name, title, description, mime_type, file_size, width, height, camera_make, camera_model, lens_model, taken_at, exif_json, original_sha256, dominant_color, created_at)
            VALUES
            (:id, :album_id, :filename, :thumbnail_filename, :original_name, :title, :description, :mime_type, :file_size, :width, :height, :camera_make, :camera_model, :lens_model, :taken_at, :exif_json, :original_sha256, :dominant_color, :created_at)'
        );

        $stmt->execute([
            'id' => (int) $photoData['id'],
            'album_id' => $albumId,
            'filename' => $photoData['filename'],
            'thumbnail_filename' => $photoData['thumbnail_filename'],
            'original_name' => $photoData['original_name'],
            'title' => $photoData['title'],
            'description' => $photoData['description'],
            'mime_type' => $photoData['mime_type'],
            'file_size' => (int) $photoData['file_size'],
            'width' => isset($photoData['width']) ? (int) $photoData['width'] : null,
            'height' => isset($photoData['height']) ? (int) $photoData['height'] : null,
            'camera_make' => $photoData['camera_make'] ?? null,
            'camera_model' => $photoData['camera_model'] ?? null,
            'lens_model' => $photoData['lens_model'] ?? null,
            'taken_at' => $photoData['taken_at'] ?? null,
            'exif_json' => $photoData['exif_json'] ?? null,
            'original_sha256' => $photoData['original_sha256'] ?? null,
            'dominant_color' => $photoData['dominant_color'] ?? null,
            'created_at' => $photoData['created_at'],
        ]);

        // Restore tags
        $tags = $manifest['tags'] ?? [];
        sync_photo_tags((int) $photoData['id'], $tags);

        $pdo->commit();

        // Delete manifest file
        @unlink($manifestPath);
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        // Rollback moved files back to trash
        foreach ($movedBack as $file) {
            @rename($file['from'], $file['trash']);
        }
        throw $exception;
    }
}

function purge_photo_from_trash(string $operationId): void
{
    $manifestPath = trash_path($operationId . '.json');
    if (!is_file($manifestPath)) {
        throw new InvalidArgumentException('Журнал видалення не знайдено.');
    }

    $manifest = json_decode((string) file_get_contents($manifestPath), true);
    if (!is_array($manifest)) {
        throw new RuntimeException('Некоректний журнал видалення.');
    }

    $files = $manifest['files'] ?? [];
    $errors = [];

    foreach ($files as $file) {
        $resolved = resolve_trash_manifest_entry((array) $file);
        if ($resolved !== null && is_file($resolved['trash'])) {
            if (!@unlink($resolved['trash'])) {
                $errors[] = 'Не вдалося видалити файл ' . basename($resolved['trash']) . '.';
            }
        }
    }

    if (!@unlink($manifestPath)) {
        $errors[] = 'Не вдалося видалити файл журналу.';
    }

    if (!empty($errors)) {
        throw new RuntimeException(implode(' ', $errors));
    }
}

function purge_all_trash(): void
{
    $manifests = glob(trash_path('*.json')) ?: [];
    $errors = [];

    foreach ($manifests as $manifestPath) {
        $operationId = pathinfo($manifestPath, PATHINFO_FILENAME);
        try {
            purge_photo_from_trash($operationId);
        } catch (Throwable $e) {
            $errors[] = $e->getMessage();
        }
    }

    if (!empty($errors)) {
        throw new RuntimeException('Деякі файли не вдалося видалити: ' . implode(' ', $errors));
    }
}

function reorder_album(PDO $pdo, int $albumId, string $direction): void
{
    if ($direction !== 'up' && $direction !== 'down') {
        throw new InvalidArgumentException('Некоректний напрямок сортування.');
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->query('SELECT id, sort_order FROM albums ORDER BY sort_order ASC, name ASC');
        $albumsList = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $targetIndex = -1;
        $currentOrder = 10;

        // Normalize sort order sequentially
        foreach ($albumsList as $index => $album) {
            $albumsList[$index]['sort_order'] = $currentOrder;
            if ((int) $album['id'] === $albumId) {
                $targetIndex = $index;
            }
            $currentOrder += 10;
        }

        if ($targetIndex === -1) {
            throw new InvalidArgumentException('Альбом не знайдено.');
        }

        // Apply normalized orders to all first to ensure no conflicts
        $stmtUpdate = $pdo->prepare('UPDATE albums SET sort_order = ? WHERE id = ?');
        foreach ($albumsList as $album) {
            $stmtUpdate->execute([$album['sort_order'], $album['id']]);
        }

        // Perform swap
        if ($direction === 'up' && $targetIndex > 0) {
            $prevIndex = $targetIndex - 1;
            $temp = $albumsList[$targetIndex]['sort_order'];
            $albumsList[$targetIndex]['sort_order'] = $albumsList[$prevIndex]['sort_order'];
            $albumsList[$prevIndex]['sort_order'] = $temp;

            $stmtUpdate->execute([$albumsList[$targetIndex]['sort_order'], $albumsList[$targetIndex]['id']]);
            $stmtUpdate->execute([$albumsList[$prevIndex]['sort_order'], $albumsList[$prevIndex]['id']]);
        } elseif ($direction === 'down' && $targetIndex < count($albumsList) - 1) {
            $nextIndex = $targetIndex + 1;
            $temp = $albumsList[$targetIndex]['sort_order'];
            $albumsList[$targetIndex]['sort_order'] = $albumsList[$nextIndex]['sort_order'];
            $albumsList[$nextIndex]['sort_order'] = $temp;

            $stmtUpdate->execute([$albumsList[$targetIndex]['sort_order'], $albumsList[$targetIndex]['id']]);
            $stmtUpdate->execute([$albumsList[$nextIndex]['sort_order'], $albumsList[$nextIndex]['id']]);
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}
