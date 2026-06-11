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
} catch (Throwable) {
    set_flash('error', 'Не вдалося завантажити фотографію для редагування.');
    redirect('admin/index.php');
}

if (!$photo) {
    set_flash('error', 'Фотографію не знайдено.');
    redirect('admin/index.php');
}

$pageTitle = 'Редагування - ' . app_name();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Помилка CSRF-захисту. Оновіть сторінку і спробуйте ще раз.';
    }

    $title = trim((string) ($_POST['title'] ?? ''));
    $description = trim((string) ($_POST['description'] ?? ''));

    if ($title === '') {
        $errors[] = 'Назва не може бути порожньою.';
    }

    if (empty($errors)) {
        try {
            $stmt = db()->prepare('UPDATE photos SET title = :title, description = :description WHERE id = :id');
            $stmt->execute([
                'title' => text_limit($title, 255),
                'description' => $description === '' ? null : $description,
                'id' => $id,
            ]);

            set_flash('success', 'Фотографію оновлено.');
            redirect('admin/index.php');
        } catch (Throwable) {
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
        <img src="<?= h(uploads_url('thumbnails', $photo['thumbnail_filename'])) ?>" alt="<?= h($photo['title']) ?>">
    </div>

    <form method="post" class="stacked-form">
        <?= csrf_field() ?>
        <label>
            Назва
            <input type="text" name="title" value="<?= h((string) $photo['title']) ?>" maxlength="255" required>
        </label>
        <label>
            Опис
            <textarea name="description" rows="6"><?= h((string) $photo['description']) ?></textarea>
        </label>
        <button class="button" type="submit">Зберегти</button>
    </form>
</section>
<?php require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
