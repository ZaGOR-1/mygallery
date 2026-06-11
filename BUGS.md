# Bugs And Risks

Цей файл містить результат другого контрольного аудиту.

**Статус:** усі 8 пунктів з другого аудиту виправлено.

## Що було виправлено

### P2

1. **Session cookie без `HttpOnly`, `SameSite` і production `Secure`.**
   Виправлено в `app/includes/functions.php` і `app/includes/auth.php`: session cookie тепер має `HttpOnly`, `SameSite=Lax`, strict mode, а `Secure` вмикається для HTTPS/production.

2. **Brute-force lockout зберігався тільки в сесії.**
   Виправлено через таблицю `login_attempts` у `database/schema.sql` і server-side перевірку в `public/admin/login.php`. Ліміт тепер прив’язаний до username + IP і не скидається очищенням cookie.

3. **Delete-flow міг залишити битий запис у БД.**
   Виправлено в `public/admin/delete.php` і `app/includes/functions.php`: код спершу перевіряє доступність файлів, потім видаляє запис із БД, після цього прибирає файли й явно повідомляє про cleanup-помилки.

### P3

4. **Prev/next фото рахувались за `id`, а галерея сортувалась за `created_at`.**
   Виправлено в `public/photo.php`: навігація використовує той самий порядок, що й галерея, `created_at DESC, id DESC`.

5. **Не було базових security headers.**
   Виправлено через `send_security_headers()` у `app/includes/functions.php`, який викликається в `app/includes/header.php`.

6. **README радив зайві Apache `Options`.**
   Виправлено в `README.md`: приклади VirtualHost тепер використовують `Options -Indexes +FollowSymLinks`.

7. **`tools/setup.php` на Windows показував пароль під час введення.**
   Виправлено в `tools/setup.php`: Windows використовує PowerShell `Read-Host -AsSecureString`.

### P4

8. **Сервер показував зайві version headers.**
   Частково виправлено в коді/проєкті: застосунок прибирає `X-Powered-By`, `public/.htaccess` також пробує прибрати його через `mod_headers`, README містить production-директиви `expose_php = Off`, `ServerTokens Prod`, `ServerSignature Off`.

   Примітка: повний `Server: Apache/...` контролюється Apache server config, а не PHP-кодом проєкту. Його треба вимкнути в конфігурації Apache на production-сервері.

## Перевірки

- `php -l` для всіх PHP-файлів пройшов без синтаксичних помилок.
- Локальна БД оновлена: таблиця `login_attempts` створена.
- HTTP smoke-test: головна, login і сторінка фото відповідають `200`.
- Upload-папки залишаються закритими для directory listing.
- `README.md` і `config/database.php` не доступні через браузер.
- Session cookie перевірено: `HttpOnly; SameSite=Lax`.
- Security headers перевірено: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, `Content-Security-Policy`.
