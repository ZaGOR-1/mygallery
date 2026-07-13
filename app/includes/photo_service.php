<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';

function create_photo_from_upload(PDO $pdo, array $file, array $input): int
{
    $tmpNameValue = $file['tmp_name'] ?? null;
    $originalNameValue = $file['name'] ?? null;
    $sizeValue = $file['size'] ?? null;
    if (!is_string($tmpNameValue) || $tmpNameValue === '' || !is_string($originalNameValue)
        || (!is_int($sizeValue) && !is_string($sizeValue))) {
        throw new InvalidArgumentException('Некоректна структура upload-запиту.');
    }
    $validatedSize = filter_var($sizeValue, FILTER_VALIDATE_INT);
    if (!is_int($validatedSize) || $validatedSize < 0) {
        throw new InvalidArgumentException('Некоректний розмір upload-файлу.');
    }

    $tmpName = $tmpNameValue;
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

    $tagNames = parse_tags_input(request_string($input, 'tags', 500));

    $originalPath = null;
    $largePath = null;
    $thumbnailPath = null;

    $maintenanceLock = acquire_media_maintenance_lock(LOCK_SH);
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

        $safeOriginalName = safe_original_name($originalNameValue);
        $title = request_string($input, 'title', 255);
        if ($title === '') {
            $title = pathinfo($safeOriginalName, PATHINFO_FILENAME);
        }
        $description = clean_description(request_raw_string($input, 'description', '', description_max_length() + 1));

        $pdo->beginTransaction();

        $albumId = null;
        $newAlbumName = clean_album_name(request_string($input, 'new_album_name', 100));
        if ($newAlbumName !== '') {
            $albumId = find_or_create_album($newAlbumName, 0, $pdo);
        } else {
            $rawAlbumId = request_raw_string($input, 'album_id', '', 20);
            if ($rawAlbumId !== '') {
                $rawAlbumId = filter_var($rawAlbumId, FILTER_VALIDATE_INT);
                if ($rawAlbumId === false || $rawAlbumId < 1) {
                    throw new InvalidArgumentException('Некоректний альбом.');
                }
                if (!album_exists((int) $rawAlbumId, $pdo)) {
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
            'original_name' => $safeOriginalName,
            'title' => text_limit($title, 255),
            'description' => $description,
            'mime_type' => 'image/jpeg',
            'file_size' => $savedFileSize === false ? $validatedSize : (int) $savedFileSize,
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
    } finally {
        release_media_maintenance_lock($maintenanceLock);
    }
}

function update_photo_metadata(PDO $pdo, int $photoId, array $input): void
{
    $title = request_string($input, 'title', 255);
    $description = clean_description(request_raw_string($input, 'description', '', description_max_length() + 1));
    $tagNames = parse_tags_input(request_string($input, 'tags', 500));

    if ($title === '') {
        throw new InvalidArgumentException('Назва не може бути порожньою.');
    }

    $expectedLockVersion = request_int($input, 'lock_version', null, 1);
    if ($expectedLockVersion === null) {
        throw new InvalidArgumentException('Некоректна версія редагування. Оновіть сторінку і спробуйте знову.');
    }

    $requestedAlbumId = null;
    $newAlbumName = clean_album_name(request_string($input, 'new_album_name', 100));
    if ($newAlbumName === '') {
        $rawAlbumId = request_raw_string($input, 'album_id', '', 20);
        if ($rawAlbumId !== '') {
            $rawAlbumId = filter_var($rawAlbumId, FILTER_VALIDATE_INT);
            if ($rawAlbumId === false || $rawAlbumId < 1) {
                throw new InvalidArgumentException('Некоректний альбом.');
            }
            $requestedAlbumId = (int) $rawAlbumId;
        }
    }

    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare('SELECT album_id, lock_version FROM photos WHERE id = ? FOR UPDATE');
        $stmt->execute([$photoId]);
        $photoData = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$photoData) {
            throw new InvalidArgumentException('Фотографію не знайдено.');
        }

        if ((int) ($photoData['lock_version'] ?? 0) !== $expectedLockVersion) {
            throw new InvalidArgumentException('Фотографію було змінено іншим адміністратором. Будь ласка, оновіть сторінку і спробуйте знову.');
        }

        $oldAlbumId = $photoData['album_id'];
        $albumId = null;
        if ($newAlbumName !== '') {
            $albumId = find_or_create_album($newAlbumName, 0, $pdo);
        } elseif ($requestedAlbumId !== null) {
            $lockAlbum = $pdo->prepare('SELECT id FROM albums WHERE id = ? FOR UPDATE');
            $lockAlbum->execute([$requestedAlbumId]);
            if ($lockAlbum->fetchColumn() === false) {
                throw new InvalidArgumentException('Обраний альбом не знайдено.');
            }
            $albumId = $requestedAlbumId;
        }
        
        $stmt = $pdo->prepare(
            'UPDATE photos
             SET album_id = :album_id, title = :title, description = :description, lock_version = lock_version + 1
             WHERE id = :id AND lock_version = :lock_version'
        );
        $stmt->execute([
            'album_id' => $albumId,
            'title' => text_limit($title, 255),
            'description' => $description,
            'id' => $photoId,
            'lock_version' => $expectedLockVersion,
        ]);
        if ($stmt->rowCount() !== 1) {
            throw new InvalidArgumentException('Фотографію було змінено іншим адміністратором. Будь ласка, оновіть сторінку і спробуйте знову.');
        }
        
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
    $maintenanceLock = acquire_media_maintenance_lock(LOCK_SH);
    try {
        $folderErrors = ensure_upload_folders();
        if (!empty($folderErrors)) {
            throw new RuntimeException(implode(' ', $folderErrors));
        }

        $movedFiles = [];
        try {
            $pdo->beginTransaction();

            $lockPhoto = $pdo->prepare('SELECT * FROM photos WHERE id = :id FOR UPDATE');
            $lockPhoto->execute(['id' => $photoId]);
            $lockedPhoto = $lockPhoto->fetch(PDO::FETCH_ASSOC);
            if (!is_array($lockedPhoto)) {
                throw new InvalidArgumentException('Фотографію вже видалено або не знайдено.');
            }
            $fileErrors = validate_photo_files_deletable($lockedPhoto);
            if ($fileErrors !== []) {
                throw new RuntimeException(implode(' ', $fileErrors));
            }
            $movedFiles = move_photo_files_to_trash($lockedPhoto);

            $stmt = $pdo->prepare('DELETE FROM photos WHERE id = :id');
            $stmt->execute(['id' => $photoId]);
            if ($stmt->rowCount() !== 1) {
                throw new RuntimeException('Фотографію не вдалося атомарно видалити.');
            }
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
    } finally {
        release_media_maintenance_lock($maintenanceLock);
    }
}

/**
 * @param list<int> $photoIds
 * @return array{deleted: list<int>, failed: array<int, string>}
 */
function bulk_delete_photos_with_trash(PDO $pdo, array $photoIds): array
{
    $photoIds = array_values(array_unique(array_filter(
        array_map('intval', $photoIds),
        static fn (int $id): bool => $id > 0
    )));
    $result = ['deleted' => [], 'failed' => []];
    if ($photoIds === []) {
        return $result;
    }

    $placeholders = implode(',', array_fill(0, count($photoIds), '?'));
    $statement = $pdo->prepare("SELECT * FROM photos WHERE id IN ({$placeholders})");
    $statement->execute($photoIds);
    $photosById = [];
    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $photo) {
        $photosById[(int) $photo['id']] = $photo;
    }

    $folderErrors = ensure_upload_folders();
    foreach ($photoIds as $photoId) {
        if (!isset($photosById[$photoId])) {
            $result['failed'][$photoId] = 'Фотографію не знайдено.';
            continue;
        }
        $fileErrors = array_merge($folderErrors, validate_photo_files_deletable($photosById[$photoId]));
        if ($fileErrors !== []) {
            $result['failed'][$photoId] = implode(' ', $fileErrors);
        }
    }

    // No item is changed if the preflight found a missing row/file/folder.
    if ($result['failed'] !== []) {
        return $result;
    }

    foreach ($photoIds as $photoId) {
        try {
            delete_photo_with_trash($pdo, $photoId, $photosById[$photoId]);
            $result['deleted'][] = $photoId;
        } catch (Throwable $exception) {
            app_log_exception($exception, 'Bulk delete photo #' . $photoId . ' failed');
            $result['failed'][$photoId] = $exception->getMessage();
        }
    }

    return $result;
}

function update_album_with_validation(PDO $pdo, int $albumId, string $newName, ?int $coverPhotoId = null, int $isPrivate = 0): void
{
    $newName = clean_album_name($newName);
    if ($newName === '') {
        throw new InvalidArgumentException('Назва альбому не може бути порожньою.');
    }

    try {
        $pdo->beginTransaction();

        // Photo rows are always locked before album rows, matching photo move/delete flows.
        if ($coverPhotoId !== null) {
            $lockCover = $pdo->prepare('SELECT album_id FROM photos WHERE id = ? FOR UPDATE');
            $lockCover->execute([$coverPhotoId]);
            $coverAlbumId = $lockCover->fetchColumn();
            if ($coverAlbumId === false || (int) $coverAlbumId !== $albumId) {
                throw new InvalidArgumentException('Обрана обкладинка має належати цьому альбому.');
            }
        }

        $lockAlbum = $pdo->prepare('SELECT id FROM albums WHERE id = ? FOR UPDATE');
        $lockAlbum->execute([$albumId]);
        if ($lockAlbum->fetchColumn() === false) {
            throw new InvalidArgumentException('Альбом не знайдено.');
        }

        $stmt = $pdo->prepare('UPDATE albums SET name = :name, cover_photo_id = :cover_photo_id, is_private = :is_private WHERE id = :id');
        $stmt->execute(['name' => $newName, 'cover_photo_id' => $coverPhotoId, 'is_private' => $isPrivate, 'id' => $albumId]);
        if ($stmt->rowCount() > 1) {
            throw new RuntimeException('Некоректна кількість оновлених альбомів.');
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function delete_album_with_validation(PDO $pdo, int $albumId): void
{
    if (!album_exists($albumId)) {
        throw new InvalidArgumentException('Альбом не знайдено.');
    }

    try {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare('UPDATE photos SET album_id = NULL, lock_version = lock_version + 1 WHERE album_id = :id');
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

function trash_restore_file_state(string $livePath, string $trashPath): string
{
    if (is_link($livePath) || is_link($trashPath)
        || (file_exists($livePath) && !is_file($livePath))
        || (file_exists($trashPath) && !is_file($trashPath))) {
        return 'conflict';
    }

    $liveExists = is_file($livePath);
    $trashExists = is_file($trashPath);
    if (!$liveExists && !$trashExists) {
        return 'missing';
    }
    if ($liveExists && !$trashExists) {
        return 'live_only';
    }
    if (!$liveExists && $trashExists) {
        return 'trash_only';
    }

    $liveSize = filesize($livePath);
    $trashSize = filesize($trashPath);
    $liveHash = is_int($liveSize) && is_int($trashSize) && $liveSize === $trashSize ? hash_file('sha256', $livePath) : false;
    $trashHash = is_string($liveHash) ? hash_file('sha256', $trashPath) : false;

    return is_string($liveHash) && is_string($trashHash) && hash_equals($liveHash, $trashHash)
        ? 'both_equal'
        : 'conflict';
}

/** @return list<array{from:string,trash:string,filename:string,trash_filename:string,entry:array<string,mixed>}> */
function resolve_trash_restore_files(array $files): array
{
    $resolvedFiles = [];
    $seenLive = [];
    $seenTrash = [];
    foreach ($files as $file) {
        $entry = (array) $file;
        $resolved = resolve_trash_manifest_entry($entry);
        if ($resolved === null || !is_string($resolved['from'] ?? null) || !is_string($resolved['trash'] ?? null)) {
            throw new RuntimeException('Некоректний шлях файла в журналі відновлення.');
        }
        $liveKey = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $resolved['from']);
        $trashKey = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $resolved['trash']);
        if (PHP_OS_FAMILY === 'Windows') {
            $liveKey = strtolower($liveKey);
            $trashKey = strtolower($trashKey);
        }
        if (isset($seenLive[$liveKey]) || isset($seenTrash[$trashKey])) {
            throw new RuntimeException('Журнал відновлення містить дубльований шлях файла.');
        }
        $seenLive[$liveKey] = true;
        $seenTrash[$trashKey] = true;
        $resolved['entry'] = $entry;
        $resolvedFiles[] = $resolved;
    }

    return $resolvedFiles;
}

function install_trash_restore_file(array $file): void
{
    $state = trash_restore_file_state($file['from'], $file['trash']);
    if ($state === 'trash_only') {
        if (!rename($file['trash'], $file['from'])) {
            throw new RuntimeException('Не вдалося перемістити файл із кошика: ' . $file['filename']);
        }
    } elseif ($state !== 'live_only' && $state !== 'both_equal') {
        throw new RuntimeException('Файл відновлення відсутній або конфліктує з live-копією: ' . $file['filename']);
    }

    $reference = photo_file_reference_from_path($file['from']);
    if (!is_array($reference)) {
        throw new RuntimeException('Відновлений файл вийшов за межі canonical media-папок: ' . $file['filename']);
    }
    $permissionsOk = ($reference['area'] ?? '') === 'storage'
        ? enforce_private_file_permissions($file['from'])
        : enforce_shared_file_permissions($file['from']);
    if (!$permissionsOk) {
        throw new RuntimeException('Не вдалося встановити безпечні права відновленого файла: ' . $file['filename']);
    }
}

function finalize_trash_restore_file(array $file): void
{
    $state = trash_restore_file_state($file['from'], $file['trash']);
    if ($state === 'both_equal') {
        if (!unlink_file_with_log($file['trash'], 'Failed duplicate trash file cleanup')) {
            throw new RuntimeException('Не вдалося прибрати підтверджений дублікат із кошика: ' . $file['filename']);
        }
        $state = trash_restore_file_state($file['from'], $file['trash']);
    }
    if ($state !== 'live_only') {
        throw new RuntimeException('Відновлений файл не має однозначної live-копії: ' . $file['filename']);
    }
}

function assert_restored_photo_row_matches_manifest(array $row, array $photoData): void
{
    foreach (['id', 'filename', 'thumbnail_filename'] as $field) {
        if ((string) ($row[$field] ?? '') !== (string) ($photoData[$field] ?? '')) {
            throw new RuntimeException('ID фотографії вже зайнятий іншим записом. Відновлення зупинено.');
        }
    }
    if (strtolower(trim((string) ($row['original_sha256'] ?? '')))
        !== strtolower(trim((string) ($photoData['original_sha256'] ?? '')))) {
        throw new RuntimeException('Хеш оригіналу в БД не відповідає trash manifest.');
    }
}

function restore_photo_from_trash_unlocked(PDO $pdo, string $operationId): void
{
    if (preg_match('/\A[a-f0-9]{32}\z/', $operationId) !== 1) {
        throw new InvalidArgumentException('Некоректний ID операції кошика.');
    }
    $manifestPath = trash_path($operationId . '.json');
    if (!recover_interrupted_trash_manifest_update($manifestPath)
        || !is_file($manifestPath) || is_link($manifestPath)) {
        throw new InvalidArgumentException('Журнал видалення не знайдено.');
    }

    $manifest = json_decode((string) file_get_contents($manifestPath), true);
    if (!is_array($manifest) || !hash_equals($operationId, (string) ($manifest['operation_id'] ?? ''))) {
        throw new RuntimeException('Некоректний журнал видалення.');
    }
    $photoData = $manifest['photo_data'] ?? null;
    if (!is_array($photoData) || (int) ($photoData['id'] ?? 0) < 1) {
        throw new RuntimeException('У журналі немає метаданих фотографії для відновлення.');
    }
    $files = is_array($manifest['files'] ?? null) ? $manifest['files'] : [];
    if (!trash_manifest_contains_required_photo_files($photoData, $files)) {
        throw new RuntimeException('Журнал не містить повного canonical media-набору. Відновлення зупинено.');
    }
    $resolvedFiles = resolve_trash_restore_files($files);
    if (!rewrite_trash_manifest_unresolved($manifestPath, $files, 'restore_in_progress')) {
        throw new RuntimeException('Не вдалося зафіксувати початок відновлення в журналі.');
    }

    try {
        $pdo->beginTransaction();
        $select = $pdo->prepare(
            'SELECT id, filename, thumbnail_filename, original_sha256 FROM photos WHERE id = :id FOR UPDATE'
        );
        $select->execute(['id' => (int) $photoData['id']]);
        $existing = $select->fetch(PDO::FETCH_ASSOC);
        if (is_array($existing)) {
            assert_restored_photo_row_matches_manifest($existing, $photoData);
        }

        foreach ($resolvedFiles as $file) {
            install_trash_restore_file($file);
        }
        foreach ($resolvedFiles as $file) {
            finalize_trash_restore_file($file);
        }

        if (!is_array($existing)) {
            $albumId = isset($photoData['album_id']) ? (int) $photoData['album_id'] : null;
            if ($albumId !== null && !album_exists($albumId, $pdo)) {
                $albumId = null;
            }
            $stmt = $pdo->prepare(
                'INSERT INTO photos
                (id, album_id, filename, thumbnail_filename, original_name, title, description, mime_type, file_size, width, height, camera_make, camera_model, lens_model, taken_at, exif_json, original_sha256, dominant_color, lock_version, created_at)
                VALUES
                (:id, :album_id, :filename, :thumbnail_filename, :original_name, :title, :description, :mime_type, :file_size, :width, :height, :camera_make, :camera_model, :lens_model, :taken_at, :exif_json, :original_sha256, :dominant_color, :lock_version, :created_at)'
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
                'lock_version' => max(1, (int) ($photoData['lock_version'] ?? 1)),
                'created_at' => $photoData['created_at'],
            ]);
            sync_photo_tags(
                (int) $photoData['id'],
                is_array($manifest['tags'] ?? null) ? $manifest['tags'] : [],
                $pdo
            );
        }
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }

    if (!rewrite_trash_manifest_unresolved($manifestPath, $files, 'restore_committed')) {
        throw new RuntimeException('Фото відновлено, але не вдалося зафіксувати завершення. Повторіть recovery.');
    }
    if (!@unlink($manifestPath)) {
        throw new RuntimeException('Фото відновлено, але журнал не видалено. Повторіть recovery.');
    }
}

function restore_photo_from_trash(PDO $pdo, string $operationId): void
{
    if (preg_match('/\A[a-f0-9]{32}\z/', $operationId) !== 1) {
        throw new InvalidArgumentException('Некоректний ID операції кошика.');
    }
    $maintenanceLock = acquire_media_maintenance_lock(LOCK_SH);
    $operationLock = null;
    $operationLockPath = trash_path('.restore_' . $operationId . '.lock');
    try {
        $operationLock = open_private_file($operationLockPath, 'c+');
        if ($operationLock === false || !flock($operationLock, LOCK_EX)) {
            throw new RuntimeException('Не вдалося заблокувати операцію відновлення.');
        }
        restore_photo_from_trash_unlocked($pdo, $operationId);
    } finally {
        if (is_resource($operationLock)) {
            flock($operationLock, LOCK_UN);
            fclose($operationLock);
            @unlink($operationLockPath);
        }
        release_media_maintenance_lock($maintenanceLock);
    }
}

function purge_photo_from_trash_unlocked(string $operationId): void
{
    if (preg_match('/\A[a-f0-9]{32}\z/', $operationId) !== 1) {
        throw new InvalidArgumentException('Некоректний ID операції кошика.');
    }

    $manifestPath = trash_path($operationId . '.json');
    if (!recover_interrupted_trash_manifest_update($manifestPath)
        || !is_file($manifestPath) || is_link($manifestPath)) {
        throw new InvalidArgumentException('Журнал видалення не знайдено.');
    }

    $manifest = json_decode((string) file_get_contents($manifestPath), true);
    if (!is_array($manifest)) {
        throw new RuntimeException('Некоректний журнал видалення.');
    }
    if (in_array((string) ($manifest['status'] ?? ''), ['restore_in_progress', 'restore_committed'], true)) {
        throw new RuntimeException('Ця операція має незавершене відновлення. Спочатку повторіть recovery.');
    }
    $photoId = (int) ($manifest['photo_id'] ?? 0);
    if ($photoId > 0) {
        $check = db()->prepare('SELECT COUNT(*) FROM photos WHERE id = :id');
        $check->execute(['id' => $photoId]);
        if ((int) $check->fetchColumn() > 0) {
            throw new RuntimeException('Фото існує в БД; очищення його recovery-файлів заборонено.');
        }
    }

    $errors = remove_trashed_photo_files([
        'files' => is_array($manifest['files'] ?? null) ? $manifest['files'] : [],
        'manifest' => $manifestPath,
    ]);
    if ($errors !== []) {
        throw new RuntimeException(implode(' ', $errors));
    }
}

function purge_photo_from_trash(string $operationId): void
{
    $maintenanceLock = acquire_media_maintenance_lock(LOCK_SH);
    try {
        purge_photo_from_trash_unlocked($operationId);
    } finally {
        release_media_maintenance_lock($maintenanceLock);
    }
}

function purge_all_trash(): void
{
    $maintenanceLock = acquire_media_maintenance_lock(LOCK_SH);
    try {
        $manifests = glob(trash_path('*.json')) ?: [];
        $errors = [];
        foreach ($manifests as $manifestPath) {
            $operationId = pathinfo($manifestPath, PATHINFO_FILENAME);
            try {
                purge_photo_from_trash_unlocked($operationId);
            } catch (Throwable $exception) {
                $errors[] = $exception->getMessage();
            }
        }
        if ($errors !== []) {
            throw new RuntimeException('Деякі файли не вдалося видалити: ' . implode(' ', $errors));
        }
    } finally {
        release_media_maintenance_lock($maintenanceLock);
    }
}

function reorder_album(PDO $pdo, int $albumId, string $direction): void
{
    if ($direction !== 'up' && $direction !== 'down') {
        throw new InvalidArgumentException('Некоректний напрямок сортування.');
    }

    try {
        $pdo->beginTransaction();

        // Lock the complete ordering set in one stable order. Concurrent reorder
        // requests then read the result of the preceding committed operation.
        $stmt = $pdo->query(
            'SELECT id, sort_order FROM albums ORDER BY sort_order ASC, name ASC, id ASC FOR UPDATE'
        );
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
