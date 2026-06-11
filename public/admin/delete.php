<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'auth.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'csrf.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('error', 'Видалення доступне тільки через POST-форму.');
    redirect('admin/index.php');
}

if (!verify_csrf()) {
    set_flash('error', 'Помилка CSRF-захисту. Оновіть сторінку і спробуйте ще раз.');
    redirect('admin/index.php');
}

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if ($id === false || $id === null || $id < 1) {
    set_flash('error', 'Некоректний ID фотографії.');
    redirect('admin/index.php');
}

try {
    $stmt = db()->prepare('SELECT * FROM photos WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $photo = $stmt->fetch();
} catch (Throwable) {
    set_flash('error', 'Не вдалося знайти фотографію для видалення.');
    redirect('admin/index.php');
}

if (!$photo) {
    set_flash('error', 'Фотографію не знайдено.');
    redirect('admin/index.php');
}

$fileErrors = validate_photo_files_deletable($photo);

if (!empty($fileErrors)) {
    set_flash('error', implode(' ', $fileErrors));
    redirect('admin/index.php');
}

$folderErrors = ensure_upload_folders();

if (!empty($folderErrors)) {
    set_flash('error', implode(' ', $folderErrors));
    redirect('admin/index.php');
}

$movedFiles = [];
$pdo = db();

try {
    $movedFiles = move_photo_files_to_trash($photo);
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('DELETE FROM photos WHERE id = :id');
    $stmt->execute(['id' => $id]);
    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    app_log_exception($exception, 'Delete failed');
    $restoreErrors = restore_moved_photo_files($movedFiles);
    $message = 'Не вдалося видалити фотографію. Файли залишено на місці.';

    if (!empty($restoreErrors)) {
        $message .= ' ' . implode(' ', $restoreErrors);
    }

    set_flash('error', $message);
    redirect('admin/index.php');
}

$fileErrors = remove_trashed_photo_files($movedFiles);

if (!empty($fileErrors)) {
    app_log('Delete cleanup warning: ' . implode(' ', $fileErrors));
    set_flash('error', 'Запис із бази видалено, але частину тимчасових файлів не вдалося прибрати. Деталі записано в лог.');
    redirect('admin/index.php');
}

set_flash('success', 'Фотографію видалено.');
redirect('admin/index.php');
