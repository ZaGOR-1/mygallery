# Prompt: Full Technical, Security and Production Audit for MyGallery

Ти — senior PHP architect, backend auditor, application security engineer, QA engineer і production readiness reviewer. Проведи повний аудит PHP-проєкту **MyGallery**.

MyGallery — персональна фотогалерея на чистому PHP без Laravel, Symfony, Composer, React, Vue, Bootstrap або jQuery. Проєкт має публічну галерею, адмінку, JPEG upload, EXIF, альбоми, приватні альбоми, теги, share links, download album ZIP, private originals, WebP/AVIF derivatives, backup/restore tools, release builder, health/self-check, tests і production/security hardening.

## Головні правила

* Не виправляй код автоматично.
* Не видаляй файли.
* Не змінюй структуру проєкту.
* Єдина дозволена зміна — створити або оновити файл `FULL_PROJECT_AUDIT.md` у корені проєкту.
* Якщо `FULL_PROJECT_AUDIT.md` уже існує — повністю перезапиши його новим актуальним аудитом.
* Не копіюй у звіт реальні паролі, токени, cookie, session ID, CSRF-токени, приватні ключі або вміст `config/database.php`.
* Якщо знайдеш секрет — вкажи тільки файл, рядок і тип проблеми, але не саме значення.
* Не називай проєкт “ідеальним”, “100% безпечним” або “production-ready”, якщо це не підтверджено перевірками.
* Не вигадуй проблеми. Якщо проблема не підтверджується кодом — прямо напиши, що вона не підтвердилась.
* Якщо бачиш захист — вкажи конкретний файл/функцію, де він реалізований.
* Розрізняй робочу папку і clean release ZIP. Наявність `.git`, фото, логів, сесій і `config/database.php` у робочій папці не завжди є багом, але в release ZIP це серйозна проблема.
* Пиши українською мовою.

---

# 1. Спочатку визнач фактичний стан проєкту

Перед аудитом визнач:

* фактичну версію з `VERSION`;
* чи це робоча папка, чи clean release;
* які PHP-файли реально існують;
* які SQL-файли реально існують;
* які JS/CSS-файли реально існують;
* які Markdown-документи реально існують;
* які tools реально існують;
* чи є `tools/build_release.php`;
* чи є `tools/backup.php`, `tools/restore.php`, `tools/verify_backup.php`;
* чи є `tests/`;
* чи є `.github/workflows/`;
* чи є `AGENTS.md`, `CLAUDE.md`, `GEMINI.md`;
* чи документація відповідає фактичній версії.

Окремо знайди застарілі згадки старих версій типу:

* `6.4.6`;
* `6.4.8`;
* `6.1.0`;
* `6.0.1`;
* старі назви roadmap/audit-файлів;
* неіснуючі файли;
* функції, які вже видалені або навмисно прибрані.

Якщо документація описує функції, яких фактично немає в коді, познач це як `documentation mismatch`.

---

# 2. Обов’язково перевір ці файли

## Публічні сторінки

* `public/index.php`
* `public/albums.php`
* `public/gallery.php`
* `public/photo.php`
* `public/share.php`
* `public/download_album.php`
* `public/404.php`
* `public/500.php`

## Адмінка

* `public/admin/login.php`
* `public/admin/logout.php`
* `public/admin/index.php`
* `public/admin/upload.php`
* `public/admin/edit.php`
* `public/admin/delete.php`
* `public/admin/bulk_edit.php`
* `public/admin/albums.php`
* `public/admin/tags.php`
* `public/admin/share.php`
* `public/admin/download.php`
* `public/admin/trash.php`
* `public/admin/health.php`
* `public/admin/stats.php`

## Core includes

* `app/includes/functions.php`
* `app/includes/file_functions.php`
* `app/includes/photo_service.php`
* `app/includes/auth.php`
* `app/includes/auth_functions.php`
* `app/includes/csrf.php`
* `app/includes/db.php`
* `app/includes/header.php`
* `app/includes/footer.php`

## Database / tools

* `database/schema.sql`
* `database/migrations/*.sql`
* `tools/setup.php`
* `tools/migrate.php`
* `tools/self_check.php`
* `tools/build_release.php`
* `tools/backup.php`
* `tools/verify_backup.php`
* `tools/restore.php`
* `tools/cleanup_orphans.php`
* `tools/cleanup_runtime.php`
* `tools/migrate_legacy_originals.php`
* `tools/recover_trash.php`
* `tools/regenerate_images.php`
* `tools/backfill_sha256.php`
* `tools/lib/SimpleZipWriter.php`

## Frontend / web server

* `public/assets/js/main.js`
* `public/assets/css/style.css`
* `public/.htaccess`
* `public/uploads/.htaccess`
* `public/uploads/originals/.htaccess`

## Documentation

* `README.md`
* `CHANGELOG.md`
* `ROADMAP.md`
* `AGENTS.md`
* `CLAUDE.md`
* `GEMINI.md`
* `docs/*.md`
* `.gemini/agents/*.md`
* `.github/workflows/*.yml`

---

# 3. Архітектурний аудит

Перевір:

* чи структура `app/public/config/storage/database/tools/tests/docs` логічна;
* чи `public/` є єдиною web-доступною папкою;
* чи `app/`, `config/`, `storage/`, `database/`, `tools/`, `backups/` не мають бути доступні напряму з браузера;
* чи `functions.php` не став занадто великим;
* чи не дублюється логіка між `gallery.php`, `albums.php`, `photo.php`, `share.php`, `admin/index.php`;
* чи логіка upload/image/database/auth/tag/share винесена нормально;
* чи не змішані надмірно HTML, SQL і бізнес-логіка;
* чи проєкт не став занадто складним для plain PHP;
* чи не з’явилися напівфреймворкові рішення, які погіршують підтримку;
* чи не треба додатково винести privacy-фільтри, share-access logic, ZIP-generation logic, backup/restore validation у окремі функції/сервіси.

---

# 4. Security audit

Перевір:

* SQL injection;
* XSS: stored, reflected, DOM-based;
* CSRF;
* auth bypass;
* IDOR/BOLA;
* path traversal;
* session fixation;
* session hijacking;
* weak cookie settings;
* brute force login;
* username enumeration;
* timing attacks;
* upload PHP/web-shell;
* double extensions типу `photo.jpg.php`;
* MIME spoofing;
* прямий доступ до private originals;
* прямий доступ до `config`, `database`, `storage`, `.git`, backups;
* чи `public/uploads` не дозволяє виконання PHP;
* чи `APP_DEBUG=false` реально потрібний у production;
* чи SQL/PHP помилки не показуються користувачу;
* чи security headers працюють;
* чи CSP не ламає UI;
* чи HSTS не вмикається на localhost.

Окремо перевір:

* `is_admin_logged_in()`;
* `require_admin()`;
* `login_admin()`;
* `logout_admin()`;
* session idle timeout;
* `session_version` invalidation;
* `session_regenerate_id(true)` після login;
* cookie flags: `Secure`, `HttpOnly`, `SameSite`;
* no-store headers для адмінки;
* dummy password hash проти timing enumeration;
* login rate limiter.

---

# 5. Privacy audit: приватні альбоми і публічні витоки

Це критично важлива частина. Перевір не тільки “чи є is_private”, а всю модель приватності.

Перевір:

* чи всі public-запити виключають `albums.is_private = 1`;
* чи `public/index.php` не показує приватні фото в hero/latest/stats;
* чи public homepage не рахує приватні фото/альбоми/камери в статистиці;
* чи `public/albums.php` не показує приватні альбоми;
* чи `public/gallery.php` не показує фото з приватних альбомів;
* чи `public/photo.php` не відкриває фото з приватного альбому без admin/share access;
* чи public filters не розкривають приватні tags/camera models/counts;
* чи `fetch_filter_options()` не показує теги/камери тільки з приватних фото;
* чи `get_tag_options()` для public mode робить join з `photos` + `albums`;
* чи private album cover не світиться публічно;
* чи search/filter/sort/pagination не обходять privacy filter;
* чи share links можуть відкривати приватні фото/альбоми тільки коли це явно дозволено;
* чи revoked/expired share links реально блокують доступ.

Окремо перевір проблему static derivatives:

* `storage/originals` приватний — це добре;
* але `public/uploads/large` і `public/uploads/thumbnails` є статичними URL;
* якщо optimized image приватного фото лежить у public, то після витоку URL його не можна відкликати PHP-авторизацією;
* перевір, чи це прийнятна модель “unguessable URL”, чи треба protected image controller;
* якщо приватність має бути реально відкличною, запропонуй варіанти:

  * зберігати derivatives приватних альбомів поза `public`;
  * віддавати приватні derivatives через PHP controller із перевіркою admin/share token;
  * використовувати `X-Accel-Redirect` / `X-Sendfile` після перевірки доступу;
  * регенерувати filenames при зміні альбому з public на private.

---

# 6. CSRF audit

Перевір:

* усі admin POST routes мають `require_csrf()`;
* login/logout;
* upload;
* edit;
* delete;
* bulk edit;
* bulk delete;
* albums create/update/delete;
* tags create/update/delete;
* share create/revoke/delete;
* trash restore/purge;
* admin download, якщо це state-changing дія;
* чи CSRF token не приймає порожнє значення;
* чи використовується `hash_equals`;
* чи multiple CSRF tokens у `csrf.php` не створюють bypass;
* чи token rotation після login не ламає форми;
* чи POST без CSRF відхиляється.

---

# 7. SQL injection audit

Перевір усі GET/POST inputs.

Особливо:

* dynamic `IN (...)` у `bulk_edit.php`;
* `get_photo_tags_map()`;
* dynamic sort у `normalize_gallery_filters()`;
* search у `fetch_photos()`;
* FULLTEXT boolean search;
* fallback LIKE;
* filters: album/tag/camera/date/sort/page;
* `information_schema` query у `fulltext_index_exists()`;
* share token queries;
* download album queries;
* backup/restore SQL operations;
* `tools/migrate.php`.

Перевір, що:

* використовуються prepared statements;
* dynamic sort має whitelist;
* LIMIT/OFFSET приведені до int;
* token/ID/page перевіряються до запиту;
* не склеюються сирі `$_GET`, `$_POST`, `$_COOKIE`.

---

# 8. XSS / CSP / frontend audit

Перевір:

* stored XSS у title, description, album name, tag name, EXIF, original filename;
* reflected XSS через query parameters;
* DOM-based XSS у `main.js`;
* output escaping через `htmlspecialchars(..., ENT_QUOTES)`;
* escaping для HTML attributes: `href`, `src`, `alt`, `title`, `value`;
* URL escaping;
* чи tag/album/photo title не можуть вставити JS;
* чи `main.js` не використовує небезпечний `innerHTML` з user-controlled input;
* чи CSP `script-src 'self'` не блокує потрібний UI;
* чи немає inline `onclick`, `onerror`, inline styles, які блокує CSP;
* чи confirmation flows працюють під CSP;
* чи всі inline handlers перенесені в `main.js` через `addEventListener`.

---

# 9. Upload/media audit

Перевір:

* `create_photo_from_upload()`;
* JPEG-only policy;
* `finfo_file()`;
* `getimagesize()`;
* `is_uploaded_file()`;
* extension validation;
* random filenames;
* original filename тільки в БД;
* max file size;
* max dimensions;
* pixel count;
* memory estimate;
* GD failure handling;
* `imagecreatetruecolor()`;
* `imagecopyresampled()`;
* `imageflip()`;
* `imagerotate()`;
* EXIF parsing;
* EXIF orientation 1–8;
* duplicate detection by `original_sha256`;
* cleanup after failed upload;
* cleanup of partially created files;
* original byte-for-byte file у `storage/originals`;
* large/thumbnail/WebP/AVIF у public derivatives;
* dominant color extraction;
* regenerate images;
* orphan cleanup;
* що буде з пошкодженим JPEG;
* що буде з дуже великим JPEG;
* що буде з `photo.jpg.php`;
* що буде з fake JPEG.

---

# 10. File access / path traversal audit

Перевір:

* `safe_existing_upload_file_path()`;
* `safe_existing_storage_file_path()`;
* `photo_file_paths()`;
* `move_photo_files_to_trash()`;
* `restore_photo_from_trash()`;
* `purge_photo_from_trash()`;
* `public/admin/download.php`;
* `public/download_album.php`;
* `tools/backup.php`;
* `tools/restore.php`;
* `tools/recover_trash.php`;
* `tools/cleanup_orphans.php`;
* `tools/regenerate_images.php`.

Перевір:

* `realpath`;
* allowed directories;
* `../` traversal;
* symlink risks;
* Windows/Linux path separator;
* case sensitivity on Linux;
* whether file deletion cannot escape allowed directories;
* whether ZIP restore prevents Zip Slip;
* whether backup/restore cannot write into `public` unexpectedly.

---

# 11. Share links audit

Перевір:

* token generation entropy;
* token format validation;
* token length;
* token DB indexes;
* expiration;
* revocation;
* photo share;
* album share;
* private album share;
* share links for deleted photos/albums;
* share links for photos moved between public/private albums;
* share access to optimized vs original files;
* whether public share page exposes originals;
* rate limiting у `public/share.php`;
* whether share rate limit uses same trusted proxy IP helper as login;
* whether invalid token is rejected before DB query;
* whether token guessing is impractical;
* whether share pages are noindex/noarchive.

---

# 12. ZIP download / album download audit

Перевір `public/download_album.php`.

Особливо:

* access control для public/private/share/admin;
* чи public/share ZIP отримує optimized files;
* чи admin ZIP може отримати originals тільки після auth;
* чи private album ZIP недоступний без admin/share;
* limits: max photos, max total size;
* cooldown/rate limit;
* ZIP filename safety;
* ZIP entry filename safety;
* cache key correctness;
* чи cache key враховує:

  * album id;
  * access variant;
  * filenames;
  * `original_sha256`;
  * file size;
  * `updated_at`;
  * `created_at`;
  * count;
* чи stale ZIP неможливий після зміни файлів;
* race conditions during ZIP generation;
* locks;
* cleanup old ZIP cache;
* whether generated ZIP can leak private content after revoke/private change.

---

# 13. Backup/restore audit

Перевір:

* `tools/backup.php`;
* `tools/verify_backup.php`;
* `tools/restore.php`;
* `BACKUP_RESTORE.md`.

Особливо:

* backup output path cannot be inside `public`;
* backup не включає `config/database.php` без явного `--include-config`;
* backup не включає secrets випадково;
* backup manifest коректний;
* backup включає DB і media;
* backup включає або коректно обробляє `schema_migrations`;
* `database/schema.sql` створює `schema_migrations`, якщо backup її очікує;
* restore prevents Zip Slip;
* restore validates allowed ZIP entries;
* restore validates manifest before changing DB;
* restore SQL execution risks;
* restore transaction/rollback;
* restore overwrite behavior;
* backup/restore на Windows і Linux;
* docs попереджають, що backup містить приватні оригінали.

---

# 14. Database and migrations audit

Перевір:

* `database/schema.sql`;
* усі `database/migrations/*.sql`;
* clean install з нуля;
* repeated migration runs;
* `schema_migrations`;
* `tools/migrate.php`;
* backup migration state;
* idempotency;
* portable SQL без `USE my_photo_gallery`;
* foreign keys;
* indexes;
* FULLTEXT;
* unique constraints;
* nullable fields;
* charset/collation `utf8mb4`;
* MySQL/MariaDB compatibility;
* таблиці:

  * `admins`;
  * `albums`;
  * `photos`;
  * `login_attempts`;
  * `tags`;
  * `photo_tags`;
  * `share_links`;
  * `schema_migrations`;
* колонки:

  * `original_sha256`;
  * `session_version`;
  * `is_private`;
  * `cover_photo_id`;
  * `sort_order`;
  * `dominant_color`.

Перевір, чи `schema.sql` і migrations не роз’їхалися.

---

# 15. Production readiness audit

Перевір:

* `APP_ENV=production`;
* `APP_DEBUG=false`;
* `APP_URL=https://...`;
* HTTPS requirement;
* HSTS only in HTTPS production;
* DB user не root/no-password у production;
* DocumentRoot має бути `public`;
* Apache `.htaccess`;
* Nginx окремі правила, бо `.htaccess` не працює;
* `storage/`, `config/`, `database/`, `tools/`, `backups/` поза web access;
* `public/uploads` blocks PHP execution;
* directory listing disabled;
* logs not public;
* backups not public;
* writable directories documented;
* log rotation;
* cron/cleanup recommendations;
* strong admin password;
* clean release ZIP only for deploy;
* no dev/debug/temp files in production;
* error pages `404.php` and `500.php`;
* no stack traces to users.

---

# 16. Release hygiene audit

Запусти:

```bash
php tools/build_release.php
```

Перевір створений ZIP через:

```bash
unzip -t dist/*.zip
```

або аналог на Windows.

У release ZIP не повинно бути:

* `.git/`;
* `.env`;
* `config/database.php`;
* `*.log`;
* `sess_*`;
* uploaded media;
* `*.jpg`;
* `*.jpeg`;
* `*.webp`;
* `*.avif`;
* `backups/`;
* `dist/`;
* nested `*.zip`;
* `*.bak`;
* `*.tmp`;
* `temp_*.php`;
* runtime files;
* share rate limit files;
* download locks/cache;
* private originals;
* local Windows paths;
* SQL dump із реальними даними.

Окремо перевір:

* `.gitignore`;
* `tools/build_release.php`;
* whether `storage/share_ratelimit` is excluded;
* whether `storage/download_locks` is excluded;
* whether ZIP cache and backups are excluded;
* whether `.gitkeep` files remain where needed.

---

# 17. Tests audit

Перевір:

* `tests/run.php`;
* всі `tests/unit/*.php`;
* чи тести запускаються без реальної БД, якщо це unit-тести;
* чи є тести для:

  * EXIF orientation;
  * paths;
  * release exclusions;
  * tags;
  * privacy filtering;
  * private homepage leakage;
  * public filter options;
  * share links;
  * ZIP cache key;
  * backup/restore path validation;
  * migration idempotency;
  * CSP/no inline handlers;
  * upload validation;
  * duplicate detection;
  * bulk edit tags.

Запропонуй конкретні нові regression tests.

---

# 18. Documentation audit

Перевір:

* `README.md`;
* `CHANGELOG.md`;
* `ROADMAP.md`;
* `AGENTS.md`;
* `CLAUDE.md`;
* `GEMINI.md`;
* `docs/*.md`;
* `.gemini/agents/*.md`;
* `.github/workflows/*.yml`.

Оціни:

* чи всі версії актуальні;
* чи README відповідає реальній структурі;
* чи AGENTS/CLAUDE/GEMINI не суперечать одне одному;
* чи roadmap не містить уже реалізовані або видалені задачі;
* чи production/security docs не перебільшують готовність;
* чи AI prompts не змушують агента вигадувати проблеми;
* чи документація пояснює WampServer/PowerShell/MySQL нюанси;
* чи docs пояснюють clean release workflow.

---

# 19. Автоматичні перевірки

Запусти, якщо середовище дозволяє:

```bash
php -l
node --check public/assets/js/main.js
php tests/run.php
php tools/self_check.php
php tools/build_release.php
unzip -t dist/*.zip
```

Для `php -l` перевір усі `.php` файли.

Якщо команда не запускається через відсутність PHP, extensions, MySQL, Node.js або unzip — прямо напиши це у звіті. Не вигадуй результат.

---

# 20. Формат `FULL_PROJECT_AUDIT.md`

Створи або онови `FULL_PROJECT_AUDIT.md` у корені проєкту.

Оформи так:

# Full Project Audit

## 1. Executive Summary

Напиши 5–10 речень:

* фактична версія;
* чи це working tree чи clean release;
* чи можна запускати локально;
* чи готовий до production;
* головні ризики;
* загальна оцінка стану.

## 2. Audit Scope

Вкажи:

* кількість PHP-файлів;
* кількість SQL-файлів;
* кількість JS-файлів;
* кількість CSS-файлів;
* кількість Markdown-файлів;
* які папки перевірені;
* які команди запускались;
* що не вдалося перевірити.

## 3. Severity Summary

| Severity | Count |
| -------- | ----: |
| Critical |   ... |
| High     |   ... |
| Medium   |   ... |
| Low      |   ... |
| Info     |   ... |

## 4. Critical Issues

Якщо немає — напиши `Critical issues not found`.

Для кожної проблеми:

* ID;
* severity;
* status: open;
* file:line;
* description;
* impact;
* reproduction scenario;
* fix;
* tests to add;
* verification steps.

## 5. High Issues

Такий самий формат.

## 6. Medium Issues

Такий самий формат.

## 7. Low Issues

Такий самий формат.

## 8. Informational / Improvements

Корисні покращення, які не є багами.

## 9. Security Review

Окремо оціни:

* SQL injection;
* XSS/CSP;
* CSRF;
* auth;
* sessions;
* uploads;
* file access;
* privacy;
* share links;
* download album ZIP;
* backup/restore;
* release ZIP;
* production config.

## 10. Architecture Review

Оціни:

* структуру;
* підтримуваність;
* складність;
* дублювання;
* розділення відповідальностей;
* чи проєкт не став занадто складним.

## 11. Privacy Review

Окремо оціни:

* private albums;
* homepage leakage;
* gallery leakage;
* filter options leakage;
* direct static derivative URLs;
* share access;
* ZIP download access.

## 12. Database Review

Оціни:

* schema;
* migrations;
* idempotency;
* indexes;
* constraints;
* schema_migrations;
* FULLTEXT;
* MySQL/MariaDB compatibility.

## 13. Tools Review

Оціни:

* setup;
* migrate;
* self_check;
* build_release;
* backup;
* verify_backup;
* restore;
* cleanup;
* regenerate;
* recover;
* backfill.

## 14. Tests Review

Оціни наявні тести й дай список тестів, які треба додати.

## 15. Documentation Review

Оціни всі Markdown-документи й AI instruction файли.

## 16. Positive Security Decisions

Окремо напиши, що в проєкті зроблено добре:

* prepared statements;
* CSRF;
* session hardening;
* upload validation;
* private originals;
* release builder;
* production guards;
* backup/restore validation;
* інші сильні рішення.

## 17. Recommended Fix Order

Дай fix plan на 1–2 дні.

Для кожного пункту:

* priority;
* complexity: S/M/L;
* affected files;
* verification steps.

## 18. Regression Test Checklist

Дай чекліст ручного тестування:

* login/logout;
* invalid login;
* rate limiter;
* upload valid JPEG;
* upload invalid file;
* upload large JPEG;
* duplicate upload;
* EXIF orientation;
* albums;
* private albums;
* tags;
* bulk edit;
* search/filter/sort;
* pagination;
* homepage privacy;
* public filter privacy;
* photo page;
* prev/next;
* share link create/open/revoke/expire;
* download original;
* download album ZIP;
* trash;
* cleanup orphans;
* regenerate images;
* backup/verify/restore;
* build release;
* self_check;
* tests/run.php;
* 404/500;
* CSRF failure;
* unauthorized admin access;
* direct private file access.

## 19. Production Readiness Verdict

Дай один із варіантів:

* `Ready`;
* `Almost ready`;
* `Not ready`.

Поясни:

* що блокує production;
* що можна виправити після публікації;
* який ризик, якщо деплоїти зараз.

## 20. Final Verdict

Чітко напиши:

* що добре;
* що обов’язково виправити;
* що бажано виправити;
* що можна відкласти;
* чи можна вважати версію stable.

Після завершення:

1. Створи або онови `FULL_PROJECT_AUDIT.md`.
2. У відповіді коротко напиши:

   * аудит завершено;
   * кількість Critical/High/Medium/Low/Info;
   * 3–5 найважливіших висновків;
   * production readiness verdict;
   * де лежить файл аудиту.
