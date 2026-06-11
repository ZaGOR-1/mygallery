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

$fileErrors = delete_photo_files($photo);

if (!empty($fileErrors)) {
    set_flash('error', implode(' ', $fileErrors));
    redirect('admin/index.php');
}

try {
    $stmt = db()->prepare('DELETE FROM photos WHERE id = :id');
    $stmt->execute(['id' => $id]);
} catch (Throwable) {
    set_flash('error', 'Файли видалено, але запис із бази даних видалити не вдалося.');
    redirect('admin/index.php');
}

set_flash('success', 'Фотографію видалено.');
redirect('admin/index.php');
