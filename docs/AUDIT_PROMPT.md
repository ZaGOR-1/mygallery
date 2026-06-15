Ти — senior PHP architect, backend auditor, security reviewer і QA engineer. Твоє завдання — зробити повний технічний аудит PHP-проєкту MyGallery V6.0.1.

Проєкт — персональна фотогалерея на чистому PHP без Laravel/React/Composer. У ньому є публічна галерея, адмінка, upload JPEG, EXIF, альбоми, теги, приватні оригінали, share links, backup/restore tools, release builder, tests і security-hardening.

ВАЖЛИВО:

* Не виправляй код автоматично.
* Не видаляй файли.
* Не змінюй логіку проєкту.
* Єдина дозволена зміна — створити або оновити файл `FULL_PROJECT_AUDIT.md` у корені проєкту.
* Якщо `FULL_PROJECT_AUDIT.md` уже існує — повністю перезапиши його новим актуальним аудитом.
* Не копіюй у звіт реальні паролі, токени, cookie, session ID, CSRF-токени або приватні ключі. Якщо знайдеш секрет — вкажи тільки файл, рядок і тип проблеми, але не саме значення.
* Не пиши “усе ідеально”, якщо не перевірив конкретно. Навіть якщо Critical/High немає, окремо вкажи Low/Info/Production risks, якщо вони є.
* Розрізняй робочу папку проєкту і clean release ZIP. Наявність `.git`, `config/database.php`, фото, логів і сесій у робочій папці не завжди є багом, але в release ZIP це критично/High.

Поточна структура і функціонал, які треба врахувати:

## Основні папки

Перевір:

* `app/includes/`

  * `auth.php`
  * `auth_functions.php`
  * `csrf.php`
  * `db.php`
  * `file_functions.php`
  * `functions.php`
  * `photo_service.php`
  * `header.php`
  * `footer.php`

* `public/`

  * `index.php`
  * `gallery.php`
  * `photo.php`
  * `share.php`
  * `404.php`
  * `500.php`
  * `.htaccess`

* `public/admin/`

  * `index.php`
  * `login.php`
  * `logout.php`
  * `upload.php`
  * `edit.php`
  * `delete.php`
  * `albums.php`
  * `download.php`
  * `health.php`
  * `stats.php`
  * `share.php`

* `tools/`

  * `setup.php`
  * `self_check.php`
  * `build_release.php`
  * `backup.php`
  * `verify_backup.php`
  * `restore.php`
  * `cleanup_orphans.php`
  * `cleanup_runtime.php`
  * `migrate_legacy_originals.php`
  * `recover_trash.php`
  * `regenerate_images.php`
  * `backfill_sha256.php`
  * `lib/SimpleZipWriter.php`

* `database/`

  * `schema.sql`
  * `migrations/2026_06_12_add_albums.sql`
  * `migrations/2026_06_13_hardening.sql`
  * `migrations/2026_06_13_add_tags.sql`
  * `migrations/2026_06_15_add_original_sha256.sql`
  * `migrations/2026_06_15_add_session_version.sql`
  * `migrations/2026_06_15_create_share_links.sql`

* `tests/`

  * `run.php`
  * `bootstrap.php`
  * `unit/exif_test.php`
  * `unit/paths_test.php`
  * `unit/release_exclusions_test.php`
  * `unit/tags_test.php`

* документація:

  * `README.md`
  * `AGENTS.md`
  * `CHANGELOG.md`
  * `ROADMAP.md`
  * `IMPLEMENTED_FEATURES.md`
  * `FIXES_APPLIED.md`
  * `AUDIT_REPORT.md`
  * `SECURITY_AUDIT.md`
  * `BACKUP_RESTORE.md`
  * `docs/AUDIT_PROMPT.md`
  * `docs/AUDIT_SECURITY_PROMT.md`

## Поточні фічі, які треба перевірити

Перевір повністю:

* JPEG upload;
* EXIF parsing;
* EXIF orientation 1–8;
* private originals у `storage/originals`;
* `large` і `thumbnail` версії фото;
* duplicate detection через `original_sha256`;
* альбоми;
* теги;
* пошук;
* FULLTEXT search + fallback на `LIKE`;
* фільтри за альбомом, тегом, камерою, датою;
* photo detail page;
* prev/next navigation на `photo.php`;
* admin login/logout;
* session timeout;
* admin session version invalidation;
* CSRF;
* login rate limiter;
* admin stats;
* admin health-check;
* admin download original;
* public/private share links через `share_links`;
* `public/share.php`;
* `public/admin/share.php`;
* backup;
* verify backup;
* restore;
* build release;
* cleanup runtime;
* cleanup orphans;
* recover trash;
* regenerate images;
* backfill sha256;
* self check;
* tests.

## Обов’язково перевір release/build workflow

У робочій папці можуть бути:

* `.git/`;
* `config/database.php`;
* фото;
* logs;
* sessions;
* backups;
* `dist/`.

Це нормально для development, але треба перевірити, що `tools/build_release.php` створює чистий ZIP і в clean release НЕ потрапляють:

* `.git`;
* `.env`;
* `config/database.php`;
* `storage/logs/*.log`;
* `storage/sessions/sess_*`;
* `storage/test_sessions/sess_*`;
* `storage/originals/*.jpg`;
* `public/uploads/large/*.jpg`;
* `public/uploads/thumbnails/*.jpg`;
* `backups/*.zip`;
* `dist/*.zip`;
* `*.bak`;
* `*.tmp`;
* приватні архіви;
* runtime-файли.

## 1. Архітектурний аудит

Перевір:

* чи структура `app/public/config/storage/tools/database/tests/docs` логічна;
* чи не занадто великий `functions.php`;
* чи правильно винесена логіка в `auth_functions.php`, `db.php`, `file_functions.php`, `photo_service.php`;
* чи немає змішування HTML, SQL і бізнес-логіки там, де це вже варто винести;
* чи не дублюється логіка між `gallery.php`, `admin/index.php`, `share.php`;
* чи не треба краще розділити public/admin/share/download/backup/restore;
* чи не з’явилися “напівфреймворкові” складні рішення, які погіршують студентську простоту проєкту;
* чи документація відповідає фактичній архітектурі.

## 2. Security audit

Перевір:

* SQL injection;
* XSS: stored, reflected, DOM-based;
* CSRF;
* path traversal;
* IDOR/BOLA;
* auth bypass;
* session fixation;
* session hijacking;
* weak cookie settings;
* username enumeration;
* timing attacks;
* brute force;
* upload PHP/web-shell;
* double extensions;
* MIME spoofing;
* EXIF risks;
* direct access до private originals;
* access до `storage`, `config`, `database`, `.git`, backups;
* безпеку `admin/download.php`;
* безпеку `share.php` і токенів;
* чи share links не дають доступу до чужих фото/альбомів;
* чи expired share links реально блокуються;
* чи `admin/share.php` має CSRF і auth;
* чи backup ZIP не може бути створений у `public/`;
* чи restore не дає path traversal через ZIP entries;
* чи `tools/build_release.php` не пропускає приватні файли;
* чи `APP_DEBUG=false` у production;
* HTTPS/HSTS;
* security headers;
* cache headers для admin;
* залежність від `.htaccess`, особливо якщо деплой на Nginx.

## 3. Code quality audit

Перевір:

* дублювання коду;
* довгі функції;
* складні SQL-builder-и;
* неймінг;
* типізацію;
* обробку винятків;
* `try/catch`;
* місця з `@unlink`, `@rename`, `@copy`;
* чи не приховуються важливі помилки;
* чи всі `PDOException` обробляються нормально;
* чи немає неправильних `return`, dead code, дубльованих умов;
* чи не порушені `declare(strict_types=1)`;
* чи немає змішаних стилів коду.

## 4. Database audit

Перевір:

* `schema.sql`;
* усі міграції;
* відповідність PHP-коду схемі;
* `photos`;
* `albums`;
* `admins`;
* `login_attempts`;
* `tags`;
* `photo_tags`;
* `share_links`;
* `original_sha256`;
* `session_version`;
* foreign keys;
* indexes;
* unique constraints;
* nullable поля;
* FULLTEXT indexes;
* idempotency міграцій;
* чи всі міграції можна безпечно запускати повторно;
* чи `2026_06_15_add_original_sha256.sql`, `2026_06_15_add_session_version.sql`, `2026_06_15_create_share_links.sql` не впадуть при повторному запуску;
* чи `schema.sql` і migrations не роз’їхалися;
* чи немає конфліктів MySQL/MariaDB;
* charset/collation/UTF-8.

## 5. Bug / potential bug audit

Перевір edge cases:

* порожня галерея;
* фото без EXIF;
* фото без опису;
* фото без альбому;
* фото без тегів;
* неіснуючий `photo_id`;
* неіснуючий `album_id`;
* неіснуючий `tag`;
* expired share link;
* share link на видалене фото;
* share link на видалений альбом;
* дубль фото через `original_sha256`;
* повторний upload того самого JPEG;
* дуже великий JPEG;
* пошкоджений JPEG;
* JPEG із неправильним MIME;
* `photo.jpg.php`;
* українські назви, теги й описи;
* дефіси в пошуку;
* короткі слова в FULLTEXT;
* pagination при фільтрах;
* prev/next при активних фільтрах;
* delete photo + tags + share links;
* delete album;
* rename album;
* orphan files;
* trash recovery;
* regenerate images, якщо оригінал відсутній;
* backup/restore на Windows;
* build release на Windows;
* restore ZIP із небезпечними шляхами;
* права на директорії.

## 6. Tools audit

Окремо перевір:

* `tools/setup.php`

  * створення адміна;
  * пароль через env/stdin;
  * session_version;

* `tools/self_check.php`

  * чи перевіряє всі потрібні PHP extensions;
  * чи перевіряє БД;
  * чи перевіряє таблиці/колонки;
  * чи перевіряє writable directories;

* `tools/build_release.php`

  * clean ZIP;
  * exclusions;
  * nested zip/backups;
  * dist/backups/storage/public uploads;
  * Windows paths;

* `tools/backup.php`

  * чи backup не в public;
  * чи не світить секрети;
  * чи manifest коректний;

* `tools/verify_backup.php`

  * чи реально перевіряє manifest і файли;

* `tools/restore.php`

  * чи безпечний restore;
  * path traversal;
  * overwrite behavior;
  * confirmation;
  * DB import;

* `tools/cleanup_orphans.php`;

* `tools/cleanup_runtime.php`;

* `tools/migrate_legacy_originals.php`;

* `tools/recover_trash.php`;

* `tools/regenerate_images.php`;

* `tools/backfill_sha256.php`.

## 7. Tests audit

Перевір:

* `tests/run.php`;
* `tests/bootstrap.php`;
* `tests/unit/exif_test.php`;
* `tests/unit/paths_test.php`;
* `tests/unit/release_exclusions_test.php`;
* `tests/unit/tags_test.php`.

Оціни:

* чи достатньо тестів;
* що вони реально покривають;
* що треба додати;
* чи тести не залежать від реальної БД без потреби;
* чи можна запускати їх на Windows/WampServer;
* які regression tests треба додати для share links, duplicate detection, backup/restore, build release.

## 8. Documentation audit

Перевір:

* `README.md`;
* `AGENTS.md`;
* `CHANGELOG.md`;
* `ROADMAP.md`;
* `IMPLEMENTED_FEATURES.md`;
* `FIXES_APPLIED.md`;
* `AUDIT_REPORT.md`;
* `SECURITY_AUDIT.md`;
* `BACKUP_RESTORE.md`;
* `docs/AUDIT_PROMPT.md`;
* `docs/AUDIT_SECURITY_PROMT.md`.

Особливо перевір:

* `docs/AUDIT_PROMPT.md` зараз може бути порожнім — чи треба його заповнити;
* `docs/AUDIT_SECURITY_PROMT.md` має помилку в назві `PROMT`, чи варто перейменувати в `AUDIT_SECURITY_PROMPT.md`;
* чи `SECURITY_AUDIT.md` не занадто оптимістичний і не вводить в оману фразами типу “10/10, production ready”;
* чи `AUDIT_REPORT.md` не посилається на неіснуючі або локальні `file:///C:/...` шляхи;
* чи `ROADMAP.md` не містить уже реалізовані задачі;
* чи README відповідає реальній структурі і поточним tools;
* чи інструкції для WampServer/PowerShell/MySQL правильні.

## 9. Автоматичні перевірки

Запусти все, що можливо в середовищі:

```bash
php -l
node --check public/assets/js/main.js
php tests/run.php
php tools/self_check.php
php tools/build_release.php
unzip -t dist/mygallery_6.0.1_release.zip
```

Для `php -l` перевір усі `.php` файли.

Для release ZIP перевір, що всередині немає:

```text
.git
.env
config/database.php
*.log
sess_*
*.jpg
*.jpeg
*.zip
*.bak
*.tmp
backups/
dist/
storage/originals/
public/uploads/large/*.jpg
public/uploads/thumbnails/*.jpg
```

Якщо якась команда не запускається через відсутність `pdo_mysql`, `gd`, `mbstring`, `mysql`, `unzip`, Node.js або БД — прямо напиши це в аудиті. Не вигадуй результат.

## 10. Формат файлу `FULL_PROJECT_AUDIT.md`

Створи або онови `FULL_PROJECT_AUDIT.md` у корені проєкту з такою структурою:

# Full Project Audit

## 1. Executive Summary

Вкажи:

* версію проєкту;
* чи це clean release чи робоча папка;
* чи можна запускати локально;
* чи готовий до production;
* головні ризики;
* загальну оцінку стану;
* чесний висновок без перебільшень.

## 2. Audit Scope

Вкажи:

* кількість PHP-файлів;
* кількість SQL-файлів;
* кількість JS-файлів;
* кількість Markdown-файлів;
* які папки перевірені;
* які команди запускались;
* які команди не вдалося запустити і чому.

## 3. Severity Summary

Таблиця:

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
* status: `open`;
* файл і рядок;
* опис;
* impact;
* як проявиться;
* як відтворити;
* конкретне виправлення;
* як перевірити після виправлення.

## 5. High Issues

Той самий формат.

## 6. Medium Issues

Той самий формат.

## 7. Low Issues

Той самий формат.

## 8. Informational / Improvements

Список корисних покращень, які не є багами.

## 9. Architecture Review

Оціни:

* структуру;
* розділення відповідальностей;
* складність;
* підтримуваність;
* чи проект не став занадто складним.

## 10. Security Review

Оціни:

* SQLi;
* XSS;
* CSRF;
* auth;
* sessions;
* uploads;
* file access;
* share links;
* backups;
* release ZIP;
* production config.

## 11. Code Quality Review

Оціни:

* дублювання;
* функції;
* SQL;
* HTML/PHP змішування;
* error handling;
* maintainability.

## 12. Database Review

Оціни:

* schema;
* migrations;
* idempotency;
* indexes;
* constraints;
* tags;
* share_links;
* original_sha256;
* session_version.

## 13. Tools Review

Оціни всі CLI tools:

* setup;
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

Оціни всі `.md` файли і конкретно напиши:

* що актуальне;
* що застаріле;
* що порожнє;
* що перейменувати;
* що видалити;
* що додати.

## 16. Recommended Fix Order

Дай порядок виправлень:

1. Critical.
2. High.
3. Medium.
4. Low.
5. Info/nice-to-have.

Для кожного пункту:

* priority;
* complexity: S/M/L;
* affected files;
* verification steps.

## 17. Regression Test Checklist

Дай чекліст ручного тестування:

* login/logout;
* invalid login;
* rate limiter;
* upload JPEG;
* upload duplicate JPEG;
* upload invalid file;
* upload large JPEG;
* EXIF orientation;
* albums;
* tags;
* search;
* filters;
* pagination;
* photo page;
* prev/next;
* edit photo;
* delete photo;
* recover trash;
* cleanup orphans;
* regenerate images;
* download original;
* admin stats;
* admin health;
* create photo share link;
* create album share link;
* revoke share link;
* expired share link;
* public share page;
* backup;
* verify backup;
* restore backup;
* build release;
* self check;
* 404 page;
* 500 page;
* unauthorized admin access;
* CSRF failure;
* direct private file access.

## 18. Final Verdict

Напиши:

* що вже добре;
* що обов’язково виправити;
* що бажано виправити;
* що можна відкласти;
* чи готовий проєкт для локального використання;
* чи готовий до production;
* чи можна цю версію вважати stable.

Вимоги до якості аудиту:

* Пиши українською мовою.
* Не вигадуй проблеми.
* Не дублюй одну проблему багато разів.
* Прив’язуй кожну реальну проблему до конкретного файла й бажано рядка.
* Якщо щось залежить від конфігурації Apache/Nginx/WampServer — прямо так і напиши.
* Якщо щось не перевірилось через середовище — прямо так і напиши.
* Не називай проєкт “ідеальним” або “100% безпечним”.
* Аудит має бути практичним, щоб по ньому можна було виправляти проєкт крок за кроком.

Після завершення:

1. Створи або онови `FULL_PROJECT_AUDIT.md`.
2. У відповіді коротко напиши:

   * аудит завершено;
   * скільки знайдено Critical/High/Medium/Low/Info;
   * які 3–5 найважливіших висновків;
   * де лежить файл аудиту.
