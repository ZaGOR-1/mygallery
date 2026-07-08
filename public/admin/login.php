<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'auth.php';
require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'csrf.php';

send_admin_cache_headers();

if (is_admin_logged_in()) {
    redirect('admin/index.php');
}

$pageTitle = 'Вхід - ' . app_name();
$errors = [];

function normalize_login_username(string $username): string
{
    $username = trim($username);
    $username = text_limit($username, 100);

    return mb_strtolower($username, 'UTF-8');
}

function login_buckets(string $username, string $ip): array
{
    $username = normalize_login_username($username);

    return [
        ['username' => $username, 'ip_address' => $ip, 'limit' => (int) app_config()['LOGIN_MAX_ATTEMPTS']],
        ['username' => $username, 'ip_address' => '*', 'limit' => (int) app_config()['LOGIN_ACCOUNT_MAX_ATTEMPTS']],
        ['username' => '*', 'ip_address' => $ip, 'limit' => (int) app_config()['LOGIN_IP_MAX_ATTEMPTS']],
    ];
}

function cleanup_old_login_attempts(): void
{
    if (random_int(1, 20) !== 1) {
        return;
    }

    $stmt = db()->prepare(
        'DELETE FROM login_attempts
        WHERE last_attempt_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
        OR (locked_until IS NOT NULL AND locked_until < DATE_SUB(NOW(), INTERVAL 1 DAY))'
    );
    $stmt->execute();
}

function login_lock_seconds_for(string $username, string $ip): int
{
    $maxSeconds = 0;

    foreach (login_buckets($username, $ip) as $bucket) {
        $stmt = db()->prepare('SELECT locked_until FROM login_attempts WHERE username = :username AND ip_address = :ip_address LIMIT 1');
        $stmt->execute([
            'username' => $bucket['username'],
            'ip_address' => $bucket['ip_address'],
        ]);

        $attempt = $stmt->fetch();

        if (!$attempt || empty($attempt['locked_until'])) {
            continue;
        }

        $lockUntil = strtotime((string) $attempt['locked_until']);

        if ($lockUntil === false || $lockUntil <= time()) {
            continue;
        }

        $maxSeconds = max($maxSeconds, $lockUntil - time());
    }

    return $maxSeconds;
}

function upsert_failed_login_bucket(PDO $pdo, array $bucket, int $lockSeconds): void
{
    // INSERT IGNORE creates the row before SELECT ... FOR UPDATE. This avoids
    // duplicate-key races when two requests fail the same bucket simultaneously.
    $insert = $pdo->prepare(
        'INSERT IGNORE INTO login_attempts (username, ip_address, attempts, locked_until)
        VALUES (:username, :ip_address, 0, NULL)'
    );
    $insert->execute([
        'username' => $bucket['username'],
        'ip_address' => $bucket['ip_address'],
    ]);

    $stmt = $pdo->prepare('SELECT attempts, locked_until FROM login_attempts WHERE username = :username AND ip_address = :ip_address LIMIT 1 FOR UPDATE');
    $stmt->execute([
        'username' => $bucket['username'],
        'ip_address' => $bucket['ip_address'],
    ]);

    $attempt = $stmt->fetch();
    if (!$attempt) {
        throw new RuntimeException('Не вдалося створити login rate-limit bucket.');
    }

    $currentLock = strtotime((string) ($attempt['locked_until'] ?? ''));
    if ($currentLock !== false && $currentLock > time()) {
        return;
    }

    $attempts = (int) ($attempt['attempts'] ?? 0) + 1;
    $lockedUntil = null;

    if ($attempts >= max(1, (int) $bucket['limit'])) {
        $lockedUntil = date('Y-m-d H:i:s', time() + $lockSeconds);
        $attempts = 0;
    }

    $update = $pdo->prepare(
        'UPDATE login_attempts
        SET attempts = :attempts, locked_until = :locked_until, last_attempt_at = CURRENT_TIMESTAMP
        WHERE username = :username AND ip_address = :ip_address'
    );
    $update->execute([
        'username' => $bucket['username'],
        'ip_address' => $bucket['ip_address'],
        'attempts' => $attempts,
        'locked_until' => $lockedUntil,
    ]);
}

function register_failed_login(string $username, string $ip): void
{
    $pdo = db();
    $pdo->beginTransaction();

    try {
        foreach (login_buckets($username, $ip) as $bucket) {
            upsert_failed_login_bucket($pdo, $bucket, (int) app_config()['LOGIN_LOCK_SECONDS']);
        }

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
    $pdo = db();
    $stmt = $pdo->prepare('DELETE FROM login_attempts WHERE (username = :username AND ip_address IN (:ip_exact, "*")) OR (username = "*" AND ip_address = :ip_global)');
    $stmt->execute([
        'username' => normalize_login_username($username),
        'ip_exact' => $ip,
        'ip_global' => $ip,
    ]);
}

function dummy_password_hash(): string
{
    return '$2y$12$XnCWVWzrthr8eekn.vsf1eHkQ66z2igpVGGNdpDOkaToEVcbEajaK';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_csrf();

    $username = trim((string) ($_POST['username'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $ip = client_ip();

    if ($username === '' || $password === '') {
        $errors[] = 'Вкажіть логін і пароль.';
    }

    if (empty($errors)) {
        try {
            cleanup_old_login_attempts();
            $lockSecondsLeft = login_lock_seconds_for($username, $ip);

            // Час-базований локаут: поки bucket залоковано, спроба входу
            // відхиляється повністю. CAPTCHA не може обходити лок (див. C1 в аудиті).
            if ($lockSecondsLeft > 0) {
                if (!headers_sent()) {
                    header('Retry-After: ' . $lockSecondsLeft);
                }

                $lockMinutes = max(1, (int) ceil($lockSecondsLeft / 60));
                $errors[] = 'Забагато невдалих спроб входу. Спробуйте ще раз приблизно через ' . $lockMinutes . ' хв.';
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
            $hash = $admin ? (string) $admin['password_hash'] : dummy_password_hash();
            $passwordOk = password_verify($password, $hash);

            if ($admin && $passwordOk) {
                clear_failed_logins($username, $ip);
                login_admin($admin);
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
            <input type="text" name="username" value="<?= h($username ?? '') ?>" required maxlength="100" autocomplete="username">
        </label>
        <label>
            Пароль
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button class="button" type="submit">Увійти</button>
    </form>
</section>
<?php require dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'footer.php'; ?>
