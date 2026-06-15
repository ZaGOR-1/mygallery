<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'auth.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'csrf.php';

require_admin();

$pageTitle = 'Теги - ' . app_name();
$errors = [];
$editingTagId = get_tag_id_from_request('edit');
$editingTag = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Помилка CSRF-захисту. Оновіть сторінку і спробуйте ще раз.';
    }

    $action = (string) ($_POST['action'] ?? '');

    if (empty($errors)) {
        try {
            if ($action === 'update') {
                $tagId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                $tagName = (string) ($_POST['name'] ?? '');
                
                if ($tagId === false || $tagId === null || $tagId < 1) {
                    throw new InvalidArgumentException('Некоректний тег.');
                }
                
                $tagName = clean_tag_name($tagName);
                $slug = tag_slug($tagName);
                
                if ($tagName === '' || $slug === '') {
                    throw new InvalidArgumentException('Назва тегу не може бути порожньою.');
                }
                
                $pdo = db();
                $stmt = $pdo->prepare('SELECT id FROM tags WHERE (name = ? OR slug = ?) AND id != ?');
                $stmt->execute([$tagName, $slug, $tagId]);
                if ($stmt->fetch()) {
                    throw new InvalidArgumentException('Тег із такою назвою вже існує.');
                }
                
                $stmt = $pdo->prepare('UPDATE tags SET name = ?, slug = ? WHERE id = ?');
                $stmt->execute([$tagName, $slug, $tagId]);
                
                set_flash('success', 'Тег оновлено.');
                redirect('admin/tags.php');
            } elseif ($action === 'merge') {
                $sourceId = filter_input(INPUT_POST, 'source_id', FILTER_VALIDATE_INT);
                $targetId = filter_input(INPUT_POST, 'target_id', FILTER_VALIDATE_INT);
                
                if (!$sourceId || !$targetId || $sourceId === $targetId) {
                    throw new InvalidArgumentException('Некоректні теги для об\'єднання.');
                }
                
                $pdo = db();
                $pdo->beginTransaction();
                
                $stmt = $pdo->prepare('SELECT photo_id FROM photo_tags WHERE tag_id = ?');
                $stmt->execute([$sourceId]);
                $photos = $stmt->fetchAll();
                
                $insertStmt = $pdo->prepare('INSERT IGNORE INTO photo_tags (photo_id, tag_id) VALUES (?, ?)');
                foreach ($photos as $photo) {
                    $insertStmt->execute([$photo['photo_id'], $targetId]);
                }
                
                $delStmt = $pdo->prepare('DELETE FROM tags WHERE id = ?');
                $delStmt->execute([$sourceId]);
                
                $pdo->commit();
                
                set_flash('success', 'Теги об\'єднано.');
                redirect('admin/tags.php');
            } elseif ($action === 'delete') {
                $tagId = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
                if ($tagId === false || $tagId === null || $tagId < 1) {
                    throw new InvalidArgumentException('Некоректний тег.');
                }
                
                $stmt = db()->prepare('DELETE FROM tags WHERE id = ?');
                $stmt->execute([$tagId]);
                
                set_flash('success', 'Тег видалено.');
                redirect('admin/tags.php');
            } elseif ($action === 'prune') {
                prune_unused_tags();
                set_flash('success', 'Висячі теги видалено.');
                redirect('admin/tags.php');
            } else {
                $errors[] = 'Невідома дія.';
            }
        } catch (InvalidArgumentException $exception) {
            $errors[] = $exception->getMessage();
        } catch (Throwable $exception) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            app_log_exception($exception, 'Tag admin action failed');
            $errors[] = 'Не вдалося виконати дію.';
        }
    }
}

try {
    $tags = get_tag_options(true);

    if ($editingTagId !== null) {
        foreach ($tags as $tag) {
            if ((int) $tag['id'] === $editingTagId) {
                $editingTag = $tag;
                break;
            }
        }
    }
} catch (Throwable $exception) {
    app_log_exception($exception, 'Tags page failed');
    $tags = [];
    $errors[] = 'Не вдалося завантажити теги.';
}

require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'header.php';
?>
<section class="admin-toolbar">
    <div>
        <h1>Теги</h1>
        <p>Керування тегами: перейменування, видалення та об'єднання.</p>
    </div>
    <div style="display: flex; gap: 1rem;">
        <form method="post" action="<?= h(url('admin/tags.php')) ?>" data-confirm="Видалити всі теги, які не прив'язані до жодної фотографії?">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="prune">
            <button type="submit" class="button secondary">Видалити порожні теги</button>
        </form>
        <a class="button secondary" href="<?= h(url('admin/index.php')) ?>">До адмінпанелі</a>
    </div>
</section>

<?php foreach ($errors as $error): ?>
    <div class="alert alert-error"><?= h($error) ?></div>
<?php endforeach; ?>

<?php if ($editingTag): ?>
<section class="form-panel">
    <h2>Редагувати тег: <?= h($editingTag['name']) ?></h2>
    
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 2rem;">
        <!-- Ренейм форми -->
        <form method="post" action="<?= h(url('admin/tags.php')) ?>" class="stacked-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" value="<?= h((string) $editingTag['id']) ?>">
            <label>
                Нова назва
                <input type="text" name="name" value="<?= h((string) $editingTag['name']) ?>" maxlength="60" required>
            </label>
            <div class="filter-actions">
                <button class="button" type="submit">Зберегти назву</button>
                <a class="button secondary" href="<?= h(url('admin/tags.php')) ?>">Скасувати</a>
            </div>
        </form>
        
        <!-- Об'єднання форми -->
        <form method="post" action="<?= h(url('admin/tags.php')) ?>" class="stacked-form" data-confirm="Увага! Тег буде видалено, а всі його фотографії отримають новий вибраний тег. Продовжити?">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="merge">
            <input type="hidden" name="source_id" value="<?= h((string) $editingTag['id']) ?>">
            <label>
                Об'єднати цей тег у:
                <select name="target_id" required>
                    <option value="">-- Виберіть цільовий тег --</option>
                    <?php foreach ($tags as $tag): ?>
                        <?php if ((int) $tag['id'] !== (int) $editingTag['id']): ?>
                            <option value="<?= h((string) $tag['id']) ?>">
                                <?= h($tag['name']) ?> (<?= h((string) (int) $tag['photo_count']) ?> фото)
                            </option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
            </label>
            <div class="filter-actions">
                <button class="button danger" type="submit">Об'єднати теги</button>
            </div>
        </form>
    </div>
</section>
<?php endif; ?>

<?php if (empty($tags)): ?>
    <p class="empty-state">Тегів поки немає.</p>
<?php else: ?>
    <div class="admin-list">
        <?php foreach ($tags as $tag): ?>
            <article class="admin-item tag-item">
                <div>
                    <h2>#<?= h($tag['name']) ?></h2>
                    <p><?= h((string) (int) $tag['photo_count']) ?> фотографій</p>
                </div>
                <div class="admin-actions">
                    <a class="button secondary" href="<?= h(url('gallery.php?tag=' . urlencode($tag['slug']))) ?>">Перегляд</a>
                    <a class="button secondary" href="<?= h(url('admin/tags.php?edit=' . (int) $tag['id'])) ?>">Редагувати</a>
                    <form method="post" action="<?= h(url('admin/tags.php')) ?>" data-confirm="Видалити цей тег з усіх фотографій?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= h((string) $tag['id']) ?>">
                        <button class="button danger" type="submit">Видалити</button>
                    </form>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
