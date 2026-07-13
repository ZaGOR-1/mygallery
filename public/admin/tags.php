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
    require_csrf();

    $action = request_string($_POST, 'action', 24);

    if (empty($errors)) {
        try {
            if ($action === 'update') {
                $tagId = request_int($_POST, 'id', null, 1);
                $tagName = request_string($_POST, 'name', tag_name_max_length());
                
                if ($tagId === null) {
                    throw new InvalidArgumentException('Некоректний тег.');
                }
                
                rename_tag_with_locking(db(), (int) $tagId, $tagName);
                
                set_flash('success', 'Тег оновлено.');
                redirect('admin/tags.php');
            } elseif ($action === 'merge') {
                $sourceId = request_int($_POST, 'source_id', null, 1);
                $targetId = request_int($_POST, 'target_id', null, 1);
                
                if (!$sourceId || !$targetId || $sourceId === $targetId) {
                    throw new InvalidArgumentException('Некоректні теги для об\'єднання.');
                }
                
                merge_tags_with_locking(db(), (int) $sourceId, (int) $targetId);
                
                set_flash('success', 'Теги об\'єднано.');
                redirect('admin/tags.php');
            } elseif ($action === 'delete') {
                $tagId = request_int($_POST, 'id', null, 1);
                if ($tagId === null) {
                    throw new InvalidArgumentException('Некоректний тег.');
                }
                
                delete_tag_with_locking(db(), (int) $tagId);
                
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
    <div class="toolbar-actions">
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
    
    <div class="admin-edit-grid">
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
    <section class="admin-empty-state">
        <h2>Тегів поки немає</h2>
        <p>Теги створюються під час завантаження або редагування фотографій. Після цього тут можна буде їх перейменувати, об'єднати або видалити.</p>
        <a class="button secondary" href="<?= h(url('admin/upload.php')) ?>">Завантажити фото</a>
    </section>
<?php else: ?>
    <div class="admin-list admin-collection-list">
        <?php foreach ($tags as $tag): ?>
            <article class="admin-item tag-item">
                <div class="admin-tag-mark" aria-hidden="true">#</div>
                <div class="admin-item-body">
                    <h2>#<?= h($tag['name']) ?></h2>
                    <div class="admin-meta">
                        <span><?= h((string) (int) $tag['photo_count']) ?> фотографій</span>
                    </div>
                </div>
                <div class="admin-actions">
                    <a class="button secondary" href="<?= h(url('gallery.php?tag_id=' . (int) $tag['id'])) ?>">Перегляд</a>
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
