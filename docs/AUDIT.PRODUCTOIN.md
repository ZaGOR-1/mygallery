Ти — senior PHP production engineer, security auditor і DevOps reviewer. Твоє завдання — провести повний аудит готовності PHP-проєкту MyGallery до публікації в Інтернет.

Мета аудиту: визначити, чи можна безпечно деплоїти цей проєкт на реальний VPS/hosting із публічним доступом, і що потрібно виправити перед production.

ВАЖЛИВО:

* Не виправляй код автоматично.
* Не видаляй файли.
* Не змінюй логіку проєкту.
* Єдина дозволена зміна — створити або оновити файл `PRODUCTION_READINESS_AUDIT.md` у корені проєкту.
* Якщо файл уже існує — повністю перезапиши його актуальним аудитом.
* Не копіюй у звіт реальні паролі, токени, session ID, CSRF-токени, cookie, приватні ключі або вміст `config/database.php`.
* Якщо знайдеш секрет — вкажи тільки файл, рядок і тип проблеми, але не саме значення.

Проєкт: MyGallery — PHP-фотогалерея на чистому PHP без Laravel/Composer/React. Є публічна галерея, адмінка, завантаження JPEG, EXIF, альбоми, теги, приватні оригінали, share links, backup/restore tools, release builder, health check, self check, error pages і security hardening.

Потрібно перевірити весь проєкт з точки зору production deployment.

## 1. Перевір загальну готовність до production

Перевір:

* чи є `VERSION`;
* чи версія в `VERSION`, `README.md`, `CHANGELOG.md`, `AUDIT_REPORT.md` і документації не розходиться;
* чи `APP_ENV=production` підтримується правильно;
* чи `APP_DEBUG=false` у production;
* чи production-запуск блокується при небезпечних налаштуваннях;
* чи код не показує stack trace, SQL-помилки або шляхи файлової системи користувачу;
* чи є нормальні `404.php` і `500.php`;
* чи логування працює без витоку приватної інформації;
* чи logs не доступні з web;
* чи runtime-файли не потрапляють у release ZIP.

## 2. Перевір release ZIP

Запусти:

```bash
php tools/build_release.php
```

Після цього перевір створений ZIP у `dist/`.

У clean release ZIP НЕ повинно бути:

* `.git/`;
* `.env`;
* `config/database.php`;
* `storage/logs/*.log`;
* `storage/sessions/sess_*`;
* `storage/test_sessions/sess_*`;
* `storage/originals/*.jpg`;
* `public/uploads/large/*.jpg`;
* `public/uploads/thumbnails/*.jpg`;
* `backups/`;
* `dist/`;
* `*.zip`;
* `*.bak`;
* `*.tmp`;
* приватних фото;
* runtime-файлів;
* backup-файлів;
* локальних Windows-шляхів;
* SQL dump із реальними даними.

Перевір ZIP через:

```bash
unzip -t dist/*.zip
```

або Windows-аналог.

Окремо перевір, що в release ZIP є:

* `public/`;
* `app/`;
* `config/database.example.php`;
* `database/schema.sql`;
* `database/migrations/`;
* `tools/`;
* `README.md`;
* `CHANGELOG.md`;
* `BACKUP_RESTORE.md`;
* `VERSION`.

## 3. Перевір web server deployment

Перевір, чи правильно описаний production deployment для Apache/Nginx.

Обов’язково перевір:

* DocumentRoot має бути саме на папку `public`;
* `app/`, `config/`, `storage/`, `database/`, `tools/`, `backups/` не мають бути доступні напряму з web;
* `.htaccess` працює тільки на Apache, тому для Nginx потрібні окремі правила;
* `public/uploads` не дозволяє виконувати PHP;
* `storage/originals` не має бути публічною папкою;
* `admin/` має бути захищена авторизацією;
* `tools/` не має бути доступна через браузер;
* backup-файли не мають створюватися в `public`.

Перевір ризики:

* неправильний DocumentRoot на корінь проєкту;
* Nginx ігнорує `.htaccess`;
* Apache `AllowOverride None`, через що `.htaccess` не працює;
* відкритий directory listing;
* відкритий доступ до `config/database.php`;
* відкритий доступ до `storage/logs`.

## 4. Перевір HTTPS і HTTP security headers

Перевір:

* чи є HTTPS requirement у production;
* чи є HSTS;
* чи HSTS не вмикається випадково на localhost;
* чи є `X-Frame-Options`;
* чи є `X-Content-Type-Options`;
* чи є `Referrer-Policy`;
* чи є базовий `Content-Security-Policy`, якщо він реалізований;
* чи admin-сторінки мають `Cache-Control: no-store`;
* чи error pages не кешують приватні дані;
* чи cookies мають `HttpOnly`;
* чи cookies мають `Secure` у production;
* чи cookies мають `SameSite`.

## 5. Перевір конфігурацію

Перевір:

* `config/database.example.php`;
* чи `config/database.php` не потрапляє в release;
* чи README правильно пояснює створення `config/database.php`;
* чи не використовується MySQL `root` без пароля в production;
* чи є рекомендація створити окремого DB-користувача з мінімальними правами;
* чи production-запуск блокує root/no-password конфігурацію;
* чи `APP_DEBUG=false`;
* чи `APP_ENV=production`;
* чи `APP_URL` використовує HTTPS;
* чи немає hardcoded absolute Windows paths;
* чи немає hardcoded localhost у production-частинах.

## 6. Перевір базу даних і міграції

Перевір:

* `database/schema.sql`;
* усі файли в `database/migrations/`;
* чи міграції idempotent;
* чи міграції можна запускати повторно;
* чи немає жорсткого `USE my_photo_gallery`;
* чи schema відповідає фактичному PHP-коду;
* чи є таблиці:

  * `photos`;
  * `albums`;
  * `admins`;
  * `login_attempts`;
  * `tags`;
  * `photo_tags`;
  * `share_links`;
* чи є колонки:

  * `original_sha256`;
  * `session_version`;
* чи є потрібні indexes;
* чи є foreign keys;
* чи є FULLTEXT indexes;
* чи charset/collation підтримують українську мову;
* чи backup/restore не ламає FK;
* чи є інструкція оновлення старої бази через migrations, а не через повторний імпорт `schema.sql`.

## 7. Перевір security перед production

Перевір production-ризики:

* SQL injection;
* XSS;
* CSRF;
* auth bypass;
* IDOR/BOLA;
* path traversal;
* upload PHP/web-shell;
* direct access to originals;
* direct access to config/storage/logs;
* session fixation;
* session hijacking;
* weak admin password;
* brute force login;
* username enumeration;
* share link token guessing;
* expired share links;
* revoked share links;
* backup leakage;
* restore ZIP path traversal.

Окремо перевір:

* `/admin/stats.php` без логіну;
* `/admin/health.php` без логіну;
* `/admin/download.php?id=1` без логіну;
* `/admin/share.php` без логіну;
* POST-запити без CSRF;
* upload `photo.php`;
* upload `photo.jpg.php`;
* upload fake JPEG;
* XSS у title/description/tag/album;
* SQLi у search/tag/filter;
* прямий доступ до `storage/originals`;
* прямий доступ до `config/database.php`.

## 8. Перевір upload/media storage

Перевір:

* чи приймаються тільки JPEG;
* чи використовується `finfo`;
* чи використовується `getimagesize`;
* чи використовується `is_uploaded_file`;
* чи назви файлів випадкові;
* чи неможливий path traversal;
* чи originals зберігаються в `storage/originals`;
* чи public отримує тільки optimized `large` і `thumbnails`;
* чи `public/uploads` не виконує PHP;
* чи duplicate detection через `original_sha256` працює;
* чи upload великих файлів не валить сервер;
* чи memory limit перевіряється;
* чи GD-помилки не ігноруються;
* чи EXIF orientation 1–8 працює;
* чи cleanup/recover/regenerate не створює orphan files.

## 9. Перевір backup/restore для production

Перевір:

* `tools/backup.php`;
* `tools/verify_backup.php`;
* `tools/restore.php`;
* `BACKUP_RESTORE.md`.

Оціни:

* чи backup не створюється в public;
* чи backup не потрапляє в release ZIP;
* чи backup включає БД і media;
* чи manifest коректний;
* чи verify реально перевіряє backup;
* чи restore має захист від path traversal;
* чи restore не перезаписує небезпечні файли без підтвердження;
* чи README пояснює, де зберігати backups;
* чи є попередження, що backup містить приватні оригінали.

## 10. Перевір tools перед production

Перевір:

* `tools/self_check.php`;
* `tools/build_release.php`;
* `tools/cleanup_runtime.php`;
* `tools/cleanup_orphans.php`;
* `tools/regenerate_images.php`;
* `tools/backfill_sha256.php`;
* `tools/migrate_legacy_originals.php`;
* `tools/recover_trash.php`;
* `tools/setup.php`.

Оціни:

* чи tools запускаються з CLI;
* чи tools не доступні з web;
* чи вони безпечні на Windows/WampServer;
* чи вони безпечні на Linux/VPS;
* чи виводять зрозумілі помилки;
* чи не видаляють файли без `--dry-run` або підтвердження;
* чи не використовують небезпечні `exec`, `shell_exec`, `system`, `passthru`;
* чи backup/restore/release scripts працюють передбачувано.

## 11. Перевір health/self check

Запусти:

```bash
php tools/self_check.php
```

Якщо можливо, також перевір у браузері:

```text
/admin/health.php
```

Перевір, що health/self-check показують:

* PHP version;
* потрібні extensions:

  * `pdo_mysql`;
  * `gd`;
  * `fileinfo`;
  * `exif`;
  * `mbstring`;
* підключення до БД;
* потрібні таблиці;
* потрібні колонки;
* writable директорії;
* upload limits;
* memory limit;
* APP_ENV;
* APP_DEBUG;
* legacy originals;
* release warnings.

## 12. Перевір документацію для production

Перевір:

* `README.md`;
* `BACKUP_RESTORE.md`;
* `CHANGELOG.md`;
* `ROADMAP.md`;
* `IMPLEMENTED_FEATURES.md`;
* `SECURITY_AUDIT.md`;
* `AUDIT_REPORT.md`;
* `AGENTS.md`;
* `BUGS.md`.

Перевір, чи є в README:

* як встановити на WampServer;
* як встановити на Linux/VPS;
* як створити БД;
* як застосувати міграції;
* як налаштувати `config/database.php`;
* як створити адміна;
* як запустити self-check;
* як зібрати release ZIP;
* як деплоїти тільки `dist` ZIP;
* як налаштувати production;
* як налаштувати HTTPS;
* як зробити backup;
* як відновити backup;
* які директорії мають бути writable;
* які файли не можна публікувати.

## 13. Перевір автоматичні команди

Запусти, якщо можливо:

```bash
php -l
node --check public/assets/js/main.js
php tests/run.php
php tools/self_check.php
php tools/build_release.php
unzip -t dist/*.zip
```

Для `php -l` перевір усі `.php` файли.

Якщо команда не запускається через середовище — чесно напиши, чому саме.

## 14. Формат файлу `PRODUCTION_READINESS_AUDIT.md`

Створи або онови файл `PRODUCTION_READINESS_AUDIT.md` у корені проєкту.

Структура:

# Production Readiness Audit

## 1. Executive Summary

Напиши:

* версію проєкту;
* чи це робоча папка чи clean release;
* чи можна запускати локально;
* чи можна публікувати в Інтернет;
* чи готовий до production;
* головні production-ризики;
* загальну оцінку readiness від 1 до 10.

## 2. Audit Scope

Вкажи:

* скільки PHP-файлів перевірено;
* скільки SQL-файлів;
* скільки JS-файлів;
* скільки Markdown-файлів;
* які директорії перевірені;
* які команди запускались;
* що не вдалося перевірити.

## 3. Readiness Summary

Таблиця:

| Area                | Status            | Notes |
| ------------------- | ----------------- | ----- |
| Code syntax         | Pass/Warning/Fail | ...   |
| Database migrations | Pass/Warning/Fail | ...   |
| Security baseline   | Pass/Warning/Fail | ...   |
| Upload safety       | Pass/Warning/Fail | ...   |
| Admin protection    | Pass/Warning/Fail | ...   |
| Release ZIP         | Pass/Warning/Fail | ...   |
| Production config   | Pass/Warning/Fail | ...   |
| HTTPS/headers       | Pass/Warning/Fail | ...   |
| Backup/restore      | Pass/Warning/Fail | ...   |
| Documentation       | Pass/Warning/Fail | ...   |

## 4. Blocking Issues Before Production

Список проблем, які обов’язково треба виправити перед публікацією.

Для кожної:

* ID;
* severity;
* file/line;
* description;
* production impact;
* fix;
* verification.

Якщо blocking issues немає — напиши `No blocking production issues found`, але тільки якщо реально перевірено.

## 5. High Priority Production Issues

Той самий формат.

## 6. Medium Priority Issues

Той самий формат.

## 7. Low Priority / Hardening

Той самий формат.

## 8. Deployment Checklist

Дай конкретний checklist перед деплоєм:

* clean release ZIP created;
* release ZIP tested;
* DocumentRoot points to `public`;
* `config/database.php` created manually on server;
* `APP_ENV=production`;
* `APP_DEBUG=false`;
* HTTPS enabled;
* HSTS enabled;
* DB user is not root;
* strong admin password;
* migrations applied;
* writable directories checked;
* self-check passed;
* backup tested;
* logs outside public;
* storage outside public;
* `.git` not uploaded;
* default/test files removed.

## 9. VPS/Hosting Checklist

Дай окремо:

* Apache requirements;
* Nginx requirements;
* PHP extensions;
* MySQL/MariaDB requirements;
* file permissions;
* cron jobs;
* backup location;
* log rotation;
* firewall notes.

## 10. Manual Test Checklist Before Going Live

Дай список ручних тестів:

* login/logout;
* invalid login;
* rate limiter;
* upload valid JPEG;
* upload invalid file;
* upload large JPEG;
* duplicate upload;
* albums;
* tags;
* search/filter;
* photo page;
* prev/next;
* delete/recover;
* download original;
* stats;
* health;
* share link create/open/revoke/expire;
* backup/verify/restore;
* 404 page;
* 500 page;
* direct access to private files;
* CSRF failure;
* clean release ZIP.

## 11. Final Verdict

Напиши чітко:

* `Ready for production: YES/NO/PARTIAL`;
* що треба зробити перед публікацією;
* що можна зробити після публікації;
* який ризик, якщо деплоїти зараз;
* чи можна вважати цю версію stable.

Вимоги:

* Пиши українською мовою.
* Не вигадуй результатів.
* Не називай проєкт production-ready, якщо не перевірено release, config, migrations, storage, security і backup.
* Не називай проєкт 100% безпечним.
* Для кожної проблеми вказуй конкретний файл і бажано рядок.
* Якщо проблема залежить від Apache/Nginx/hosting config — прямо так і напиши.
* Якщо щось не вдалося перевірити — прямо напиши.
* Звіт має бути практичним, щоб по ньому можна було підготувати сайт до публікації.

Після завершення:

1. Створи або онови `PRODUCTION_READINESS_AUDIT.md`.
2. У відповіді коротко напиши:

   * аудит завершено;
   * чи готовий проєкт до production;
   * скільки blocking/high/medium/low issues знайдено;
   * 3–5 найважливіших висновків;
   * де лежить файл аудиту.
