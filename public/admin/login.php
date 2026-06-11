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

function login_client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    return is_string($ip) && $ip !== '' ? $ip : 'unknown';
}

function normalize_login_username(string $username): string
{
    $username = trim($username);
    $username = text_limit($username, 100);

    return function_exists('mb_strtolower') ? mb_strtolower($username) : strtolower($username);
}

function cleanup_old_login_attempts(): void
{
    $stmt = db()->prepare(
        'DELETE FROM login_attempts
        WHERE last_attempt_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        OR (locked_until IS NOT NULL AND locked_until < DATE_SUB(NOW(), INTERVAL 1 DAY))'
    );
    $stmt->execute();
}

function login_lock_seconds_for(string $username, string $ip): int
{
    $stmt = db()->prepare('SELECT locked_until FROM login_attempts WHERE username = :username AND ip_address = :ip_address LIMIT 1');
    $stmt->execute([
        'username' => normalize_login_username($username),
        'ip_address' => $ip,
    ]);

    $attempt = $stmt->fetch();

    if (!$attempt || empty($attempt['locked_until'])) {
        return 0;
    }

    $lockUntil = strtotime((string) $attempt['locked_until']);

    if ($lockUntil === false || $lockUntil <= time()) {
        clear_failed_logins($username, $ip);
        return 0;
    }

    return max(0, $lockUntil - time());
}

function register_failed_login(string $username, string $ip): void
{
    $config = app_config();
    $maxAttempts = (int) $config['LOGIN_MAX_ATTEMPTS'];
    $lockSeconds = (int) $config['LOGIN_LOCK_SECONDS'];
    $username = normalize_login_username($username);
    $pdo = db();

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare('SELECT attempts, locked_until FROM login_attempts WHERE username = :username AND ip_address = :ip_address LIMIT 1 FOR UPDATE');
        $stmt->execute([
            'username' => $username,
            'ip_address' => $ip,
        ]);

        $attempt = $stmt->fetch();
        $lockedUntil = null;
        $attempts = 1;

        if ($attempt) {
            $currentLock = strtotime((string) ($attempt['locked_until'] ?? ''));

            if ($currentLock !== false && $currentLock > time()) {
                $pdo->commit();
                return;
            }

            $attempts = (int) ($attempt['attempts'] ?? 0) + 1;
        }

        if ($attempts >= $maxAttempts) {
            $lockedUntil = date('Y-m-d H:i:s', time() + $lockSeconds);
            $attempts = 0;
        }

        if ($attempt) {
            $stmt = $pdo->prepare(
                'UPDATE login_attempts
                SET attempts = :attempts, locked_until = :locked_until
                WHERE username = :username AND ip_address = :ip_address'
            );
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO login_attempts (username, ip_address, attempts, locked_until)
                VALUES (:username, :ip_address, :attempts, :locked_until)'
            );
        }

        $stmt->execute([
            'username' => $username,
            'ip_address' => $ip,
            'attempts' => $attempts,
            'locked_until' => $lockedUntil,
        ]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $exception;
    }
}

function clear_failed_logins(string $username, string $ip): void
{
    $stmt = db()->prepare('DELETE FROM login_attempts WHERE username = :username AND ip_address = :ip_address');
    $stmt->execute([
        'username' => normalize_login_username($username),
        'ip_address' => $ip,
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf()) {
        $errors[] = 'Помилка CSRF-захисту. Оновіть сторінку і спробуйте ще раз.';
    }

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $ip = login_client_ip();

    if ($username === '' || $password === '') {
        $errors[] = 'Вкажіть логін і пароль.';
    }

    if (empty($errors)) {
        try {
            cleanup_old_login_attempts();
            $lockSecondsLeft = login_lock_seconds_for($username, $ip);
            if ($lockSecondsLeft > 0) {
                $errors[] = 'Забагато невдалих спроб. Спробуйте ще раз через ' . $lockSecondsLeft . ' с.';
            }
        } catch (Throwable $exception) {
            app_log_exception($exception, 'Login rate limit check failed');
            $errors[] = 'Не вдалося перевірити ліміт спроб входу. Перевірте структуру бази даних.';
        }
    }

    if (empty($errors)) {
        try {
            $stmt = db()->prepare('SELECT * FROM admins WHERE username = :username LIMIT 1');
            $stmt->execute(['username' => $username]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, (string) $admin['password_hash'])) {
                clear_failed_logins($username, $ip);
                login_admin($admin);
                redirect('admin/index.php');
            }

            register_failed_login($username, $ip);
            $errors[] = 'Неправильний логін або пароль.';
        } catch (Throwable $exception) {
            app_log_exception($exception, 'Login failed');
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
            <input type="text" name="username" required maxlength="100" autocomplete="username">
        </label>
        <label>
            Пароль
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button class="button" type="submit">Увійти</button>
    </form>
</section>
<?php require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
