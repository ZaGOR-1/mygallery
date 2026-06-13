<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Цей файл запускається тільки з консолі.');
}

function ask(string $label): string
{
    echo $label;
    $value = fgets(STDIN);

    return trim($value === false ? '' : $value);
}

function ask_secret(string $label): string
{
    if (DIRECTORY_SEPARATOR === '\\') {
        $prompt = trim($label, ": \t\n\r\0\x0B");
        $prompt = str_replace("'", "''", $prompt);
        $script = "\$secure = Read-Host -Prompt '" . $prompt . "' -AsSecureString; "
            . "\$bstr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR(\$secure); "
            . "try { [Runtime.InteropServices.Marshal]::PtrToStringBSTR(\$bstr) } "
            . "finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR(\$bstr) }";
        $utf16Script = function_exists('mb_convert_encoding')
            ? mb_convert_encoding($script, 'UTF-16LE', 'UTF-8')
            : iconv('UTF-8', 'UTF-16LE', $script);

        if ($utf16Script === false) {
            fwrite(STDERR, "Не вдалося підготувати приховане введення пароля.\n");
            return ask($label);
        }

        $encoded = base64_encode($utf16Script);
        $value = shell_exec('powershell.exe -NoProfile -ExecutionPolicy Bypass -EncodedCommand ' . escapeshellarg($encoded));

        if (is_string($value)) {
            return trim($value);
        }

        fwrite(STDERR, "Не вдалося приховати введення пароля. Перевірте PowerShell.\n");
        return ask($label);
    }

    echo $label;
    system('stty -echo');
    $value = fgets(STDIN);
    system('stty echo');
    echo PHP_EOL;

    return trim($value === false ? '' : $value);
}

try {
    $adminExists = (int) db()->query('SELECT COUNT(*) FROM admins')->fetchColumn() > 0;
} catch (Throwable $exception) {
    app_log_exception($exception, 'Setup DB check failed');
    fwrite(STDERR, "Не вдалося підключитися до бази. Імпортуйте database/schema.sql і перевірте config/database.php.\n");
    exit(1);
}

if ($adminExists) {
    echo "Адміністратор уже існує. Новий адміністратор не створювався.\n";
    exit(0);
}

$username = $argv[1] ?? ask('Логін адміністратора: ');
$password = ask_secret('Пароль адміністратора: ');

if ($username === '') {
    fwrite(STDERR, "Вкажіть логін адміністратора.\n");
    exit(1);
}

if (strlen($password) < 8) {
    fwrite(STDERR, "Пароль має містити щонайменше 8 символів.\n");
    exit(1);
}

$stmt = db()->prepare('INSERT INTO admins (username, password_hash) VALUES (:username, :password_hash)');
$stmt->execute([
    'username' => $username,
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
]);

echo "Адміністратора створено. Тепер можна увійти в /admin/login.php.\n";
