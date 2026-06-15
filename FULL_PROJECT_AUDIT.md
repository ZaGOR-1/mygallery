# Повний аудит проєкту My Photo Gallery

Дата аудиту: 2026-06-15  
Версія проєкту: 6.1.0  
Режим аудиту: read-only для коду; створено лише цей звіт  
Джерело правил аудиту: `docs/AUDIT_PROMPT.md`

## 1. Короткий висновок

Проєкт виглядає як достатньо зрілий MVP персональної фотогалереї: базова безпека, приватне зберігання оригіналів, CSRF, rate limiter, release tooling, backup tooling, теги, альбоми, responsive images і self-check уже реалізовані.

Критичних або high-проблем під час цього аудиту не знайдено. Medium issues, знайдені під час аудиту, виправлено: тимчасовий migration/debug script видалено, share links отримали строк дії, inline UI у вказаних файлах прибрано, документацію синхронізовано.

Підсумок:

| Рівень | Кількість |
|---|---:|
| Critical | 0 |
| High | 0 |
| Medium | 0 |
| Low | 6 |
| Info | 7 |

Примітка після виправлення: оригінальний опис medium issues збережено нижче як історія аудиту, але кожен пункт позначено як виправлений.

## 2. Що перевірено

Переглянуто:

- структуру репозиторію;
- `README.md`;
- `AGENTS.md`;
- `CHANGELOG.md`;
- `VERSION`;
- `docs/AUDIT_PROMPT.md`;
- `docs/AUDIT_REPORT.md`;
- `docs/SECURITY_AUDIT.md`;
- `docs/BUGS.md`;
- `ROADMAP.md`;
- `docs/IMPLEMENTED_FEATURES.md`;
- `docs/BACKUP_RESTORE.md`;
- `config/config.php`;
- `config/database.example.php`;
- `app/includes/*.php`;
- `public/*.php`;
- `public/admin/*.php`;
- `public/assets/css/style.css`;
- `public/assets/js/main.js`;
- `tools/*.php`;
- `tests/*.php`;
- SQL schema і migrations;
- release ZIP, створений через `tools/build_release.php`.

Автоматичні перевірки:

```text
PHP lint для всіх 54 PHP-файлів: OK
node --check public/assets/js/main.js: OK
php tests/run.php: 6 passed, 0 failed
php tools/self_check.php: passed
php tools/build_release.php: release ZIP created, dangerous file check OK
ZIP open/read test: OK, 105 entries
Release forbidden-pattern scan: OK
```

Примітка: PHP CLI під час перевірок виводив warning Xdebug про шлях до `c:/wamp64/logs/xdebug.log`. Це не помилка застосунку, але локальне PHP/Xdebug-середовище варто поправити окремо.

## 3. Обмеження аудиту

Не виконувалося:

- ручний login/logout у браузері;
- ручний upload валідного JPEG;
- ручна відмова для non-JPEG;
- ручна перевірка EXIF Orientation на реальних файлах;
- destructive restore з backup ZIP;
- production deploy на Linux/Apache;
- тестування з реальним HTTPS/HSTS.

Причина: `docs/AUDIT_PROMPT.md` просить аудит і звіт, а не зміну або запуск потенційно руйнівних сценаріїв.

## 4. Critical Issues

Critical-проблем не знайдено.

## 5. High Issues

High-проблем не знайдено.

## 6. Medium Issues

Усі medium issues з цього розділу виправлено 2026-06-15.

### MED-001. У корені репозиторію залишився тимчасовий migration/debug script

Статус: виправлено. `temp_migrate.php` видалено з кореня репозиторію.

Файл: `temp_migrate.php`

Ознаки:

- `ini_set('display_errors', '1')`;
- пряме читання SQL migration;
- прямий `$pdo->exec($sql)`;
- вивід exception message;
- debug-вивід результату з БД.

Ризик:

Якщо Apache помилково буде спрямований не на `public`, а на корінь проєкту, або якщо хтось випадково запустить файл вручну, цей скрипт може виконати зміну БД і показати технічні деталі. У release ZIP він не потрапляє, бо `temp_*.php` виключено, але в робочому дереві файл все одно небажаний.

Рекомендація:

- видалити `temp_migrate.php`, якщо він більше не потрібен;
- або перенести логіку в нормальний CLI-only tool з перевіркою `PHP_SAPI === 'cli'`;
- не залишати `display_errors=1` у тимчасових PHP-файлах.

Перевірка після виправлення:

```text
rg --files | rg "temp_.*\.php"
php tools/build_release.php
```

Очікувано: тимчасового файлу немає, release build проходить.

### MED-002. Share links створюються без строку дії

Статус: виправлено. Нові photo/album share links мають `expires_at`; дефолтний строк дії — 30 днів, безстроковий режим доступний лише як явний вибір.

Файл: `public/admin/share.php`

Ознаки:

- insert для photo share не задає `expires_at`;
- insert для album share не задає `expires_at`;
- у `database/schema.sql` поле `expires_at` уже існує.

Ризик:

Share URL є bearer-посиланням: хто має токен, той має доступ. Якщо строк дії не задано, посилання фактично живе безстроково до ручного revoke. Для приватної фотогалереї це слабке місце privacy-моделі.

Рекомендація:

- додати в UI вибір строку дії: 1 день, 7 днів, 30 днів, без строку;
- зробити дефолт, наприклад, 7 або 30 днів;
- у списку share links показувати `expires_at`;
- прострочені посилання показувати як неактивні;
- додати CLI cleanup для expired/revoked links, якщо потрібно.

Перевірка після виправлення:

```text
php -l public/admin/share.php
php tests/run.php
```

Додатково вручну: створити посилання з коротким строком, перевірити доступ до і після expiry.

### MED-003. Документація розсинхронена зі структурою і конфігурацією

Статус: виправлено. README, AGENTS, CHANGELOG, `docs/AUDIT_REPORT.md`, `docs/SECURITY_AUDIT.md` і `docs/IMPLEMENTED_FEATURES.md` оновлено під фактичні шляхи, PHP-версію, image limits і нову share expiry поведінку.

Файли:

- `README.md`;
- `AGENTS.md`;
- `docs/AUDIT_REPORT.md`;
- `docs/SECURITY_AUDIT.md`;
- `CHANGELOG.md`;
- `config/config.php`.

Приклади:

- `README.md` посилається на root `IMPLEMENTED_FEATURES.md` і `BACKUP_RESTORE.md`, але фактичні файли лежать у `docs/`;
- `AGENTS.md` теж описує root `IMPLEMENTED_FEATURES.md` і `BACKUP_RESTORE.md`;
- `AGENTS.md` просить PHP 8.4 і шлях `C:\wamp64\bin\php\php8.4.0\php.exe`, тоді як README допускає PHP 8.2+, а локальна перевірка йшла через PHP 8.3.14;
- README описує upload max 30 MB і large 2400 px, тоді як `config/config.php` містить 50 MB і 4000 px;
- `CHANGELOG.md` має дубльований заголовок `v6.1.0`;
- старі audit docs містять абсолютні `file:///C:/...` посилання і надто категоричні формулювання.

Ризик:

Новий розробник або майбутній AI-агент може виконувати неправильні інструкції, тестувати не тим PHP, шукати файли не там або описувати security-стан оптимістичніше, ніж він є.

Рекомендація:

- синхронізувати README, AGENTS, audit docs і changelog;
- залишити один canonical шлях для implemented features і backup docs;
- вирішити, яка мінімальна PHP-версія офіційна: 8.2, 8.3 чи 8.4;
- привести upload limits у документації до `config/config.php` або навпаки.

Перевірка після виправлення:

```text
rg "IMPLEMENTED_FEATURES|BACKUP_RESTORE|php8\.4\.0|30 МБ|2400|file:///C:|v6\.1\.0" README.md AGENTS.md CHANGELOG.md docs
```

### MED-004. На частині сторінок залишилися inline style/onclick, несумісні з поточним CSP-підходом

Статус: виправлено. Inline `style` / `onclick` прибрано з `public/admin/edit.php`, `public/admin/bulk_edit.php`, `public/gallery.php` і `public/share.php`; UI перенесено в CSS-класи та `data-confirm`.

Файли:

- `public/admin/edit.php`;
- `public/admin/bulk_edit.php`;
- `public/gallery.php`;
- `public/share.php`.

Ознаки:

- inline `style=...`;
- inline `onclick=...`;
- у `public/admin/edit.php` використовуються класи на кшталт `button-danger` / `button-secondary`, тоді як у CSS основний патерн виглядає як `.button.danger` / `.button.secondary`;
- якщо CSP забороняє inline styles/scripts, частина UI або confirm-поведінки може не працювати.

Ризик:

Візуальні зсуви, неконсистентний UI, зламані confirm-діалоги, неможливість підтримувати один CSP-профіль для всього застосунку.

Рекомендація:

- винести inline styles у `public/assets/css/style.css`;
- inline `onclick` замінити на `data-confirm` + JS handler;
- уніфікувати класи кнопок;
- прогнати пошук по PHP-шаблонах.

Перевірка після виправлення:

```text
rg "style=|onclick=" public app
node --check public/assets/js/main.js
php -l public/admin/edit.php
php -l public/admin/bulk_edit.php
```

## 7. Low Issues

### LOW-001. `public/admin/share.php` не валідує існування photo/album перед insert

Файл: `public/admin/share.php`

Ризик:

Некоректний ID може привести до database exception замість нормального flash-повідомлення. Також після створення album share редирект іде на `admin/albums.php?id=...`, тоді як форма редагування альбому використовує `edit=...`.

Рекомендація:

- перед insert перевіряти існування photo/album;
- FK exception ловити й показувати дружнє повідомлення;
- для album share редиректити на `admin/albums.php?edit=...`.

### LOW-002. `public/share.php` показує raw text error замість єдиного error layout

Файл: `public/share.php`

Ризик:

Для expired/not found/internal error користувач бачить простий текст, не спільний дизайн сайту. Це не security-critical, але погіршує UX і підтримку.

Рекомендація:

- використати спільний 404/500 helper або standalone error template;
- не показувати технічні деталі;
- зберегти правильні HTTP status codes.

### LOW-003. `public/admin/bulk_edit.php` використовує `die()` для CSRF failure

Файл: `public/admin/bulk_edit.php`

Ризик:

Поведінка відрізняється від решти адмінки: замість flash + redirect користувач бачить грубий plain text.

Рекомендація:

- замінити `die('Invalid CSRF token.')` на flash error і redirect;
- залишити status 400/403 там, де це доречно.

### LOW-004. `tools/restore.php` можна посилити щодо path validation

Файл: `tools/restore.php`

Поточний стан:

Tool є CLI-only і має confirmation `RESTORE`. Release/backup тести проходять. Прямої Zip Slip-проблеми під час аудиту не підтверджено, але валідацію шляху можна зробити канонічнішою.

Рекомендація:

- нормалізувати `\` і `/`;
- перевіряти target path через canonical base directory;
- обмежити очікувані extensions для media restore;
- залишити destructive restore тільки за явним підтвердженням.

### LOW-005. `storage/test_sessions` накопичує test session files

Файл: `tools/self_check.php`  
Папка: `storage/test_sessions`

Ризик:

Не security-проблема для release, бо `sess_*` виключаються, але локально папка може накопичувати сміття після self-check запусків.

Рекомендація:

- очищати старі test sessions у self-check;
- або додати cleanup у maintenance tool.

### LOW-006. Старі audit docs містять застарілі абсолютні посилання і надто сильні формулювання

Файли:

- `docs/AUDIT_REPORT.md`;
- `docs/SECURITY_AUDIT.md`.

Ризик:

Документи створюють враження, що безпековий стан ідеальний, хоча цей аудит знайшов medium/low hardening tasks.

Рекомендація:

- замінити абсолютні `file:///C:/...` посилання на repo-relative paths;
- прибрати категоричні фрази на кшталт "flawless";
- додати актуальний summary з цього аудиту.

## 8. Informational Findings

### INFO-001. Release build виглядає чистим

`tools/build_release.php` створив release ZIP з 105 entries. Forbidden-pattern scan не знайшов `.git`, `.env`, `config/database.php`, logs, sessions, backup ZIP, uploaded JPEG або `temp_*.php`.

### INFO-002. Базові automated checks пройшли

PHP lint, JS syntax check, unit-style tests, self-check і release build завершилися успішно.

### INFO-003. У локальному робочому дереві є runtime data

Є локальні uploads, originals, logs, sessions, backup ZIP і dist ZIP. Для Wamp/dev це очікувано. Вони не повинні потрапляти в Git або release ZIP.

### INFO-004. `config/database.php` існує локально

Це очікуваний локальний файл конфігурації БД. Значення не копіювалися в цей звіт. Release tooling його виключає. Production guard у коді блокує небезпечні production DB налаштування на кшталт root/empty password.

### INFO-005. Release ZIP включає частину audit/developer docs

Це не security issue саме по собі. Але варто вирішити, чи студентський/production release має включати всі audit prompts і внутрішні агентські документи.

### INFO-006. Схема і migrations загалом виглядають переносимими

SQL не прив'язаний до конкретної DB через `USE ...`. Є MySQL/MariaDB-specific конструкції, що нормально для заявленого stack. Перед production бажано прогнати clean install на цільовій MariaDB/MySQL.

### INFO-007. Основні high-risk зони вже мають захист

Знайдено:

- prepared statements;
- CSRF для POST-дій;
- session regeneration після login;
- session version для invalidation;
- private originals у `storage/originals`;
- MIME і image validation для upload;
- заборона PHP у uploads;
- CLI-only guards для tools;
- release exclusions для secrets/runtime files.

## 9. Security Review

### Auth/session

Стан добрий:

- login використовує password verification;
- після login виконується session regeneration;
- є idle timeout;
- `admin_id` перевіряється в БД;
- session version підтримує invalidation старих сесій.

Ризиків critical/high не знайдено.

### CSRF

Стан загалом добрий:

- основні POST-форми мають CSRF;
- delete/update/create виконуються через POST.

Покращення:

- `bulk_edit.php` має обробляти CSRF failure так само дружньо, як решта адмінки.

### Upload

Стан добрий:

- JPEG-only;
- MIME через `finfo_file()`;
- `getimagesize()`;
- random filenames;
- приватне зберігання оригіналів;
- large і thumbnail версії в public;
- EXIF Orientation враховується;
- duplicate hash підтримується.

Потрібна ручна перевірка на реальних файлах після майбутніх змін.

### XSS/CSP

HTML escaping використовується широко. Головний залишковий ризик тут не stored XSS, а підтримуваність CSP через inline styles/scripts на окремих сторінках.

### SQL Injection

Під час перегляду критичних шляхів не знайдено очевидної SQL injection. Dynamic placeholders у bulk actions формуються з integer-filtered IDs. Сортування/напрямки мають whitelist-підхід у відповідних місцях.

### Share links

Основний security/privacy недолік: share links без дефолтного expiry. Для приватної галереї це найважливіший hardening після видалення `temp_migrate.php`.

## 10. Architecture Review

Архітектура відповідає MVP:

- простий PHP без framework;
- `app/includes` для shared logic;
- `public` як DocumentRoot;
- `storage` для приватних runtime files;
- `tools` для CLI maintenance;
- `tests` для self-contained перевірок.

Що варто покращити далі:

- довести до кінця відділення бізнес-логіки від HTML на найважчих admin сторінках;
- винести share-link логіку в helper/service;
- уніфікувати flash/error handling;
- завершити CSS/CSP cleanup без inline styles.

## 11. Database Review

Стан:

- PDO pattern використовується;
- schema містить `admins`, `albums`, `photos`, `login_attempts`, tags/share-related таблиці;
- migrations є окремими файлами;
- schema не містить `USE my_photo_gallery;`;
- release/test tooling перевіряє частину DB/schema очікувань.

Покращення:

- додати expiry handling для `share_links.expires_at`;
- перевірити clean install і migrations на цільовій MariaDB/MySQL;
- після змін share links додати тест на expired token.

## 12. Tools Review

Сильні сторони:

- `self_check.php` проходить;
- `build_release.php` проходить і виключає небезпечні файли;
- tests проходять;
- backup/release exclusions перевіряються тестами.

Покращення:

- cleanup для `storage/test_sessions`;
- hardening path validation у restore;
- окремий test для share link expiry після реалізації.

## 13. Documentation Review

Документація багата, але потребує синхронізації:

- шляхи до docs;
- PHP version;
- upload limits;
- duplicated changelog heading;
- absolute local audit links;
- надто категоричні security statements.

Це не ламає застосунок, але напряму впливає на майбутню підтримку.

## 14. Recommended Fix Order

1. Покращити `public/admin/share.php`: existence validation і friendly errors.
2. Замінити raw text errors у `public/share.php` на спільний error layout.
3. Замінити `die()` у `bulk_edit.php` на flash/redirect.
4. Додати cleanup для `storage/test_sessions`.
5. Посилити `tools/restore.php` path validation.
6. Додати regression tests для share expiry і share admin flow.

## 15. Regression Checklist для наступних змін

Після виправлень запустити:

```text
C:\wamp64\bin\php\php8.3.14\php.exe -l path\to\changed.php
node --check public/assets/js/main.js
C:\wamp64\bin\php\php8.3.14\php.exe tests/run.php
C:\wamp64\bin\php\php8.3.14\php.exe tools/self_check.php
C:\wamp64\bin\php\php8.3.14\php.exe tools/build_release.php
```

Ручна перевірка:

- login/logout;
- створення share link;
- відкриття share link;
- expired share link;
- revoke share link;
- bulk edit;
- edit photo;
- gallery filters;
- album page;
- tags page;
- upload валідного JPEG;
- відмова для non-JPEG;
- release ZIP не містить private/runtime files.

## 16. Final Verdict

Проєкт можна вважати хорошим MVP-станом без знайдених critical/high/medium проблем. Перед наступним релізом варто закрити low issues, бо вони покращують UX, cleanup і restore hardening.

Найважливіші medium-пункти вже виконано: `temp_migrate.php` прибрано, expiry для share links додано, inline style/onclick дочищено, документацію синхронізовано.
