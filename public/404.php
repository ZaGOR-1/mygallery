<?php

declare(strict_types=1);

if (!function_exists('app_name')) {
    require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';
}

$errorStatusCode = (int) ($errorStatusCode ?? 404);
$errorTitle = (string) ($errorTitle ?? 'Сторінку не знайдено');
$errorMessage = (string) ($errorMessage ?? 'Перевірте адресу або поверніться до галереї.');

if (!headers_sent()) {
    http_response_code(404);
    send_security_headers();
}
?>
<!doctype html>
<html lang="uk">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= h($errorTitle . ' - ' . app_name()) ?></title>
    <link rel="stylesheet" href="<?= h(local_url('assets/css/style.css')) ?>">
</head>
<body>
<main class="error-page">
    <section class="container error-panel">
        <span class="error-code">HTTP <?= h((string) $errorStatusCode) ?></span>
        <h1><?= h($errorTitle) ?></h1>
        <p><?= h($errorMessage) ?></p>
        <div class="toolbar-actions">
            <a class="button" href="<?= h(url('gallery.php')) ?>">До галереї</a>
            <a class="button secondary" href="<?= h(url()) ?>">На головну</a>
        </div>
    </section>
</main>
</body>
</html>
