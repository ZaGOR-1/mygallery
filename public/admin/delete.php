<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'auth.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'csrf.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('error', 'Видалення доступне тільки через POST-форму.');
    redirect('admin/index.php');
}

require_csrf();

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if ($id === false || $id === null || $id < 1) {
    set_flash('error', 'Некоректний ID фотографії.');
    redirect('admin/index.php');
}

try {
    $photo = fetch_photo_by_id(db(), $id);
} catch (Throwable $exception) {
    app_log_exception($exception, 'Delete fetch failed');
    set_flash('error', 'Не вдалося знайти фотографію для видалення.');
    redirect('admin/index.php');
}

if (!$photo) {
    set_flash('error', 'Фотографію не знайдено.');
    redirect('admin/index.php');
}

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'photo_service.php';

try {
    delete_photo_with_trash(db(), $id, $photo);
} catch (RuntimeException $exception) {
    app_log_exception($exception, 'Delete failed');
    set_flash('error', $exception->getMessage());
    redirect('admin/index.php');
}

set_flash('success', 'Фотографію видалено.');
redirect('admin/index.php');
