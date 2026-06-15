<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Цей файл запускається тільки з консолі.');
}

$options = getopt('', ['password-from-stdin', 'help']);
if (isset($options['help'])) {
    echo "Usage:\n";
    echo "  php tools/setup.php admin_username\n";
    echo "  ADMIN_PASSWORD='strong-password' php tools/setup.php admin_username\n";
    echo "  printf 'strong-password' | php tools/setup.php admin_username --password-from-stdin\n";
    exit(0);
}

function setup_positional_args(array $argv): array
{
    $args = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (str_starts_with((string) $arg, '--')) {
            continue;
        }

        $args[] = (string) $arg;
    }

    return $args;
}

function ask(string $label): string
{
    echo $label;
    $value = fgets(STDIN);

    return trim($value === false ? '' : $value);
}

function read_admin_password(array $options): string
{
    $fromEnv = getenv('ADMIN_PASSWORD');
    if (is_string($fromEnv) && $fromEnv !== '') {
        return $fromEnv;
    }

    if (isset($options['password-from-stdin'])) {
        $value = stream_get_contents(STDIN);
        return trim($value === false ? '' : $value);
    }

    fwrite(STDERR, "Увага: пароль буде видно під час введення. Безпечніше: ADMIN_PASSWORD='...' php tools/setup.php admin або --password-from-stdin.\n");
    return ask('Пароль адміністратора: ');
}

try {
    $adminExists = (int) db()->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0;
} catch (Throwable $exception) {
    app_log_exception($exception, 'Setup DB check failed');
    fwrite(STDERR, "Не вдалося підключитися до бази. Імпортуйте database/schema.sql і перевірте config/database.php.\n");
    exit(1);
}

if ($adminExists) {
    // Admin already exists, but we will proceed to check if they want to update the password.
}

$args = setup_positional_args($argv);
$username = $args[0] ?? ask('Логін адміністратора: ');
$password = read_admin_password($options);

if ($username === '') {
    fwrite(STDERR, "Вкажіть логін адміністратора.\n");
    exit(1);
}

if (strlen($password) < 8) {
    fwrite(STDERR, "Пароль має містити щонайменше 8 символів.\n");
    exit(1);
}

if ($adminExists) {
    $stmt = db()->prepare('SELECT id FROM admins WHERE username = :username');
    $stmt->execute(['username' => $username]);
    $adminId = $stmt->fetchColumn();

    if ($adminId !== false) {
        $stmt = db()->prepare('UPDATE admins SET password_hash = :password_hash, session_version = session_version + 1 WHERE id = :id');
        $stmt->execute([
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'id' => $adminId,
        ]);
        echo "Пароль адміністратора оновлено. Усі старі сесії скасовані.\n";
        exit(0);
    } else {
        fwrite(STDERR, "Адміністратор уже існує, але з іншим логіном. Створення кількох адміністраторів не підтримується.\n");
        exit(1);
    }
} else {
    $stmt = db()->prepare('INSERT INTO admins (username, password_hash, session_version) VALUES (:username, :password_hash, 1)');
    $stmt->execute([
        'username' => $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);
    echo "Адміністратора створено. Тепер можна увійти в /admin/login.php.\n";
}
