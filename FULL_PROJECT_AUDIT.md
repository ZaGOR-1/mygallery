# Full Project Audit

Дата аудиту: 2026-06-13  
Проєкт: персональна фотогалерея `mygallery`  
Режим початкового створення: аудит без виправлення коду.
Статус після follow-up: High/Medium/Low findings із цього звіту виправлено 2026-06-13 або зведено до документованого майбутнього `session_version` після появи зміни пароля.

## 1. Executive Summary

Загальний стан проєкту добрий для MVP: структура `public/app/config/database/tools/storage` в основному правильна, PHP-синтаксис проходить, JavaScript проходить `node --check`, SQL-інʼєкцій у перевірених запитах не знайдено, POST-форми адмінки мають CSRF, upload перевіряє MIME через `finfo_file()`, `getimagesize()`, розмір і пікселі.

Основний ризик зараз не в SQL/XSS, а у файловій консистентності: у БД є фото, для яких приватні оригінали в `storage/originals` відсутні, але legacy-копії ще лежать у `public/uploads/originals`. Це треба виправити першим.

Підсумок проблем:

- Critical: 0
- High: 1
- Medium: 6
- Low: 7
- Info: 5

## 2. Critical / High Findings

### H-01. Legacy originals залишилися в public, а приватні originals відсутні для частини DB-записів

- Рівень: High
- Статус: виправлено 2026-06-13.
- Файли і рядки: `tools/cleanup_orphans.php:38`, `tools/cleanup_orphans.php:64`, `tools/cleanup_orphans.php:76`, `README.md:75`, `public/uploads/originals/.htaccess:1`
- Опис: `php tools/cleanup_orphans.php` показав 4 DB-записи, для яких немає файлів у `storage/originals`, і 4 legacy-файли у `public/uploads/originals`.
- Як відтворити: запустити `php tools/cleanup_orphans.php`.
- Ризик: оригінали в public залежать від `.htaccess`. Якщо `AllowOverride` вимкнений, сервер не Apache, або production буде на Nginx без окремого deny-правила, приватні оригінали можуть стати доступними напряму.
- Наслідки: витік приватних оригіналів, EXIF/metadata, неузгодженість БД і файлового сховища.
- Рекомендоване виправлення: спочатку запустити dry-run `php tools/migrate_legacy_originals.php`, потім `php tools/migrate_legacy_originals.php --apply`, повторити `php tools/cleanup_orphans.php`, після перевірки прибрати public-копії. Для production краще не покладатися на public legacy originals взагалі.

## 3. Medium Findings

### M-01. `recover_trash.php` довіряє шляхам із manifest-файлів

- Рівень: Medium
- Статус: виправлено 2026-06-13.
- Файли і рядки: `tools/recover_trash.php:39`, `tools/recover_trash.php:46`, `tools/recover_trash.php:51`, `tools/recover_trash.php:57`, `app/includes/functions.php:1152`
- Опис: recovery tool читає `from` і `trash` із JSON manifest і при `--apply` робить `rename()` / `unlink()` без повторної realpath-перевірки базових директорій.
- Як відтворити: вручну змінити manifest у `storage/trash/*.json` і запустити `php tools/recover_trash.php --apply`.
- Ризик: локальний користувач або пошкоджений manifest може змусити tool рухати/видаляти не ті файли в межах прав процесу.
- Наслідки: пошкодження файлів або некоректне відновлення після аварійного delete.
- Рекомендоване виправлення: зберігати у manifest відносні імена файлів/типи сховища, перед `rename()` / `unlink()` перевіряти `valid_photo_filename()`, `safe_existing_storage_file_path()` / `safe_existing_upload_file_path()` і realpath-guard для trash.

### M-02. Обробка помилок в `admin/albums.php` може впасти повторно

- Рівень: Medium
- Статус: виправлено 2026-06-13.
- Файл і рядки: `public/admin/albums.php:87`, `public/admin/albums.php:93`
- Опис: у `catch` викликається `db()->inTransaction()` і `db()->rollBack()`. Якщо першопричина — проблема підключення до БД, повторний `db()` може кинути новий виняток.
- Як відтворити: зламати DB-конфіг і виконати POST-дію альбомів.
- Ризик: користувач отримає 500 замість контрольованого повідомлення, лог може втратити першопричину.
- Наслідки: гірша діагностика і нестабільна поведінка адмінки при DB outage.
- Рекомендоване виправлення: створити `$pdo = db()` один раз перед транзакцією, у `catch` перевіряти тільки `isset($pdo) && $pdo->inTransaction()`.

### M-03. Немає HSTS / централізованого HTTPS redirect для production

- Рівень: Medium
- Статус: виправлено 2026-06-13.
- Файли і рядки: `app/includes/functions.php:302`, `public/.htaccess:6`, `README.md:294`
- Опис: застосунок відправляє CSP, `nosniff`, `X-Frame-Options`, але не відправляє `Strict-Transport-Security`. Production-конфіг вимагає `APP_URL=https://`, але HTTP-запити не редиректяться на HTTPS на рівні app/Apache.
- Як відтворити: перевірити заголовки відповіді у production HTTPS.
- Ризик: downgrade/HTTP-доступ, слабший захист cookie до першого HTTPS-візиту.
- Наслідки: менший рівень transport security на production.
- Рекомендоване виправлення: додати HSTS тільки для `APP_ENV=production` і HTTPS-запитів; HTTPS redirect краще налаштувати у VirtualHost/Nginx, щоб не ламати локальний Wamp HTTP.

### M-04. Документація посилається на файли, які зараз видалені у Git worktree

- Рівень: Medium
- Статус: виправлено 2026-06-13.
- Файли і рядки: `README.md:63`, `README.md:65`, `README.md:66`, `AGENTS.md:150`, `AGENTS.md:319`
- Опис: `git status --short --ignored` показує `D AUDIT_REPORT.md`, `D BUGS.md`, `D FIXES_APPLIED.md`, але README/AGENTS досі описують їх як актуальні файли.
- Як відтворити: виконати `git status --short --ignored` і порівняти з README/AGENTS.
- Ризик: майбутні інструкції для агента і користувача ведуть до неіснуючих файлів.
- Наслідки: плутанина в аудитах, roadmap/fixes/bugs history може розʼїхатися.
- Рекомендоване виправлення: або відновити ці файли, або оновити README/AGENTS на нову схему, де canonical audit-файл — `FULL_PROJECT_AUDIT.md`.

### M-05. `self_check.php` перевіряє менше, ніж обіцяє документація

- Рівень: Medium
- Статус: виправлено 2026-06-13.
- Файли і рядки: `tools/self_check.php:149`, `tools/self_check.php:150`, `README.md:56`, `IMPLEMENTED_FEATURES.md:89`
- Опис: документація каже, що `self_check.php` перевіряє структуру, модулі, конфіги і доступи. Фактично tool перевіряє CSRF і EXIF Orientation.
- Як відтворити: прочитати `tools/self_check.php` або запустити `php tools/self_check.php`.
- Ризик: помилкове відчуття, що storage permissions, DB, upload folders і production guards перевірені.
- Наслідки: помилки середовища можуть пройти непоміченими перед deploy.
- Рекомендоване виправлення: або розширити `self_check.php`, або чесно звузити опис у README/IMPLEMENTED_FEATURES.

### M-06. `cleanup_orphans.php` виводить confusing path для legacy originals

- Рівень: Medium
- Статус: виправлено 2026-06-13.
- Файл і рядки: `tools/cleanup_orphans.php:68`, `tools/cleanup_orphans.php:76`, `tools/cleanup_orphans.php:94`
- Опис: tool сканує `uploads_path('originals')`, але виводить шлях як `public\originals\...`, без сегмента `uploads`.
- Як відтворити: запустити `php tools/cleanup_orphans.php` при наявності legacy originals.
- Ризик: оператор може шукати або вручну чистити не ту директорію.
- Наслідки: людська помилка під час cleanup/migration.
- Рекомендоване виправлення: для `public:*` виводити `public/uploads/<folder>/<filename>`.

## 4. Low Findings

### L-01. SQL-файли жорстко привʼязані до `my_photo_gallery`

- Рівень: Low
- Статус: виправлено 2026-06-13.
- Файли і рядки: `database/schema.sql:1`, `database/schema.sql:5`, `database/migrations/2026_06_12_add_albums.sql:1`, `database/migrations/2026_06_13_hardening.sql:1`
- Опис: schema/migrations використовують hard-coded DB name.
- Ризик: якщо `config/database.php` або env використовує іншу `DB_NAME`, migration може піти не в ту БД.
- Наслідки: помилки deploy або зміни не в тій базі.
- Рекомендоване виправлення: зробити SQL portable: без `USE`, або окремий setup-wrapper, який підставляє DB name із конфігу.

### L-02. Upload cleanup після DB-помилки не перевіряє результат `unlink()`

- Рівень: Low
- Статус: виправлено 2026-06-13.
- Файл і рядки: `public/admin/upload.php:150`, `public/admin/upload.php:154`, `public/admin/upload.php:158`
- Опис: якщо файл створено, але DB insert/transaction впав, cleanup викликає `unlink()` без перевірки результату.
- Ризик: orphan-файли після рідкісних помилок прав або filesystem.
- Наслідки: зайве місце, неконсистентність storage/public.
- Рекомендоване виправлення: додати helper для безпечного unlink із логуванням помилок.

### L-03. Admin-сесія не перевіряє, що admin досі існує в БД

- Рівень: Low
- Статус: виправлено частково 2026-06-13: `admin_id` перевіряється в БД; `session_version` лишається майбутнім покращенням, якщо зʼявиться зміна пароля.
- Файл і рядки: `app/includes/auth.php:15`, `app/includes/auth.php:19`, `app/includes/auth.php:27`
- Опис: сесія вважається валідною, якщо є `admin_id` і не сплив idle timeout.
- Ризик: якщо в майбутньому додати зміну пароля/видалення адміна, старі сесії не інвалідовуватимуться автоматично.
- Наслідки: слабше керування активними сесіями.
- Рекомендоване виправлення: додати перевірку admin row або `session_version` / `password_changed_at`.

### L-04. Trash manifest зберігає абсолютні filesystem paths

- Рівень: Low
- Статус: виправлено 2026-06-13.
- Файл і рядки: `app/includes/functions.php:1147`, `app/includes/functions.php:1148`, `app/includes/functions.php:1152`
- Опис: manifest у `storage/trash` містить абсолютні шляхи `from` і `trash`.
- Ризик: якщо backup/release помилково включить `storage/trash`, будуть розкриті локальні шляхи сервера.
- Наслідки: path disclosure і привʼязка manifest до конкретної машини.
- Рекомендоване виправлення: зберігати відносні storage/upload identifiers, а абсолютні шляхи будувати під час recovery.

### L-05. `setup.php` використовує shell-виклики для прихованого вводу пароля

- Рівень: Low
- Статус: виправлено 2026-06-13.
- Файл і рядки: `tools/setup.php:39`, `tools/setup.php:50`, `tools/setup.php:52`
- Опис: `shell_exec()` / `system()` знайдено тільки в CLI setup tool, не у web runtime.
- Ризик: залежність від PowerShell/stty і security-політик хоста.
- Наслідки: setup може не працювати на locked-down середовищі.
- Рекомендоване виправлення: залишити як documented CLI-only компроміс або зробити fallback через `readline()` із попередженням, якщо прихований ввід недоступний.

### L-06. Немає responsive images (`srcset`/`sizes`)

- Рівень: Low
- Статус: виправлено 2026-06-13.
- Файли і рядки: `public/gallery.php:184`, `public/admin/index.php:193`, `public/photo.php:94`
- Опис: UI використовує thumbnails/large, `loading="lazy"` є, але немає `srcset`/`sizes`.
- Ризик: на великих або retina-екранах якість/вага може бути неоптимальною.
- Наслідки: зайвий трафік або гірша якість у деяких viewport.
- Рекомендоване виправлення: додати генерацію 2-3 web-розмірів і `srcset`; пункт уже є в roadmap.

### L-07. Пошук через `LIKE` без FULLTEXT може деградувати на великій галереї

- Рівень: Low
- Статус: виправлено 2026-06-13.
- Файли і рядки: `public/gallery.php:65`, `public/gallery.php:66`, `public/admin/index.php:65`
- Опис: пошук по назві/опису використовує `LIKE '%term%'`.
- Ризик: при тисячах/десятках тисяч фото запити стануть повільнішими.
- Наслідки: повільна галерея та адмінка на великій базі.
- Рекомендоване виправлення: для великого архіву додати FULLTEXT-індекс або окрему стратегію пошуку; пункт уже є в roadmap.

## 5. Info / Environment Notes

### I-01. PHP lint пройшов для всіх PHP-файлів, але Xdebug має проблему з логом

- Рівень: Info
- Місце: локальне Wamp/PHP середовище, `c:/wamp64/logs/xdebug.log`
- Факт: `php -l` для 24 PHP-файлів не знайшов syntax errors, але кожен запуск показував warning від Xdebug про неможливість відкрити log file.
- Рекомендація: створити/дати права на `c:/wamp64/logs/xdebug.log` або вимкнути Xdebug для CLI, щоб перевірки були чистими.

### I-02. JavaScript syntax check пройшов

- Рівень: Info
- Файл: `public/assets/js/main.js`
- Факт: `node --check public/assets/js/main.js` завершився без помилок.

### I-03. Основні таблиці БД існують

- Рівень: Info
- Перевірено: `admins`, `albums`, `photos`, `login_attempts`
- Факт: DB-check через локальний PHP-конфіг показав `ok` для всіх чотирьох таблиць.

### I-04. HTTP smoke-tests через `http://mygallery/` недоступні в момент аудиту

- Рівень: Info
- Факт: `Invoke-WebRequest http://mygallery/` отримав connection refused.
- Наслідок: реальні HTTP headers/redirects/status pages не перевірені через браузерний сервер.
- Рекомендація: повторити smoke-tests після запуску Apache/Wamp VirtualHost.

### I-05. Секрети і публічні службові файли

- Рівень: Info
- Факт: `config/database.php` і media/storage файли ігноруються Git; `git ls-files config/database.php` не показав tracked-файл. У `public/` не знайдено `.env`, `.sql`, `.log`, `database.php`, session-файлів або backup-архівів.
- Рекомендація: перед release повторити `git status --short --ignored` і не пакувати `config/database.php`, `storage/*`, реальні uploads.

## 6. Security Review

Позитивні результати:

- SQL Injection: у перевірених місцях використовуються prepared statements або whitelist для `ORDER BY`.
- XSS: HTML-вивід здебільшого йде через `h()`, яка використовує `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- CSRF: POST-дії login/logout/upload/edit/delete/albums мають `verify_csrf()`.
- Login: є rate limiter через `login_attempts`, generic error для неправильного логіна, `password_hash()` / `password_verify()`.
- Sessions: є `session_regenerate_id(true)` після login, приватна `storage/sessions`, `httponly`, `samesite=Lax`, strict mode.
- Upload: MIME перевіряється через `finfo_file()`, додатково через `getimagesize()`, є ліміти 30 MB, 8000x8000, 50 MP, random filenames.
- Upload execution: `public/uploads/.htaccess` блокує PHP-like extensions.

Основні security gaps із High/Medium/Low секцій закриті 2026-06-13. Залишкові майбутні покращення: `session_version` / `password_changed_at`, якщо зʼявиться зміна пароля.

## 7. Architecture / Structure Review

Поточна структура відповідає цільовій ідеї:

- `public/` є web-root;
- `app/`, `config/`, `database/`, `tools/`, `storage/` не мають бути доступні напряму;
- нові оригінали мають іти в `storage/originals`;
- optimized large і thumbnails ідуть у `public/uploads`.

Проблема: фактичні legacy originals у `public/uploads/originals` ще не прибрані, а документація/roadmap уже правильно попереджає, що це треба закрити.

## 8. Upload / EXIF / Images

Перевірено:

- JPEG-only policy є.
- `finfo_file()` і `getimagesize()` є.
- Не використовується оригінальна назва як server filename.
- EXIF читається обережно, без показу warning у UI.
- EXIF Orientation 1-8 покритий self-check.
- Large/thumbnail створюються через GD.

Стан після виправлень:

- legacy originals перенесені в `storage/originals`;
- responsive `srcset`/`sizes` додано;
- cleanup після rare upload failure логуватиме невдалі `unlink`.

## 9. Database / SQL

Поточні таблиці на локальній БД існують. PDO-конфіг у коді використовує `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `EMULATE_PREPARES=false`, `utf8mb4`.

Стан після виправлень: SQL-файли portable і не містять `USE my_photo_gallery`; README-команди явно передають DB name у `mysql`.

## 10. Git / Ignore / Release Hygiene

Перевірено:

- `config/database.php` ігнорується.
- `public/uploads/large/*`, `public/uploads/thumbnails/*`, `storage/originals/*`, `storage/logs/*`, `storage/sessions/*`, `.env` ігноруються.
- У Git worktree зараз є видалені docs: `AUDIT_REPORT.md`, `BUGS.md`, `FIXES_APPLIED.md`.

Рекомендація: перед першим/наступним комітом вирішити, які audit/bugs/fixes файли є canonical, і привести README/AGENTS до цього стану.

## 11. Checks Actually Run

- `php -l` для всіх PHP-файлів.
- `node --check public/assets/js/main.js`.
- `git status --short --ignored`.
- `git ls-files config/database.php`.
- `git check-ignore -v` для DB config, uploads, storage, logs, `.env`.
- Пошук небезпечних патернів: `shell_exec`, `system`, `eval`, `unserialize`, `innerHTML`, `document.write`, `mysqli`.
- Перевірка public sensitive files у `public/`.
- `php tools/self_check.php`.
- `php tools/cleanup_orphans.php`.
- DB table check через PHP/PDO без виводу секретів.
- Спроба HTTP smoke-test `http://mygallery/`.

## 12. Checks Not Completed

- HTTP smoke-tests сторінок не завершені, бо локальний `http://mygallery/` не відповідав.
- Не перевірявся реальний browser rendering після аудиту.
- Не виконувалися destructive tools з `--apply`, `--delete`, `--purge-deleted`.
- Не запускалася SQL migration на чистій БД, щоб не змінювати середовище під час аудиту.
- Не перевірялися реальні production Apache/Nginx headers, бо production server недоступний.

## 13. Recommended Fix Order

1. Запустити `php tools/cleanup_orphans.php`, коли MySQL/MariaDB буде доступний, щоб підтвердити DB/file consistency.
2. Застосувати оновлену `database/migrations/2026_06_13_hardening.sql` на існуючій БД, щоб додати FULLTEXT indexes.
3. Якщо зʼявиться зміна пароля адміністратора, додати `session_version` або `password_changed_at`.
4. Для production Nginx додати окремий deny-приклад для `public/uploads/originals`.

## 14. Files Recommended To Change Later

- `tools/migrate_legacy_originals.php`
- `tools/cleanup_orphans.php`
- `tools/recover_trash.php`
- `public/admin/albums.php`
- `public/admin/upload.php`
- `app/includes/functions.php`
- `app/includes/auth.php`
- `database/schema.sql`
- `database/migrations/2026_06_12_add_albums.sql`
- `database/migrations/2026_06_13_hardening.sql`
- `README.md`
- `AGENTS.md`
- `IMPLEMENTED_FEATURES.md`
- `POST_MVP_ROADMAP.md`

## 15. Conclusion

Проєкт уже має нормальний MVP-рівень безпеки для локального використання: prepared statements, CSRF, rate limit, private storage intent, upload validation і базові security headers. Перед production найбільше треба закрити файлову консистентність legacy originals, harden recovery tools, додати production HTTPS/HSTS strategy і привести документацію до фактичного Git-стану.
