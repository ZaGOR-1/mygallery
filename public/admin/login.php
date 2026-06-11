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

function login_lock_seconds_left(): int
{
    start_session();
    $lockUntil = (int) ($_SESSION['login_lock_until'] ?? 0);

    return max(0, $lockUntil - time());
}

function register_failed_login(): void
{
    start_session();
    $config = app_config();
    $maxAttempts = (int) $config['LOGIN_MAX_ATTEMPTS'];
    $lockSeconds = (int) $config['LOGIN_LOCK_SECONDS'];
    $attempts = (int) ($_SESSION['login_failed_attempts'] ?? 0) + 1;

    $_SESSION['login_failed_attempts'] = $attempts;

    if ($attempts >= $maxAttempts) {
        $_SESSION['login_lock_until'] = time() + $lockSeconds;
        $_SESSION['login_failed_attempts'] = 0;
    }
}

function clear_failed_logins(): void
{
    start_session();
    unset($_SESSION['login_failed_attempts'], $_SESSION['login_lock_until']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Помилка CSRF-захисту. Оновіть сторінку і спробуйте ще раз.';
    }

    $lockSecondsLeft = login_lock_seconds_left();
    if ($lockSecondsLeft > 0) {
        $errors[] = 'Забагато невдалих спроб. Спробуйте ще раз через ' . $lockSecondsLeft . ' с.';
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
                clear_failed_logins();
                login_admin($admin);
                redirect('admin/index.php');
            }

            register_failed_login();
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
