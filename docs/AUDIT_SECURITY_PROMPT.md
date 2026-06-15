Ти — senior PHP security auditor / application security engineer. Твоє завдання — провести повний security-аудит PHP-проєкту MyGallery і перевірити, чи немає в ньому проблем безпеки.

ВАЖЛИВО:

* Не виправляй код автоматично.
* Не видаляй файли.
* Не змінюй логіку проєкту.
* Єдина дозволена зміна — створити або оновити файл `SECURITY_AUDIT.md` у корені проєкту.
* Якщо `SECURITY_AUDIT.md` уже існує — перезапиши його актуальним security-аудитом.
* Не копіюй у звіт реальні паролі, токени, session ID, CSRF-токени, cookie або приватні ключі. Якщо знайдеш секрет — вкажи тільки файл, рядок і тип проблеми, але не саме значення.

Проаналізуй увесь проєкт з точки зору безпеки:

* PHP-код;
* SQL-запити;
* SQL-міграції;
* `.htaccess`;
* `config`;
* `storage`;
* `public`;
* upload-логіку;
* admin-панель;
* login/logout;
* sessions;
* CSRF;
* теги;
* статистику;
* download original;
* backup/release tools;
* error pages;
* документацію, якщо вона містить небезпечні інструкції.

Особливо уважно перевір:

## 1. SQL Injection

Перевір:

* чи всі SQL-запити використовують prepared statements;
* чи немає ручного склеювання SQL із `$_GET`, `$_POST`, `$_REQUEST`, `$_COOKIE`;
* чи безпечно реалізовані search/filter/tag/album queries;
* чи безпечний FULLTEXT-пошук;
* чи немає небезпечних `ORDER BY`, `LIMIT`, `WHERE`, які беруть дані напряму від користувача;
* чи немає SQL injection у tools/scripts.

## 2. XSS

Перевір:

* stored XSS у title, description, album, tags, EXIF;
* reflected XSS через query parameters;
* DOM-based XSS у JavaScript;
* чи всі дані в HTML проходять через escaping;
* чи безпечно виводяться attributes: `href`, `src`, `alt`, `title`, `value`;
* чи немає небезпечного вставлення HTML без escaping;
* чи не можна вставити JavaScript через назву тегу/альбому/опис фото.

## 3. CSRF

Перевір:

* чи всі POST-запити мають CSRF-токен;
* upload;
* edit;
* delete;
* restore/purge;
* login/logout, якщо доречно;
* album actions;
* tag-related actions;
* admin actions;
* чи CSRF-токен не приймає порожнє значення;
* чи використовується `hash_equals`.

## 4. Authentication / Authorization

Перевір:

* чи всі admin-сторінки захищені `require_admin`;
* чи `/admin/stats.php`, `/admin/health.php`, `/admin/download.php` недоступні гостям;
* чи не можна скачати original без авторизації;
* чи немає IDOR/BOLA;
* чи сесія перевіряє актуальність admin-користувача;
* чи logout реально знищує сесію;
* чи немає username enumeration;
* чи є dummy password hash проти timing attacks;
* чи login rate limiter реально працює;
* чи немає race condition у rate limiter.

## 5. Session Security

Перевір:

* `session_start`;
* session cookie flags;
* `HttpOnly`;
* `Secure` у production;
* `SameSite`;
* session fixation;
* session regeneration після login;
* session timeout;
* чи admin-сторінки не кешуються;
* чи сесії не потрапляють у release ZIP;
* чи session files не доступні публічно.

## 6. File Upload Security

Перевір:

* чи можна завантажити PHP/web-shell;
* MIME type перевірку;
* extension перевірку;
* `getimagesize`;
* `is_uploaded_file`;
* `move_uploaded_file`;
* random file names;
* path traversal;
* double extension типу `photo.jpg.php`;
* polyglot files;
* обмеження розміру;
* memory limit;
* GD image processing;
* EXIF orientation;
* чи original зберігається приватно;
* чи `public/uploads` не дозволяє виконання PHP;
* чи немає прямого доступу до `storage/originals`.

## 7. File Access / Path Traversal

Перевір:

* `readfile`;
* `unlink`;
* `rename`;
* `copy`;
* `realpath`;
* download original;
* trash/recover;
* cleanup orphans;
* regenerate images;
* backup;
* build release;
* чи не можна через `../` отримати доступ до чужих файлів;
* чи всі файлові операції обмежені дозволеними директоріями.

## 8. Secrets / Private Files

Перевір:

* чи немає в проєкті `.env`, `config/database.php`, паролів, токенів;
* чи не потрапляють секрети в ZIP;
* чи не потрапляють у ZIP `.git`, logs, sessions, photos, backups;
* чи `tools/build_release.php` реально виключає приватні файли;
* чи README не радить небезпечні production-налаштування.

## 9. Security Headers / HTTP

Перевір:

* `X-Frame-Options`;
* `X-Content-Type-Options`;
* `Referrer-Policy`;
* `Content-Security-Policy`, якщо є;
* HSTS у production;
* cache headers для admin;
* правильну обробку 404/500;
* чи debug-помилки не показуються в production;
* чи `APP_DEBUG=false` працює.

## 10. Backup / Tools Security

Перевір:

* `tools/backup.php`;
* `tools/build_release.php`;
* `tools/regenerate_images.php`;
* `tools/recover_trash.php`;
* `tools/cleanup_orphans.php`;
* `tools/setup.php`;
* чи CLI tools не доступні з web;
* чи backup не створюється в публічній директорії;
* чи backup ZIP не потрапляє в release;
* чи немає небезпечних `exec`, `shell_exec`, `system`, `passthru`;
* чи setup не світить пароль.

## 11. Production Risks

Перевір:

* `APP_ENV`;
* `APP_DEBUG`;
* HTTPS;
* root MySQL без пароля;
* права на директорії;
* writable folders;
* log rotation;
* release ZIP safety;
* Apache/Nginx ризики;
* `.htaccess` залежність: якщо сервер Nginx, `.htaccess` не працює.

Запусти доступні перевірки:

* `php -l` для всіх PHP-файлів;
* `node --check public/assets/js/main.js`, якщо Node.js доступний;
* `php tools/build_release.php`;
* перевір release ZIP;
* пошук небезпечних файлів у release ZIP:

  * `.git`;
  * `.env`;
  * `config/database.php`;
  * `*.log`;
  * `sess_*`;
  * `*.jpg`;
  * `*.jpeg`;
  * `*.zip`;
  * `*.bak`;
  * `*.tmp`;
* пошук небезпечних PHP-функцій:

  * `eval`;
  * `exec`;
  * `shell_exec`;
  * `system`;
  * `passthru`;
  * `proc_open`;
  * `popen`;
  * `unserialize`;
  * dynamic `include`;
  * dynamic `require`.

Файл `SECURITY_AUDIT.md` оформи так:

# Security Audit

## 1. Executive Summary

Напиши:

* чи є критичні security-проблеми;
* чи можна використовувати локально;
* чи готовий проєкт до production;
* головні security-ризики;
* загальний security-рейтинг від 1 до 10.

## 2. Scope

Вкажи:

* які папки перевірені;
* скільки PHP/SQL/JS/MD файлів перевірено;
* які команди запускались;
* що не вдалося перевірити і чому.

## 3. Severity Summary

Таблиця:

| Severity | Count |
| -------- | ----: |
| Critical |   ... |
| High     |   ... |
| Medium   |   ... |
| Low      |   ... |
| Info     |   ... |

## 4. Critical Security Issues

Якщо немає — напиши `Critical security issues not found`.

Для кожної проблеми:

* ID;
* severity;
* status: `open`;
* файл і рядок;
* опис;
* impact;
* exploit scenario;
* how to fix;
* how to verify.

## 5. High Security Issues

Такий самий формат.

## 6. Medium Security Issues

Такий самий формат.

## 7. Low Security Issues

Такий самий формат.

## 8. Security Checklist Results

Зроби таблицю:

| Area              | Status            | Notes |
| ----------------- | ----------------- | ----- |
| SQL Injection     | Pass/Warning/Fail | ...   |
| XSS               | Pass/Warning/Fail | ...   |
| CSRF              | Pass/Warning/Fail | ...   |
| Auth              | Pass/Warning/Fail | ...   |
| Sessions          | Pass/Warning/Fail | ...   |
| Uploads           | Pass/Warning/Fail | ...   |
| File Access       | Pass/Warning/Fail | ...   |
| Secrets           | Pass/Warning/Fail | ...   |
| Release ZIP       | Pass/Warning/Fail | ...   |
| Production Config | Pass/Warning/Fail | ...   |

## 9. Attack Scenarios

Опиши реальні сценарії атак, якщо вони можливі:

* гість намагається зайти в адмінку;
* гість намагається скачати original;
* attacker пробує upload PHP-shell;
* attacker пробує XSS у description/tag/album;
* attacker пробує SQLi через search/tag/filter;
* attacker пробує path traversal;
* attacker пробує CSRF;
* attacker пробує brute force login.

Якщо сценарій заблокований — напиши, чому саме заблокований.

## 10. Recommended Security Fix Order

Дай порядок виправлень:

1. Critical.
2. High.
3. Medium.
4. Low.

Для кожного пункту:

* affected files;
* complexity S/M/L;
* verification steps.

## 11. Production Security Checklist

Окремий checklist перед деплоєм:

* `APP_ENV=production`;
* `APP_DEBUG=false`;
* HTTPS;
* HSTS;
* secure cookies;
* clean release ZIP;
* no `.git`;
* no `config/database.php`;
* no logs;
* no sessions;
* no uploaded photos in release;
* correct DocumentRoot;
* private `storage`;
* backups outside public;
* database user not root;
* strong admin password.

## 12. Final Verdict

Напиши:

* що вже добре;
* що небезпечно;
* що обов’язково виправити;
* що можна відкласти;
* чи можна вважати security стан нормальним;
* чи можна деплоїти в production.

Вимоги:

* Пиши українською.
* Не вигадуй проблеми.
* Не дублюй одну проблему багато разів.
* Прив’язуй проблеми до конкретних файлів і рядків.
* Якщо проблема залежить від конфігурації Apache/Nginx — прямо так і напиши.
* Якщо щось не перевірилось через середовище — прямо напиши.
* Звіт має бути практичним, щоб по ньому можна було виправляти security-проблеми крок за кроком.

Після завершення:

1. Створи або онови `SECURITY_AUDIT.md`.
2. У відповіді коротко напиши:

   * аудит завершено;
   * кількість Critical/High/Medium/Low;
   * 3–5 найважливіших security-висновків;
   * де лежить файл.
