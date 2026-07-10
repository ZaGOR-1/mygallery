# Full Project Audit

Дата аудиту: 2026-07-10.

## 1. Executive Summary

Аудит виконано для версії `6.4.20`; High findings закриті у `6.4.21`, Medium/Low — у `6.4.22`, а Informational — закриті або підтверджені у `6.4.23` за файлом `VERSION`. Аудит виконано для робочої Git-копії, а не для clean release: до початку перевірки `FULL_PROJECT_AUDIT.md` і `provirka.md` були видалені, а `mygallery18_ai_audit_prompt.md` був новим untracked-файлом. Кодова база має добру базову модель безпеки: PDO з native prepared statements, централізоване HTML-екранування, CSRF, захищені admin routes, приватні оригінали, контроль доступу в `media.php`, 128-бітні share tokens і чистий release builder.

Поточну робочу копію не можна повністю запустити локально без додаткового налаштування, бо `config/database.php` відсутній; саме тому DB-dependent перевірки були пропущені, а `tools/self_check.php` завершився помилкою. Статичний аналіз не підтвердив SQL injection, stored/reflected/DOM XSS, public privacy leakage приватних альбомів або видачу byte-for-byte оригіналів неадміну через album ZIP. На момент аудиту backup/restore pipeline мав три High-проблеми; у v6.4.21 вони закриті format-v2 manifest/validation та staged transactional restore із rollback/restart recovery.

Medium/Low findings закриті у v6.4.22: CSRF lifecycle, ZIP cache/names, consistent backup snapshot, filesystem containment, dependency checks, test skip semantics, docs, bulk reporting, permissions, optimistic revision і checked writes виправлені. У v6.4.23 закрито I-03–I-05, а позитивні I-01/I-02 підтверджено regression-захистом. Production verdict усе ще залежить від environment gates: у цій локальній копії немає `config/database.php`, тому повний MySQL/HTTP/manual regression не виконувався.

### Post-audit remediation v6.4.21

- H-01: `.gitkeep`/`.htaccess` виключені з media payload; backup і restore мають однаковий strict filename contract.
- H-02: спільний `tools/lib/BackupArchiveValidator.php` перевіряє format v2, exact allowlist, non-empty SQL markers, size/SHA-256 і фактичне читання кожного ZIP stream; backup автоматично валідує власний output.
- H-03: restore повністю готує й хешує media у staging, перемикає директорії до DB commit, зберігає old rollback-копії та використовує journal + transactional marker для recovery після crash.
- Після змін `php tests/run.php`: `19 passed, 0 failed`; DB-dependent suites і далі `[SKIP]`, бо локальний `config/database.php` відсутній.

### Post-audit remediation v6.4.22

- M-01–M-08 і L-01–L-06 виправлено та покрито новими unit/static/fault tests.
- Local runner коректно показує DB suites як skipped; CI використовує `REQUIRE_TEST_DB=1` і MySQL service.
- PHP 8.2/8.4: lint 75 files без помилок; tests — `21 passed, 0 failed, 2 skipped`; required-DB mode без DB коректно завершився non-zero.
- Final release `mygallery_6.4.22_release.zip`: 112 entries, internal/external full stream readback без errors, forbidden internal/secret/media/runtime entries — 0.
- Реальний local MySQL backup/restore та browser/server regression залишаються невиконаними через відсутню локальну DB-конфігурацію.

### Post-audit remediation v6.4.23

- I-01/I-02: release cleanliness і web-security baseline підтверджені чинними regression tests; CI HTTP smoke тепер перевіряє CSP і `nosniff`.
- I-03: maintenance, share-link, protected-media та album-ZIP helpers винесено у focused includes; `download_album.php` скорочено з 357 до 206 рядків, `media.php` не містить helper declarations.
- I-04: schema та idempotent migration додають `chk_share_links_exactly_one_target`; self-check, admin health і DB/static tests вимагають constraint.
- I-05: workflow переведено на PHP 8.2/8.4 matrix і доповнено backup → verify → restore → self-check та HTTP smoke.
- Локально PHP 8.2/8.4: lint 82 files без помилок; tests — `24 passed, 0 failed, 2 skipped`; required-DB mode без DB коректно завершився non-zero.
- Final release `mygallery_6.4.23_release.zip`: 117 entries, internal/external full stream readback без errors, forbidden entries — 0, missing required v6.4.23 entries — 0.

## 2. Audit Scope

- Перевірено 68 PHP-файлів, 11 SQL-файлів, 1 JS-файл, 1 CSS-файл і 33 наявні до створення звіту Markdown-файли; після створення цього звіту Markdown-файлів стало 34.
- Перевірені каталоги: `app/`, `config/`, `database/`, `public/`, `storage/` (структура та runtime placeholders), `tools/`, `tests/`, `docs/`, `.gemini/agents/`, `.github/workflows/`.
- Перевірені всі публічні та admin endpoints, core includes, схема і всі міграції, maintenance tools, `.htaccess`, JS/CSS, документація та AI instruction files, перелічені в audit prompt.
- Встановлено, що це working tree; `.github/workflows/build_release.yml`, `AGENTS.md`, `CLAUDE.md`, `GEMINI.md`, `tools/build_release.php`, backup/restore tools і `tests/` реально існують.
- Старі версії `6.4.6`, `6.4.8`, `6.1.0` знайдені переважно в `CHANGELOG.md` та явно позначених historical reports; це історія, а не саме по собі stale mismatch.

Запущені команди та результати:

- PHP 8.2.26: lint усіх 68 `.php` — 0 помилок.
- Bundled Node.js: `node --check public/assets/js/main.js` — успішно.
- PHP 8.2.26: `php tests/run.php` — process exit 0, показано `17 passed, 0 failed`, але `album_privacy_test.php`, `dominant_color_test.php` і `share_links_test.php` фактично дали `[SKIP] DB not available`.
- PHP 8.2.26: `php tools/self_check.php` — exit 1, `config/database.php missing`.
- PHP 8.2.26: `php tools/build_release.php` — успішно, `dist/mygallery_6.4.20_release.zip`, 111 entries, 533111 bytes.
- ZIP: `tar -tf` — exit 0; усі file entries прочитані через `System.IO.Compression`, 0 stream errors; 0 entries із перевіреного списку секретів, media, logs, sessions, runtime locks і nested ZIP.
- Синтетичний backup test: `verify_backup.php` прийняв ZIP із порожнім `database.sql` (exit 0).
- Синтетичний restore test: backup-style `public/uploads/large/.htaccess` був відхилений як invalid media entry (exit 1).
- Синтетичний restore test: manifest заявляв 1 large file, архів містив 0; restore оголосив архів валідним і дійшов до confirmation prompt. Відновлення скасовано до будь-яких змін.
- Ізольований CSRF test: після генерації 14 токенів у сесії лишилося 10; перший токен був невалідний, п’ятий — валідний.

Не вдалося перевірити:

- реальне підключення до MySQL/MariaDB, clean install, повторний запуск migrations і DB-dependent tests через відсутній `config/database.php`;
- фактичний Apache/Nginx VirtualHost, TLS redirect, HSTS/cookie headers у браузері та права production-файлової системи;
- end-to-end login/upload/EXIF/private/share/media/ZIP/trash/backup/restore у браузері;
- реальний backup поточних даних, щоб не створювати приватний артефакт і не виходити за read-only audit scope.

## 3. Severity Summary

| Severity | Count |
|---|---:|
| Critical | 0 |
| High | 3 (виправлено у v6.4.21) |
| Medium | 8 (виправлено у v6.4.22) |
| Low | 6 (виправлено у v6.4.22) |
| Info | 5 (закрито/підтверджено у v6.4.23) |

## 4. Critical Issues

Critical issues not found.

Не підтвердилися: SQL injection через filters/sort/`IN (...)`, stored/reflected/DOM XSS, auth bypass admin routes, public leakage private album filters/covers/media, direct static derivative access в Apache, Zip Slip через backup entry names та видача приватних оригіналів public/share ZIP-користувачу.

## 5. High Issues

### H-01 — Штатний backup не проходить штатну restore-валідацію

- **Severity:** High
- **Status:** fixed in v6.4.21
- **Resolution:** backup пропускає `.gitkeep`/`.htaccess`, приймає лише валідні media filenames; regression fixture підтверджує fail-closed для control-file payload.
- **File:line:** `tools/backup.php:130`, `tools/backup.php:153`, `tools/backup.php:200`, `tools/backup.php:201`, `tools/restore.php:127`, `tools/restore.php:136`, `tools/restore.php:225`, `public/uploads/large/.htaccess:1`, `public/uploads/thumbnails/.htaccess:1`
- **Description:** `backup_add_directory_files()` пропускає лише `.gitkeep`, тому додає `.htaccess` із large/thumbnails. Restore дозволяє в цих префіксах лише `valid_photo_filename()` формату `32hex.(jpg|webp|avif)` і відхиляє `.htaccess`.
- **Impact:** backup, створений офіційним інструментом, непридатний для автоматичного disaster recovery; оператор може дізнатися про це лише під час аварійного restore.
- **Reproduction scenario:** синтетичний ZIP зі структурою штатного backup та entry `mygallery_backup/public/uploads/large/.htaccess` був відхилений `tools/restore.php` повідомленням про некоректний media-файл до confirmation/DB changes.
- **Fix:** у backup додавати тільки `valid_photo_filename()` і явно пропускати `.gitkeep`/`.htaccess`; або узгоджено дозволити control files у restore, але не копіювати їх як media. Найпростіше — не включати `.htaccess` у backup, бо release уже постачає ці правила.
- **Tests to add:** реальний round-trip fixture `backup -> verify -> restore validation` з `.gitkeep/.htaccess`, JPEG/WebP/AVIF і порожніми media directories.
- **Verification steps:** створити backup на тестовій БД; `verify_backup.php`; restore у чисту тестову інсталяцію; звірити DB rows, filenames, SHA-256 оригіналів і всі derivatives.

### H-02 — Backup verifier і restore не перевіряють фактичну цілісність manifest/SQL/media

- **Severity:** High
- **Status:** fixed in v6.4.21
- **Resolution:** реалізовано manifest v2 і спільну streaming validation з exact allowlist, SQL markers, size/SHA-256 та rejection для missing/extra/corrupt entries.
- **File:line:** `tools/verify_backup.php:33`, `tools/verify_backup.php:48`, `tools/verify_backup.php:63`, `tools/verify_backup.php:80`, `tools/restore.php:161`, `tools/restore.php:171`, `tools/restore.php:203`, `tools/restore.php:236`
- **Description:** verifier перевіряє лише наявність `database.sql`, JSON shape і кількість entries у трьох папках. Він не вимагає непорожній SQL, не читає/валідує всі entry streams, не має per-file size/hash і не відхиляє unexpected entries. Restore читає manifest, але взагалі не порівнює заявлені counts із `media_entries`.
- **Impact:** пошкоджений або неповний backup може отримати повідомлення «успішна перевірка»; manifest mismatch проходить restore preflight і після confirmation може спричинити втрату поточних media.
- **Reproduction scenario:** verifier прийняв синтетичний ZIP із порожнім `database.sql` та нульовими counts (exit 0). Інший ZIP заявляв `public_large=1`, фактично містив 0 media; restore оголосив його валідним і дійшов до `RESTORE` prompt.
- **Fix:** manifest v2 з точним allowlist entries, byte size і SHA-256 кожного файла та SQL; verifier і restore повинні використовувати одну спільну validation function, перевіряти non-empty/expected SQL markers, counts, hashes, stream read results і відсутність зайвих entries.
- **Tests to add:** empty SQL, missing entry, extra entry, wrong count, wrong hash, truncated stream, duplicate entry, invalid filename, oversized manifest/entry.
- **Verification steps:** кожен негативний fixture має завершуватися non-zero до confirmation; валідний backup має пройти verify і повний test restore з hash comparison.

### H-03 — Media restore неатомарний після commit бази даних

- **Severity:** High
- **Status:** fixed in v6.4.21
- **Resolution:** media розпаковуються у same-filesystem staging до DB mutation; directory swap виконується під DB transaction, а journal/DB marker забезпечують rollback або завершення interrupted restore.
- **File:line:** `tools/restore.php:268`, `tools/restore.php:281`, `tools/restore.php:283`, `tools/restore.php:296`, `tools/restore.php:301`, `tools/restore.php:311`, `tools/restore.php:318`, `tools/restore.php:326`
- **Description:** DML dump commit-иться, після чого поточні media видаляються in-place. Якщо open/write/copy/disk space/CRC failure стається під час extraction, скрипт виходить із уже новою БД та частково порожніми media directories. `stream_copy_to_stream()` result не перевіряється.
- **Impact:** production може залишитися в неконсистентному стані, а попередні файли вже видалені. DB rollback не рятує media після commit.
- **Reproduction scenario:** у disposable environment підтвердити `RESTORE`, а під час media extraction зробити target read-only або вичерпати disk quota; DB уже буде відновлена, `clean_directory()` уже видалить старі media, extraction завершиться частково.
- **Fix:** спочатку витягнути й повністю перевірити media у staging directories на тому самому filesystem; зробити DB transaction; атомарно swap/rename media directories з rollback backup; commit лише коли swap гарантований, або мати чіткий compensating rollback. Перевіряти copied byte count/hash.
- **Tests to add:** fault injection на N-му файлі, disk-full/write failure, DB failure до swap, swap failure, rollback старих media, restart/recovery після crash.
- **Verification steps:** після кожного injected failure система має бути повністю в old або new state, без mixed DB/media.

## 6. Medium Issues

### M-01 — Ліміт у 10 CSRF-токенів інвалідує форми під час рендерингу

- **Severity:** Medium
- **Status:** fixed in v6.4.22
- **Resolution:** усі форми одного render використовують спільний request-scoped one-time token; store має bounded history для паралельних вкладок, а login повністю обертає CSRF state.
- **File:line:** `app/includes/csrf.php:15`, `app/includes/csrf.php:18`, `app/includes/header.php:39`, `public/admin/index.php:151`, `public/admin/index.php:217`, `config/config.php:40`
- **Description:** кожен `csrf_field()` створює новий одноразовий токен, але сесія тримає лише 10. Admin index при 12 фото рендерить logout, bulk form і 12 delete forms, тобто щонайменше 14 токенів. Ранні токени видаляються до показу сторінки.
- **Impact:** logout, bulk edit/delete і перші item actions можуть стабільно давати CSRF error без закінчення сесії. На albums/tags/trash кількість форм може бути ще більшою.
- **Reproduction scenario:** runtime test із 14 викликами `csrf_token()` дав `stored=10`, `first_token_valid=no`, `fifth_token_valid=yes`.
- **Fix:** використовувати один session token на сторінку/сесію з rotation на privilege boundary, keyed per-form tokens без малого global FIFO, або зберігати достатньо токенів із TTL і явним upper bound, що не менший за максимальну кількість форм.
- **Tests to add:** 20+ forms on one page; submit first/middle/last form; logout after admin index render; one-time replay rejection.
- **Verification steps:** усі форми щойно відрендереної сторінки мають пройти один раз, повторний submit того самого токена — відхилятися.

### M-02 — Album ZIP із приватним/share/admin контентом явно кешується

- **Severity:** Medium
- **Status:** fixed in v6.4.22
- **Resolution:** admin/share ZIP отримують `private, no-store`; лише public non-admin ZIP має явний короткий public cache, а security headers надсилаються до stream.
- **File:line:** `public/download_album.php:127`, `public/download_album.php:138`, `public/download_album.php:141`, `public/download_album.php:142`, `public/download_album.php:243`
- **Description:** усі ZIP-відповіді отримують `Pragma: public` і `Cache-Control: max-age=3600`. Для admin це може бути archive з byte-for-byte originals; для share — приватний optimized album. Немає `private, no-store` і немає variant-aware response cache policy.
- **Impact:** browser/shared proxy/CDN може зберігати чутливий ZIP після logout/revoke; це суперечить no-store підходу адмінки та private media.
- **Reproduction scenario:** завантажити public, share і admin ZIP та порівняти response headers; усі три отримують однаковий cache policy.
- **Fix:** admin/share/private responses — `Cache-Control: private, no-store, max-age=0`, `Pragma: no-cache`, `Expires: 0`; public album за явною політикою може мати короткий public cache. Викликати security headers перед streaming.
- **Tests to add:** header tests для public/share/admin/private variants і revoke/logout flow.
- **Verification steps:** `curl -I`/browser devtools для кожного access mode; перевірити, що admin originals не лишаються у shared cache.

### M-03 — Backup формується без consistent DB/media snapshot

- **Severity:** Medium
- **Status:** fixed in v6.4.22
- **Resolution:** backup тримає exclusive media maintenance lock і `REPEATABLE READ` consistent DB snapshot; manifest photo inventory звіряє DB filenames/original hashes із canonical media та abort-ить missing/orphan state.
- **File:line:** `tools/backup.php:109`, `tools/backup.php:119`, `tools/backup.php:178`, `tools/backup.php:199`
- **Description:** кожна таблиця читається окремим `SELECT *` без consistent snapshot/locking, а media додаються ще пізніше. Паралельний upload/edit/delete може потрапити лише в частину таблиць або тільки в DB/media.
- **Impact:** формально валідний backup може містити orphan relations, DB rows без files або files без DB rows; `FOREIGN_KEY_CHECKS=0` у dump маскує частину проблем під час import.
- **Reproduction scenario:** під час backup виконати upload/delete між export `photos` і `photo_tags` або між DB export та media iteration; отримані набори представлятимуть різні моменти часу.
- **Fix:** DB export робити у consistent read transaction (`REPEATABLE READ`, consistent snapshot); media lifecycle на час backup координувати maintenance lock або робити documented quiesce/staging snapshot; manifest прив’язати до DB photo inventory/hashes.
- **Tests to add:** concurrent upload/delete during backup; після restore invariant check DB/media/FK/tags.
- **Verification steps:** повторюваний concurrency test не повинен створювати inconsistent backup або має безпечно abort із поясненням.

### M-04 — Cleanup/restore може видалити target за symlink поза дозволеною папкою

- **Severity:** Medium
- **Status:** fixed in v6.4.22
- **Resolution:** destructive code використовує lexical pathname, відмовляє symlink/junction/reparse roots/children і повторно перевіряє real containment перед delete/swap.
- **File:line:** `tools/restore.php:103`, `tools/restore.php:114`, `tools/restore.php:119`, `tools/restore.php:122`, `tools/cleanup_runtime.php:49`, `tools/cleanup_runtime.php:62`, `tools/cleanup_runtime.php:66`, `tools/cleanup_runtime.php:69`
- **Description:** destructive iterators беруть `getRealPath()`, який розкриває symlink у зовнішній target, а потім передають resolved path у `unlink()`/`rmdir()`. Немає `isLink()` refusal і повторної containment check перед delete.
- **Impact:** якщо writable runtime/media directory містить symlink, maintenance tool, особливо запущений із вищими правами через cron/root, може видалити файл поза project storage.
- **Reproduction scenario:** тільки в disposable sandbox створити symlink у test runtime dir на зовнішній test file, штучно зістарити його і запустити cleanup; поточний алгоритм оперує target realpath, а не path самого symlink.
- **Fix:** не використовувати resolved target для unlink; працювати з `getPathname()`, відхиляти symlinks через `isLink()/lstat`, перевіряти lexical+real parent containment і ніколи не запускати tools від root.
- **Tests to add:** symlink-to-file, symlink-to-dir, dangling symlink, Windows junction/reparse point, normal nested files.
- **Verification steps:** зовнішні sentinel files мають лишатися; tool повинен report/skip unsafe entries non-zero.

### M-05 — `zip` є runtime-залежністю фіч, але не входить у required checks/install docs

- **Severity:** Medium
- **Status:** fixed in v6.4.22
- **Resolution:** `zip` додано до required extensions, health/self-check/CI та Windows/Linux install docs.
- **File:line:** `public/download_album.php:8`, `app/includes/functions.php:113`, `public/admin/health.php:99`, `tools/self_check.php:149`, `README.md:116`, `README.md:220`
- **Description:** album download, verify і restore потребують `ZipArchive`, але `required_php_extensions()`, health і self-check не перевіряють `zip`. Windows module list його не згадує; Linux команда встановлює CLI `unzip`, але не `php-zip`.
- **Impact:** self-check/health можуть бути green, тоді як album ZIP і disaster recovery завершуються 500/CLI error.
- **Reproduction scenario:** вимкнути extension `zip`; core required-extension check проходить, але `download_album.php`, `verify_backup.php`, `restore.php` зупиняються.
- **Fix:** додати `zip` до required feature checks або окремий required/conditional capability check; README: `php-zip`/Wamp extension; health/self-check — `class_exists('ZipArchive')`.
- **Tests to add:** self-check negative fixture без zip; health status; CI assertion.
- **Verification steps:** `php -m`, self-check, health і album download повинні узгоджено показувати missing dependency.

### M-06 — Test runner рахує skipped DB suites як passed

- **Severity:** Medium
- **Status:** fixed in v6.4.22
- **Resolution:** runner має окремі passed/failed/skipped counters; `REQUIRE_TEST_DB=1` робить будь-який skip CI failure, а dominant-color test більше не залежить від DB.
- **File:line:** `tests/bootstrap.php:9`, `tests/bootstrap.php:13`, `tests/run.php:13`, `tests/run.php:17`, `tests/unit/album_privacy_test.php:7`, `tests/unit/share_links_test.php:7`
- **Description:** DB tests роблять `return`, але runner після `require_once` безумовно збільшує `$passed`. Поточний output заявив 17 passed, хоча три suites були skipped. `dominant_color_test.php` також непотрібно прив’язаний до DB.
- **Impact:** локальний/CI green status може перебільшити coverage; у цьому аудиті privacy DB behavior, schema та share table runtime не підтверджені.
- **Reproduction scenario:** запуск без `config/database.php` показує `[SKIP] DB not available. [OK]` і фінальне `17 passed, 0 failed`.
- **Fix:** окремий result API/count `passed/failed/skipped`; у CI required DB suites мають fail, якщо DB недоступна; чисті unit tests не повинні залежати від DB bootstrap.
- **Tests to add:** meta-test runner semantics і CI env flag `REQUIRE_TEST_DB=1`.
- **Verification steps:** локально — явне `14 passed, 0 failed, 3 skipped`; CI з недоступною DB — non-zero.

### M-07 — Поточні audit/bugs/UX docs перебільшують актуальність і суперечать коду

- **Severity:** Medium
- **Status:** fixed in v6.4.22
- **Resolution:** audit/bugs/backup/README/UI docs та GEMINI output rule синхронізовані; docs tests блокують missing references і повторне планування реалізованого UX.
- **File:line:** `docs/AUDIT_REPORT.md:5`, `docs/AUDIT_REPORT.md:15`, `docs/BUGS.md:5`, `docs/BUGS.md:13`, `docs/BACKUP_RESTORE.md:9`, `docs/UI_UX_RECOMMENDATIONS.md:457`, `README.md:554`, `GEMINI.md:110`
- **Description:** docs заявляють, що всі High/Medium/Low findings закриті та backup verification перевіряє integrity; це спростовано поточним аудитом. UI/UX plan досі пропонує copy button, keyboard lightbox, pagination styling, Inter, drawer, drag-and-drop і dominant color, які вже реалізовані. README посилається на відсутні `audit.md`/`provirka.md`. GEMINI audit rule дозволяє змінювати лише `docs/`, тоді як canonical audit prompts вимагають root report.
- **Impact:** оператор або наступний агент може помилково вважати backup/production стан перевіреним, повторно планувати готові features або писати звіт не в той файл.
- **Reproduction scenario:** порівняти наведені рядки з H-01..H-03, `public/assets/js/main.js`, `docs/IMPLEMENTED_FEATURES.md` і фактичним tree.
- **Fix:** після code fixes синхронізувати `AUDIT_REPORT`, `BUGS`, `BACKUP_RESTORE`, README та UI/UX status; у AI instructions залишити один canonical audit-output rule.
- **Tests to add:** docs consistency assertions для існування referenced files, поточної VERSION, implemented-vs-roadmap/UX status і відсутності overclaims.
- **Verification steps:** `rg` по stale names/claims; review усіх links; docs test має падати при неіснуючому reference.

### M-08 — ZIP entry names не мають cross-platform sanitization

- **Severity:** Medium
- **Status:** fixed in v6.4.22
- **Resolution:** original names нормалізують `/` і `\\`; ZIP entries є single-segment JPEG names без controls/reserved names, мають byte limit, Unicode support та case-insensitive deduplication.
- **File:line:** `app/includes/file_functions.php:204`, `app/includes/file_functions.php:206`, `public/download_album.php:318`, `public/download_album.php:320`, `public/download_album.php:332`
- **Description:** `safe_original_name()` і ZIP loop використовують OS-dependent `basename()`. На Linux backslash не є separator, тому original name на кшталт `..\..\file.jpg` або `C:\fakepath\file.jpg` може потрапити в ZIP entry із backslashes/control chars. Частина Windows extractors нормалізує backslash як separator.
- **Impact:** некоректна структура архіву, конфлікти і потенційний traversal при розпакуванні в чутливому extractor. Upload доступний лише адміну, що зменшує exploitability, але ZIP може отримувати третя особа через public/share.
- **Reproduction scenario:** на Linux test deployment завантажити multipart JPEG зі crafted Windows-style filename, згенерувати ZIP і перевірити raw central-directory entry names у Windows-compatible extractor.
- **Fix:** окрема `safe_zip_entry_filename()`: замінити `\` на `/`, взяти останній segment, прибрати control chars, `.`/`..`, separators/drive prefixes, дозволити обмежений Unicode filename, гарантувати `.jpg` для optimized files і fallback на ID.
- **Tests to add:** `/`, `\`, drive path, UNC, dot segments, NUL/control chars, Unicode, duplicate/case-insensitive names, 255+ chars.
- **Verification steps:** усі generated entry names мають бути single-segment і не містити slash/backslash/control/dot-segments.

## 7. Low Issues

### L-01 — Release builder пропустив новий root audit prompt

- **Severity:** Low
- **Status:** fixed in v6.4.22
- **Resolution:** production Markdown пакується категоріальним root/docs allowlist; довільні audit/prompt names блокуються tests і final ZIP verification.
- **File:line:** `tools/build_release.php:39`, `tools/build_release.php:45`, `README.md:464`
- **Description:** internal artifact patterns охоплюють лише конкретні назви. Фактичний ZIP містить `mygallery/mygallery18_ai_audit_prompt.md`, хоча docs заявляють, що internal AI/audit artifacts не входять у production release.
- **Impact:** витоку секретів не виявлено, але production bundle містить внутрішню інструкцію й policy drift.
- **Reproduction scenario:** `php tools/build_release.php`, потім list ZIP entries.
- **Fix:** категоріальний allowlist production docs або ширше правило для root/docs audit/prompt artifacts; не покладатися лише на конкретні імена.
- **Tests to add:** довільні `*_audit_prompt.md`, `audit-*.md`, нові AI config dirs.
- **Verification steps:** rebuilt ZIP не містить prompt, а required operational docs лишаються.

### L-02 — Bulk delete допускає тихий partial success

- **Severity:** Low
- **Status:** fixed in v6.4.22
- **Resolution:** all-item preflight скасовує batch до змін для missing/unsafe items; runtime partial failure повертає точні deleted/failed IDs у UI.
- **File:line:** `public/admin/bulk_edit.php:35`, `public/admin/bulk_edit.php:40`, `public/admin/bulk_edit.php:45`, `public/admin/bulk_edit.php:51`
- **Description:** фото видаляються по одному окремими file+DB transactions. Якщо N-й delete падає, попередні вже переміщені в trash/видалені з DB, а UI показує лише generic error без точного списку success/failure.
- **Impact:** операція не атомарна на рівні batch; адміністратор може повторити дію або неправильно оцінити результат.
- **Reproduction scenario:** зробити один із пізніх media files недоступним для rename та виконати bulk delete.
- **Fix:** preflight усіх IDs/files, чіткий per-item result і summary; або batch manifest/coordinated transaction, якщо потрібна атомарність.
- **Tests to add:** failure on first/middle/last item, duplicate IDs, missing row/file.
- **Verification steps:** UI точно показує deleted/failed IDs; повторний запуск безпечний.

### L-03 — Backup confidentiality залежить від зовнішнього `umask`

- **Severity:** Low
- **Status:** fixed in v6.4.22
- **Resolution:** Linux backup directory/file примусово мають 0700/0600, group/other access abort-ить backup; правила задокументовані.
- **File:line:** `tools/backup.php:32`, `tools/lib/SimpleZipWriter.php:20`
- **Description:** `backups/` створюється 0755, ZIP через `fopen('wb')` без явного restrictive mode/chmod. На типовому Linux umask 022 archive може бути 0644. Backup містить private originals, password hashes, share tokens, а з `--include-config` — DB credentials.
- **Impact:** інший local OS user на shared host може прочитати backup; на dedicated VPS ризик нижчий.
- **Reproduction scenario:** створити backup на Linux з umask 022 та перевірити `stat`/ACL.
- **Fix:** створювати backup dir 0700, файл 0600, перевіряти effective permissions; docs заборонити root/shared-readable storage.
- **Tests to add:** permission assertion на Linux CI.
- **Verification steps:** `stat -c '%a' backups backup.zip` показує 700/600 або stricter.

### L-04 — Pre-auth CSRF token list не очищається після login

- **Severity:** Low
- **Status:** fixed in v6.4.22
- **Resolution:** після session ID regeneration pre-auth list/legacy/request token видаляються й створюється новий post-auth one-time token.
- **File:line:** `app/includes/auth.php:62`, `app/includes/auth.php:65`, `app/includes/auth.php:72`, `app/includes/csrf.php:11`
- **Description:** session ID регенерується, але `$_SESSION['csrf_tokens']` не очищається; встановлюється лише новий legacy `csrf_token`. Невикористані токени з інших pre-login tabs переживають privilege boundary.
- **Impact:** практична експлуатація потребує знання pre-auth token, але це порушує заявлену CSRF rotation model.
- **Reproduction scenario:** згенерувати два anonymous tokens, одним submit login, після `login_admin()` перевірити другий через `consume_csrf_token()`.
- **Fix:** після `session_regenerate_id(true)` unset обидва token stores і створити новий post-auth token set.
- **Tests to add:** pre-auth token rejected after login; post-auth token valid once.
- **Verification steps:** старі токени не проходять після login, форми нової сторінки працюють.

### L-05 — Optimistic lock має лише секундну точність

- **Severity:** Low
- **Status:** fixed in v6.4.22
- **Resolution:** додано `photos.lock_version` та idempotent migration; edit використовує atomic `WHERE lock_version = ?` + increment/rowCount, інші metadata mutations теж збільшують revision.
- **File:line:** `database/schema.sql:52`, `app/includes/photo_service.php:180`, `app/includes/photo_service.php:188`, `app/includes/photo_service.php:194`
- **Description:** lock token — `TIMESTAMP` із секундною точністю. Два updates у ту саму секунду можуть мати однаковий `updated_at`, тому stale form не завжди буде розпізнана.
- **Impact:** рідкісна lost update для одного/кількох admin tabs; single-admin модель зменшує ризик.
- **Reproduction scenario:** два parallel edits із однаковим hidden token, виконані в одну секунду.
- **Fix:** integer revision column із atomic increment або DATETIME(6)/TIMESTAMP(6) плюс affected-row compare.
- **Tests to add:** concurrent same-second update test.
- **Verification steps:** тільки один update зі stale revision проходить.

### L-06 — SimpleZipWriter не перевіряє результати запису

- **Severity:** Low
- **Status:** fixed in v6.4.22
- **Resolution:** `writeAll()` перевіряє short writes, finish перевіряє flush/close, backup/release повторно відкривають і читають готові ZIP streams; додано failing-wrapper test.
- **File:line:** `tools/lib/SimpleZipWriter.php:69`, `tools/lib/SimpleZipWriter.php:89`, `tools/lib/SimpleZipWriter.php:101`, `tools/lib/SimpleZipWriter.php:133`, `tools/lib/SimpleZipWriter.php:149`
- **Description:** `fwrite()` return values не перевіряються; `finish()` може завершитися без exception після short write/disk-full. Backup tool автоматично не перевіряє щойно створений archive.
- **Impact:** corrupted release/backup може бути оголошений створеним; для backup це підсилює H-02.
- **Reproduction scenario:** output filesystem із малою quota або fault-injected stream wrapper.
- **Fix:** `writeAll()` loop із перевіркою bytes/error, flush/close error handling, після finish відкрити й прочитати archive; backup — обов’язковий verifier.
- **Tests to add:** short-write/failing stream tests і truncated archive.
- **Verification steps:** будь-який short write дає non-zero та не залишає artifact, позначений успішним.

## 8. Informational / Improvements

### I-01 — Release ZIP загалом чистий

**Status:** validated/closed in v6.4.23.

Початковий ZIP мав L-01 з audit prompt. Після v6.4.22 production-doc allowlist і повторної v6.4.23 перевірки final ZIP відкрився, усі streams прочитані, не знайдено `.git`, `.env`, `config/database.php`, internal audit/agent docs, media, logs, sessions, runtime locks, backups або nested ZIP.

### I-02 — Web security baseline сильний

**Status:** validated/closed in v6.4.23.

Prepared statements, sort whitelist, integer LIMIT/OFFSET, `h(... ENT_QUOTES)`, CSP без user-controlled `innerHTML`, POST+CSRF mutations, session regeneration, strict cookie mode, HttpOnly/SameSite, no-store admin headers і dummy password hash реалізовані.

**Verification:** чинні static/unit tests перевіряють ключові policy markers, а CI HTTP smoke — CSP і `X-Content-Type-Options: nosniff` на реальній відповіді застосунку.

### I-03 — Варто зменшити великі core files

**Status:** fixed in v6.4.23.

Maintenance containment/locking, share lookup/expiry, protected-media access та album-ZIP naming/cache/cooldown/streaming винесено у `maintenance_functions.php`, `share_functions.php`, `media_access_functions.php` і `album_zip_functions.php`. Разом із уже виділеним `BackupArchiveValidator.php` це закриває запропоновані межі без framework/DI container; `download_album.php` скорочено з 357 до 206 рядків.

### I-04 — DB hardening candidates

**Status:** fixed in v6.4.23.

Схема та idempotent `2026_07_10_add_share_target_check.sql` мають CHECK, що рівно одне з `share_links.photo_id/album_id` не NULL. Constraint контролюють self-check, admin health, static test і DB test із відхиленням invalid INSERT. Album cover membership лишається application invariant. Реальна MySQL/MariaDB сумісність локально не запускалась у цьому workspace.

### I-05 — CI/manual coverage можна розширити

**Status:** fixed in v6.4.23.

Workflow тестує PHP 8.2/8.4, вимагає DB suites без skips, виконує backup → verify → restore → self-check і HTTP smoke для homepage/login/404/CSP/`nosniff`. Browser, Apache/Nginx/TLS, ненульовий production-like media fixture та production filesystem tests залишаються manual/environment gates.

## 9. Security Review

- **SQL injection:** не підтверджено. User inputs bind-яться; dynamic `IN` будується з integer IDs і `?`; sort має whitelist; LIMIT/OFFSET bind як int; table names у tools походять із fixed arrays.
- **XSS/CSP:** не підтверджено. DB/query/error outputs переважно проходять `h()`. `main.js` використовує `textContent`; два `innerHTML` містять лише constants. CSP `script-src/style-src 'self'` узгоджується з відсутністю inline handlers.
- **CSRF:** mutation routes захищені, empty token відхиляється, `hash_equals` є; request-scoped tokens one-time і повністю rotate-яться після login (M-01/L-04 fixed).
- **Auth:** усі admin pages, крім login, використовують `require_admin()`; download original захищений. Login має account/IP buckets, dummy hash і generic error. Auth bypass не знайдено.
- **Sessions:** strict mode, cookies only, regeneration, 1-hour idle timeout, 60-second `session_version` check, fail-closed DB freshness check. Secure cookie/HSTS залежать від коректного HTTPS/proxy deployment.
- **Uploads:** `UPLOAD_ERR`, size, `is_uploaded_file`, `finfo`, `getimagesize`, pixel/dimension/memory limits, random server names, GD decode, orientation 1–8 і cleanup реалізовані. Double extension/fake JPEG не стає executable file.
- **File access:** photo/media path helpers перевіряють filename/realpath containment; restore Zip Slip allowlist сильний; destructive cleanup/restore відмовляють symlink/junction/reparse targets (M-04 fixed).
- **Privacy:** private originals у `storage`; derivatives йдуть через `media.php`; Apache nested `.htaccess` deny; Nginx deny rules задокументовані. Public privacy leaks не знайдено.
- **Share links:** 16 random bytes/32 hex, format prevalidation, unique index, expiry, deletion/revoke, membership check, noindex/noarchive, fail-closed rate-limit storage у production. Tokens не логуються повністю.
- **Album ZIP:** access variants і cache scope розділені; public/share використовує optimized, admin — originals; count/size/cooldown/generation lock є; sensitive responses no-store, entry names cross-platform safe (M-02/M-08 fixed).
- **Backup/restore:** Zip Slip blocked, exact hash/inventory validation, consistent DB/media snapshot, staged transactional swap і crash recovery реалізовані (H-01..H-03/M-03 fixed).
- **Release ZIP:** секретів/private media/internal prompts не знайдено після categorical allowlist і stream readback (L-01/L-06 fixed).
- **Production config:** `APP_DEBUG=true`, non-HTTPS APP_URL і root/empty DB password block production startup. Фактичні server headers/redirects не перевірені.

## 10. Architecture Review

Структура `app/public/config/storage/database/tools/tests/docs` логічна, а `public/` може бути єдиним DocumentRoot. Проєкт лишається plain PHP без runtime framework/Composer/Node dependency. Core responsibilities уже частково розділені на auth, csrf, gallery, file і photo service.

Основний maintainability risk — розмір та змішані responsibilities: `file_functions.php` одночасно обробляє upload paths, EXIF/GD, responsive URLs і trash manifests; `photo_service.php` охоплює photo/album/trash/order; `download_album.php` поєднує controller, access, rate limit, cache і ZIP writer. Share validation дублюється в `share.php`, `media.php`, `download_album.php`. Рекомендовано винести невеликі функціональні modules, але не перетворювати проєкт на framework application.

## 11. Privacy Review

- Homepage latest/hero/stats явно фільтрують private albums.
- Public albums не повертають private albums; cover subquery вимагає membership і має safe fallback.
- Public gallery/count/search/filter/pagination використовують `(albums.is_private IS NULL OR albums.is_private = 0)`.
- Public tag/camera options join-яться з photos/albums і не показують private-only values.
- `photo.php` повертає 404 для private album без admin; public prev/next також фільтрує private.
- `media.php` віддає private derivative лише admin або valid photo/album token; expired/revoked token не проходить.
- Share album примусово встановлює target `album_id`; `view_photo` перевіряє membership.
- Public/share album ZIP не читає `storage/originals`; admin variant відокремлений cache key.
- `large/thumbnails/originals` static access заборонений Apache `.htaccess`; Nginx потребує documented rules.
- Залишковий environment risk: ручна перевірка response headers і real web-server config.

## 12. Database Review

`schema.sql` містить усі очікувані таблиці й колонки: `admins`, `schema_migrations`, `albums`, `photos`, `login_attempts`, `tags`, `photo_tags`, `share_links`, а також `original_sha256`, `session_version`, `cover_photo_id`, `is_private`, `sort_order`, `dominant_color`, `lock_version`. Charset/collation — `utf8mb4`; FKs, exact-one-target share CHECK, unique filenames/hash/token, FULLTEXT public/admin indexes присутні. SQL не містить `USE`.

Міграції використовують `IF NOT EXISTS` або `information_schema` guards і в нормальному стані придатні до повторного запуску; `tools/migrate.php` правильно не обіцяє DDL atomicity та записує migration лише після успіху. У v6.4.22 додано idempotent integer `photos.lock_version` migration і self-check required-column assertion; у v6.4.23 — idempotent exact-one-target CHECK для `share_links` із self-check/health assertion. Не перевірено реально: clean MySQL 8/MariaDB 10.6 install, multi-statement PDO execution, repeated migration run та partial-failure recovery.

## 13. Tools Review

| Tool | Assessment |
|---|---|
| `setup.php` | CLI-only, password не в argv за замовчуванням, hash і session_version invalidation є. |
| `migrate.php` | Реєстр migration і idempotency policy правильні; DB runtime не перевірено. |
| `self_check.php` | Fail-fast, required zip, lock_version, share target CHECK і DB/schema checks є; локально зупинився на missing DB config. |
| `build_release.php` | Production-doc allowlist, sensitive exclusions, checked writer і повний stream readback; final ZIP clean. |
| `backup.php` | Public output blocked; 0700/0600, consistent snapshot/media lock, schema_migrations, photo inventory та full validation. |
| `verify_backup.php` | Спільна exact allowlist/size/SHA-256/inventory/stream validation. |
| `restore.php` | Zip Slip/symlink safe, prevalidated staging, DB+directory rollback і journal recovery. |
| `cleanup_orphans.php` | Dry-run default, safe path helpers; behavior з реальною DB не перевірено. |
| `cleanup_runtime.php` | Dry-run default, age thresholds, lexical containment і fail-closed symlink/junction handling. |
| `migrate_legacy_originals.php` | Dry-run default, hash comparison; production run не перевірено. |
| `recover_trash.php` | Manifest entries проходять safe resolver; manual failure matrix потрібна. |
| `regenerate_images.php` | JPEG replacement атомарний; DB/files runtime не перевірено. |
| `backfill_sha256.php` | Prepared update; варто використовувати safe existing path та обробляти duplicate hash per row. |
| `SimpleZipWriter.php` | Portable uncompressed writer із writeAll/flush/close checks; output додатково перечитується ZipArchive. |

## 14. Tests Review

Наявні тести корисні для EXIF fractions/orientation helper, paths, CSRF one-time semantics, tags, privacy query helpers, media/static policy, share token/expiry, ZIP fingerprint, release exclusions, docs markers, schema CHECK і optimistic-lock code markers. CI піднімає MySQL 8 на PHP 8.2/8.4 і виконує schema/migrations/self-check/tests, backup/restore, HTTP smoke та build.

Після v6.4.23 unit/static/fault coverage додатково охоплює 25-form CSRF render і login rotation, backup inventory/corruption, atomic restore recovery, bulk exact results, ZIP filename/cache corpus, symlink containment, short writes, share access/constraint, CI contract та runner skip semantics. Основні залишкові прогалини:

1. Реальний backup -> verify -> restore round-trip із ненульовою DB/media та hash comparison (CI round-trip поки використовує clean fixture).
2. Глибші HTTP tests для homepage privacy, photo 404, media public/private/token, share revoke/expire, ZIP headers/access; базовий homepage/login/404/header smoke уже є.
3. Upload integration: valid/corrupt/fake/large JPEG, orientation 1–8, duplicate race, partial derivative failure cleanup.
4. Migration clean install + double run на MySQL 8 і MariaDB 10.6.
5. Windows junction/reparse і Linux production-permission tests на реальних filesystems.

## 15. Documentation Review

У v6.4.23 `AGENTS.md`, README, CHANGELOG, audit/bugs/security/implemented docs синхронізовані з focused helper modules, share-target migration і CI matrix/round-trip/HTTP smoke. README містить обидві поточні 2026-07-10 migrations, accurate test skip semantics та production-doc allowlist. Docs consistency tests перевіряють VERSION, canonical audit output rule, migration references і відсутність повторного планування готового UX.

## 16. Positive Security Decisions

- PDO: exceptions, associative fetch, native prepares, utf8mb4.
- User-driven SQL values bind-яться; dynamic sort whitelist; integer pagination.
- Central `h()` із `ENT_QUOTES`; CSP-compatible event listeners; no user-controlled HTML injection у JS.
- Усі admin mutations POST+CSRF; admin routes захищені; logout POST-only.
- Session strict mode, cookies only, regeneration, idle timeout, session_version freshness, admin no-store.
- Login rate limiting має exact/account/IP buckets і dummy password hash.
- Upload перевіряє PHP upload status, size, `is_uploaded_file`, `finfo`, `getimagesize`, dimensions/pixels/memory і GD decode.
- Оригінал зберігається byte-for-byte у private storage; derivatives мають random names і strip EXIF через GD.
- EXIF Orientation 1–8 реалізовано й покрито pixel test у self-check.
- Public privacy queries, filter options, album covers і prev/next мають explicit private filters.
- `media.php` централізує access, format/variant whitelist і private no-store.
- Share tokens мають 128 bits entropy, format validation, expiry, cascade deletion/revoke та robots noindex.
- Album ZIP розділяє optimized public/share і admin originals, cache fingerprint/scope та generation lock.
- Restore має strong entry allowlist і Zip Slip checks до destructive phase.
- Release builder має defense-in-depth exclusions; фактичний ZIP не містив secrets/private media.

## 17. Recommended Fix Order

План на 1–2 дні:

1. **P0, S — completed v6.4.21:** H-01 — `.htaccess` не входить до media payload; додано compatibility fixture.
2. **P0, M — completed v6.4.21:** H-02 — manifest v2/shared validation; corrupt/mismatch fixtures fail closed.
3. **P0, L — completed v6.4.21:** H-03 — staged atomic media restore із fault/recovery tests.
4. **P1, S — completed v6.4.22:** CSRF token storage M-01 і rotation L-04.
5. **P1, S — completed v6.4.22:** private/no-store ZIP headers M-02 і safe entry names M-08.
6. **P1, S — completed v6.4.22:** zip capability checks/docs M-05.
7. **P1, M — completed v6.4.22:** symlink-safe cleanup/restore M-04 та consistent backup snapshot/inventory M-03.
8. **P2, S — completed v6.4.22:** runner skips M-06, release prompt L-01, bulk summary L-02, permissions L-03, checked writes L-06 і revision L-05.
9. **P2, S — completed v6.4.22:** audit/bugs/backup/README/UI/AI instructions синхронізовано.
10. **P2, M — completed v6.4.23:** I-01–I-05 — focused helper extraction, share CHECK, PHP matrix, backup/restore та HTTP smoke; позитивні security/release observations regression-підтверджені.
11. **Gate:** MySQL 8 + MariaDB 10.6 runtime; full manual browser/security regression; fresh release ZIP recheck.

## 18. Regression Test Checklist

- [ ] Login/logout.
- [ ] Invalid login і generic error.
- [ ] Exact/account/IP rate limiter, lock expiry, trusted proxy behavior.
- [ ] CSRF failure: missing/empty/wrong/replayed token.
- [ ] 20+ admin forms: first/middle/last action і logout.
- [ ] Valid JPEG upload.
- [ ] Non-JPEG, fake JPEG, corrupt JPEG, `photo.jpg.php`.
- [ ] JPEG over file/dimension/pixel/memory limits.
- [ ] Duplicate upload і concurrent duplicate race.
- [ ] EXIF Orientation 1–8, missing/invalid EXIF.
- [ ] Large/thumbnail/WebP/AVIF creation and cleanup on partial failure.
- [ ] Album create/rename/delete/cover/order.
- [ ] Private album: homepage, albums, gallery, stats, filters, tags, camera counts.
- [ ] Tags create/edit/merge/delete/prune.
- [ ] Bulk edit album/tags/no-change/empty values.
- [ ] Bulk delete partial failure й accurate summary.
- [ ] Search FULLTEXT/LIKE, filters, sort, pagination query preservation.
- [ ] Public original filename does not search; admin/share behavior explicit.
- [ ] Photo page public/private, prev/next privacy.
- [ ] `media.php` GET/HEAD, format/variant, public/private/admin/token, expired/revoked.
- [ ] Direct `/uploads/large`, `/thumbnails`, `/originals` blocked on Apache і Nginx.
- [ ] Share create/open/revoke/expire, deleted target, moved photo, private album.
- [ ] Share robots headers і rate limiter failure modes.
- [ ] Admin original download, headers і filename safety.
- [ ] Album ZIP public/share/admin/private access.
- [ ] Album ZIP count/size/cooldown/cache/lock/stale invalidation.
- [ ] Album ZIP no-store/private headers і safe entry names.
- [ ] Trash delete/restore/purge/purge-all, interrupted DB/file operations.
- [ ] `recover_trash.php` dry-run/apply/purge-deleted.
- [ ] `cleanup_orphans.php` dry-run/delete/missing media.
- [ ] `cleanup_runtime.php` age thresholds і symlink refusal.
- [ ] `regenerate_images.php` all/large/thumbnails/photo-id/dry-run.
- [ ] `backfill_sha256.php` missing file/duplicate hash.
- [ ] Backup default/include-config/public-output refusal/permissions.
- [ ] Verify valid/corrupt/empty/missing/extra/hash mismatch backup.
- [ ] Restore success, Zip Slip, count/hash mismatch, DB failure, media failure, rollback.
- [ ] Clean install schema, migrations twice, partial migration retry.
- [ ] `self_check.php` із повною DB та без кожної required extension.
- [ ] `tests/run.php` із DB і без DB; skipped count correct.
- [ ] PHP 8.2 і 8.4 lint/tests.
- [ ] `node --check` і browser JS/CSP console.
- [ ] Build release; read every ZIP stream; forbidden entry scan.
- [ ] 404/500 без stack trace у production.
- [ ] Unauthorized admin routes і expired session.
- [ ] HTTPS redirect, Secure/HttpOnly/SameSite cookies, HSTS only HTTPS production.
- [ ] Backup/log/session/original filesystem permissions і no web access.

## 19. Production Readiness Verdict

`Conditionally ready after environment validation`.

H-01..H-03 закриті у v6.4.21, M-01..M-08 і L-01..L-06 — у v6.4.22, I-01..I-05 — закриті/підтверджені у v6.4.23. У code scope відкритих findings цього аудиту не лишилося. Перед production все ще обов’язкові реальний MySQL/MariaDB backup → verify → restore round-trip із ненульовими media, DB tests без skips, self-check pass та manual HTTP/TLS/browser regression.

Ризик deploy тепер зосереджений не у відомих code findings, а в невиконаній перевірці конкретного production environment: DB engine/config, filesystem permissions, Apache/Nginx/TLS headers і browser flows.

## 20. Final Verdict

Добре зроблено: public/private access model, prepared SQL, output escaping, upload validation, session hardening, token entropy, private originals, protected derivatives, release exclusions і базова test/CI інфраструктура.

High findings виправлено у v6.4.21, Medium/Low — у v6.4.22, Informational — закриті або підтверджені у v6.4.23. Environment validation перед production відкладати не можна.

Версію `6.4.23` можна вважати придатною для локальної розробки. Позначати її stable для Internet deployment варто лише після успішного реального backup/restore round-trip, DB tests без skips, self-check pass і manual browser/server regression на цільовому сервері.
