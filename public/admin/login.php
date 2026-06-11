<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'auth.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'csrf.php';

if (is_admin_logged_in()) {
    redirect('admin/index.php');
}

$pageTitle = 'Вхід - ' . app_name();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Помилка CSRF-захисту. Оновіть сторінку і спробуйте ще раз.';
    }

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    if ($username === '' || $password === '') {
        $errors[] = 'Вкажіть логін і пароль.';
    }

    if (empty($errors)) {
        try {
            $stmt = db()->prepare('SELECT * FROM admins WHERE username = :username LIMIT 1');
            $stmt->execute(['username' => $username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, (string) $admin['password_hash'])) {
                login_admin($admin);
                redirect('admin/index.php');
            }

            $errors[] = 'Неправильний логін або пароль.';
        } catch (Throwable) {
            $errors[] = 'Не вдалося виконати вхід. Перевірте налаштування бази даних.';
        }
    }
}

require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'header.php';
?>
<section class="form-panel narrow">
    <h1>Вхід адміністратора</h1>

    <?php foreach ($errors as $error): ?>
        <div class="alert alert-error"><?= h($error) ?></div>
    <?php endforeach; ?>

    <form method="post" class="stacked-form">
        <?= csrf_field() ?>
        <label>
            Логін
            <input type="text" name="username" required autocomplete="username">
        </label>
        <label>
            Пароль
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button class="button" type="submit">Увійти</button>
    </form>
</section>
<?php require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
