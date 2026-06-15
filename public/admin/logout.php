<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'auth.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'csrf.php';

require_admin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    set_flash('error', 'Вихід з адмінпанелі доступний тільки через POST-форму.');
    redirect('admin/index.php');
}

require_csrf();

logout_admin();
redirect('gallery.php');
