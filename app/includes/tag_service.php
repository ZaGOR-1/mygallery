<?php

declare(strict_types=1);

/** @return list<int> */
function tag_photo_ids_for_update(PDO $pdo, int $tagId): array
{
    $stmt = $pdo->prepare('SELECT photo_id FROM photo_tags WHERE tag_id = ? ORDER BY photo_id FOR UPDATE');
    $stmt->execute([$tagId]);

    return array_values(array_unique(array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN))));
}

/** @param list<int> $photoIds */
function bump_photo_lock_versions(PDO $pdo, array $photoIds): void
{
    $photoIds = array_values(array_unique(array_filter(array_map('intval', $photoIds), static fn (int $id): bool => $id > 0)));
    if ($photoIds === []) {
        return;
    }
    $placeholders = implode(',', array_fill(0, count($photoIds), '?'));
    $stmt = $pdo->prepare('UPDATE photos SET lock_version = lock_version + 1 WHERE id IN (' . $placeholders . ')');
    $stmt->execute($photoIds);
    if ($stmt->rowCount() !== count($photoIds)) {
        throw new RuntimeException('Не вдалося оновити optimistic revision усіх affected photos.');
    }
}

/** @param list<int> $tagIds */
function require_tag_rows_for_update(PDO $pdo, array $tagIds): void
{
    $tagIds = array_values(array_unique(array_map('intval', $tagIds)));
    sort($tagIds, SORT_NUMERIC);
    if ($tagIds === []) {
        throw new InvalidArgumentException('Не вказано теги.');
    }
    $placeholders = implode(',', array_fill(0, count($tagIds), '?'));
    $stmt = $pdo->prepare('SELECT id FROM tags WHERE id IN (' . $placeholders . ') ORDER BY id FOR UPDATE');
    $stmt->execute($tagIds);
    $found = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    sort($found, SORT_NUMERIC);
    if ($found !== $tagIds) {
        throw new InvalidArgumentException('Один або кілька тегів не знайдено.');
    }
}

function rename_tag_with_locking(PDO $pdo, int $tagId, string $tagName): void
{
    $tagName = clean_tag_name($tagName);
    $slug = tag_slug($tagName);
    if ($tagId < 1 || $tagName === '' || $slug === '') {
        throw new InvalidArgumentException('Некоректний тег або назва.');
    }

    $pdo->beginTransaction();
    try {
        require_tag_rows_for_update($pdo, [$tagId]);
        $affectedPhotoIds = tag_photo_ids_for_update($pdo, $tagId);
        $duplicate = $pdo->prepare('SELECT id FROM tags WHERE (name = ? OR slug = ?) AND id <> ? LIMIT 1 FOR UPDATE');
        $duplicate->execute([$tagName, $slug, $tagId]);
        if ($duplicate->fetchColumn() !== false) {
            throw new InvalidArgumentException('Тег із такою назвою вже існує.');
        }
        $update = $pdo->prepare('UPDATE tags SET name = ?, slug = ? WHERE id = ?');
        $update->execute([$tagName, $slug, $tagId]);
        bump_photo_lock_versions($pdo, $affectedPhotoIds);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function merge_tags_with_locking(PDO $pdo, int $sourceId, int $targetId): void
{
    if ($sourceId < 1 || $targetId < 1 || $sourceId === $targetId) {
        throw new InvalidArgumentException('Некоректні теги для об’єднання.');
    }

    $pdo->beginTransaction();
    try {
        require_tag_rows_for_update($pdo, [$sourceId, $targetId]);
        $affectedPhotoIds = tag_photo_ids_for_update($pdo, $sourceId);
        $insert = $pdo->prepare('INSERT IGNORE INTO photo_tags (photo_id, tag_id) VALUES (?, ?)');
        foreach ($affectedPhotoIds as $photoId) {
            $insert->execute([$photoId, $targetId]);
        }
        $delete = $pdo->prepare('DELETE FROM tags WHERE id = ?');
        $delete->execute([$sourceId]);
        bump_photo_lock_versions($pdo, $affectedPhotoIds);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

function delete_tag_with_locking(PDO $pdo, int $tagId): void
{
    if ($tagId < 1) {
        throw new InvalidArgumentException('Некоректний тег.');
    }

    $pdo->beginTransaction();
    try {
        require_tag_rows_for_update($pdo, [$tagId]);
        $affectedPhotoIds = tag_photo_ids_for_update($pdo, $tagId);
        $delete = $pdo->prepare('DELETE FROM tags WHERE id = ?');
        $delete->execute([$tagId]);
        bump_photo_lock_versions($pdo, $affectedPhotoIds);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}
