<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';

$pageTitle = 'Сторінку не знайдено - ' . app_name();
require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'header.php';
?>
<section class="page-title">
    <h1>Сторінку не знайдено</h1>
    <p>Перевірте адресу або поверніться до галереї.</p>
    <a class="button" href="<?= h(url('gallery.php')) ?>">До галереї</a>
</section>
<?php require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
