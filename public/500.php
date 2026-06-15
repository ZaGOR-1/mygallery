<?php

declare(strict_types=1);

if (!function_exists('app_name')) {
    require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';
}

$errorStatusCode = (int) ($errorStatusCode ?? 500);
$errorTitle = (string) ($errorTitle ?? 'Помилка сервера');
$errorMessage = (string) ($errorMessage ?? 'Сталася внутрішня помилка. Спробуйте пізніше або перевірте логи застосунку.');
$errorDetails = (string) ($errorDetails ?? '');

if (!headers_sent()) {
    http_response_code($errorStatusCode >= 400 ? $errorStatusCode : 500);
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
        <?php if (app_debug() && $errorDetails !== ''): ?>
            <pre class="error-details"><?= h($errorDetails) ?></pre>
        <?php endif; ?>
        <div class="toolbar-actions">
            <a class="button" href="<?= h(url('gallery.php')) ?>">До галереї</a>
            <a class="button secondary" href="<?= h(url()) ?>">На головну</a>
        </div>
    </section>
</main>
</body>
</html>
