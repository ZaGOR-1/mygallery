# Full Project Audit

## 1. Executive Summary

Фактична версія проєкту: `6.4.20` з файлу `VERSION`. Перевірена гілка: `beta`, стан Git: робоча папка синхронізована з `origin/beta`, але це не clean release, бо є `.git`, untracked `provirka.md`, tracked runtime-файл `storage/share_ratelimit/limit_...json` і локальні `storage/test_sessions/sess_*`. Проєкт має зрілу plain-PHP архітектуру без Laravel/Composer/React/Vue і містить багато правильних security-рішень: PDO prepared statements, CSRF, hardened sessions, MIME/getimagesize upload validation, private originals, release builder, backup/restore validation і production guards.

Production-ready статус зараз: **Not ready**. Головний блокер - приватність: `public/index.php` може показувати приватні фото на головній сторінці і рахувати приватні фото/альбоми/камери у публічній статистиці. Також публічні filter options можуть розкривати теги/камери приватних фото, а релізна гігієна має помилку: runtime-файл share rate-limit відстежується Git і не виключається release builder-ом. Автоматично підтверджено тільки JS syntax check; PHP lint, unit tests, self-check і build release не запускались, бо `php` не доступний у цьому середовищі.

Загальна оцінка: кодова база сильна для студентського plain-PHP MVP, але перед production потрібно виправити privacy leaks, release hygiene, `schema_migrations` mismatch і backup verification behavior. Після цього проєкт виглядатиме близьким до production для невеликої персональної галереї.

## 2. Audit Scope

- PHP-файлів: 58.
- SQL-файлів: 11.
- JS-файлів: 1.
- CSS-файлів: 1.
- Markdown-файлів: 33.
- YAML/YML-файлів: 1.
- Перевірені папки: `app/`, `config/`, `database/`, `public/`, `public/admin/`, `public/assets/`, `public/uploads/`, `storage/`, `tests/`, `tools/`, `docs/`, `.gemini/agents/`, `.github/workflows/`.
- Обов'язкові tools існують: `tools/setup.php`, `migrate.php`, `self_check.php`, `build_release.php`, `backup.php`, `verify_backup.php`, `restore.php`, `cleanup_orphans.php`, `cleanup_runtime.php`, `migrate_legacy_originals.php`, `recover_trash.php`, `regenerate_images.php`, `backfill_sha256.php`, `tools/lib/SimpleZipWriter.php`.
- `tests/` існує: 9 unit-тестів плюс `tests/run.php` і `tests/bootstrap.php`.
- `.github/workflows/build_release.yml` існує.
- `AGENTS.md`, `CLAUDE.md`, `GEMINI.md` існують.

Команди, які запускались:

- `git pull --ff-only origin beta` - гілка вже була up to date.
- `git status --short --branch` - `## beta...origin/beta`, untracked `provirka.md`.
- `rg --files`, `rg -n ...`, `Get-Content ...` - статичний аудит структури і коду.
- `node --check public/assets/js/main.js` через bundled Node.js - пройшло без помилок.
- `php -v` - не виконалось: `php` не знайдений у PATH.
- `node --version` у PATH - не знайдений, але bundled Node.js доступний.
- `unzip` / `7z` - не знайдені; `tar` доступний.

Що не вдалося перевірити:

- `php -l` для PHP-файлів - PHP runtime недоступний.
- `php tests/run.php` - PHP runtime недоступний.
- `php tools/self_check.php` - PHP runtime недоступний.
- `php tools/build_release.php` - PHP runtime недоступний; також ця дія створює ZIP, а правила аудиту дозволяють змінювати тільки `FULL_PROJECT_AUDIT.md`.
- `unzip -t dist/*.zip` - релізний ZIP не створювався, `unzip` недоступний.
- Підключення до реальної БД, login/logout, upload JPEG, restore і manual browser flows - не запускались у цьому середовищі.

## 3. Severity Summary

| Severity | Count |
| -------- | ----: |
| Critical |     1 |
| High     |     5 |
| Medium   |     8 |
| Low      |     7 |
| Info     |     6 |

## 4. Critical Issues

### C1

- Severity: Critical
- Status: open
- File: `public/index.php:10`, `public/index.php:20`
- Description: публічна головна сторінка вибирає останні фото і статистику без privacy-фільтра по `albums.is_private`.
- Impact: фото з приватного альбому може стати hero/latest card на головній, а статистика може розкрити кількість приватних фото, альбомів, камер і дату останнього приватного upload.
- Reproduction scenario: створити приватний альбом, завантажити в нього нове фото, відкрити `/index.php` анонімно. Запит `SELECT ... FROM photos LEFT JOIN albums ... LIMIT 8` не містить `WHERE albums.is_private = 0 OR IS NULL`.
- Fix: у latest query додати `WHERE (albums.is_private IS NULL OR albums.is_private = 0)`. Статистику рахувати тільки по публічно доступних фото/альбомах через `LEFT JOIN albums` і той самий privacy filter. Краще винести helper для public photo scope.
- Tests to add: regression test, який створює public/private album/photo і перевіряє, що homepage query/helper не повертає приватне фото і не рахує приватні камери.
- Verification steps: анонімно відкрити головну, переконатися, що приватні фото/назви/камери не показуються; повторити після `php tests/run.php` і `php tools/self_check.php`.

## 5. High Issues

### H1

- Severity: High
- Status: open
- File: `app/includes/functions.php:656`, `app/includes/functions.php:926`, `public/gallery.php:73`
- Description: public filter options розкривають теги і camera models приватних фото; окремо `gallery.php?album_id=<private_id>` може показати назву приватного альбому в заголовку.
- Impact: навіть якщо приватні фото не показуються у grid, анонімний користувач може дізнатися приватні теги, камери або назву приватного альбому.
- Reproduction scenario: додати тег/камеру тільки до приватного фото; відкрити `/gallery.php` анонімно і подивитися dropdowns. Для назви альбому відкрити `/gallery.php?album_id=<id приватного альбому>`.
- Fix: зробити `get_tag_options($withCounts, $includePrivate)` і public-режим з `JOIN photos LEFT JOIN albums` + `(albums.is_private IS NULL OR albums.is_private = 0)`. Camera options також рахувати тільки з public scope. `selectedAlbumName` отримувати тільки для public album або shared authorized album.
- Tests to add: public filter privacy test для tags/cameras/album title.
- Verification steps: приватні-only теги/камери не з'являються у public filters; приватний `album_id` не показує назву, повертає нейтральний заголовок або 404.

### H2

- Severity: High
- Status: open
- File: `app/includes/file_functions.php:27`, `app/includes/photo_service.php:50`, `app/includes/photo_service.php:58`
- Description: optimized derivatives приватних фото зберігаються у `public/uploads/large` і `public/uploads/thumbnails` та віддаються як статичні URL.
- Impact: якщо URL приватного optimized image витік через homepage bug, share link, логи, referrer або кеш, PHP вже не може відкликати доступ до цього файла. Random filename зменшує ризик вгадування, але не дає revocable privacy.
- Reproduction scenario: отримати URL `/uploads/large/<random>.jpg` приватного фото і відкрити його напряму після відкликання share link або зміни album privacy.
- Fix: якщо приватність має бути відкличною, зберігати derivatives приватних альбомів поза `public`, віддавати через PHP controller з admin/share перевіркою або через `X-Accel-Redirect` / `X-Sendfile` після авторизації. Альтернатива: регенерувати filenames при переході public -> private.
- Tests to add: direct private derivative access test і share revoke regression.
- Verification steps: прямий URL приватного derivative без admin/share token повертає 404/403; share revoke реально блокує не тільки сторінку, а й image delivery.

### H3

- Severity: High
- Status: open
- File: `.gitignore:10`, `tools/build_release.php:48`, `tools/build_release.php:129`, `storage/share_ratelimit/limit_837ec5754f503cfaaee0929fd48974e7.json`
- Description: runtime-файл `storage/share_ratelimit/limit_...json` відстежується Git і `tools/build_release.php` не виключає `storage/share_ratelimit`.
- Impact: clean release ZIP може містити runtime-файли. Це порушує release hygiene і вимогу не пакувати share rate-limit state у реліз.
- Reproduction scenario: `git ls-files storage/share_ratelimit` показує tracked JSON. `release_should_exclude()` має `storage/test_sessions` і `storage/download_locks`, але не `storage/share_ratelimit`.
- Fix: додати `storage/share_ratelimit/*` у `.gitignore`, прибрати tracked runtime JSON з Git, додати `storage/share_ratelimit` у `$excludedDirs` і forbidden regex у `release_forbidden_reason()`.
- Tests to add: `release_exclusions_test.php` assertions для `storage/share_ratelimit/limit_x.json`.
- Verification steps: `git ls-files storage/share_ratelimit` порожній; release builder блокує або виключає share rate-limit файли; release ZIP не містить runtime JSON.

### H4

- Severity: High
- Status: open
- File: `database/schema.sql:1`, `tools/migrate.php:14`, `tools/backup.php:176`, `public/admin/health.php:126`, `tools/self_check.php:273`
- Description: `database/schema.sql` не створює `schema_migrations`, але `tools/backup.php` очікує цю таблицю в дампі. `tools/migrate.php` створює її сам, а health/self_check її не перевіряють.
- Impact: clean install через імпорт `database/schema.sql` без запуску `tools/migrate.php` може мати повну бізнес-схему, але backup впаде на `schema_migrations`. Health/self-check це не помітять.
- Reproduction scenario: імпортувати `database/schema.sql`, не запускати migrate, потім запустити `tools/backup.php`; export loop містить `'schema_migrations'`.
- Fix: додати `CREATE TABLE IF NOT EXISTS schema_migrations` у `database/schema.sql`; додати таблицю в health/self_check required tables.
- Tests to add: test, який читає `database/schema.sql` і перевіряє наявність `CREATE TABLE IF NOT EXISTS schema_migrations`.
- Verification steps: clean schema import + backup проходить; health/self_check перевіряють `schema_migrations`.

### H5

- Severity: High
- Status: open
- File: `tools/verify_backup.php:84`, `tools/verify_backup.php:96`
- Description: `verify_backup.php` лише друкує warning, якщо media-файлів у ZIP менше, ніж заявлено у manifest, але завершується `exit(0)` і пише "успішна".
- Impact: пошкоджений або неповний backup може вважатися валідним. Це ризик втрати даних при production restore.
- Reproduction scenario: backup manifest очікує 10 originals, ZIP містить 9; verify показує warning, але повертає success code.
- Fix: при будь-якій невідповідності manifest vs actual повертати non-zero exit code. Бажано переюзати частину strict validation з `tools/restore.php`.
- Tests to add: synthetic ZIP/manifest test з missing media, який очікує exit code `1`.
- Verification steps: пошкоджений backup fail-иться; валідний backup проходить.

## 6. Medium Issues

### M1

- Severity: Medium
- Status: open
- File: `README.md:15`, `database/schema.sql:56`, `app/includes/functions.php:306`, `public/gallery.php:109`, `public/admin/index.php:90`
- Description: README каже, що пошук працює за назвою, описом і назвою файла, але public gallery шукає тільки `title` і `description`. Admin search включає `original_name`.
- Impact: публічна функція не відповідає опису MVP/README; користувач не знайде фото за original filename.
- Reproduction scenario: завантажити фото з original name `nikon_trip_001.jpg`, не вказувати це в title/description, шукати `nikon_trip` у public gallery.
- Fix: або реалізувати public search по `original_name` тільки для public-scoped photos, або уточнити README, що filename search доступний лише в адмінці.
- Tests to add: public search by original_name for public photo; private original_name must not leak.
- Verification steps: public search знаходить public filename і не знаходить private filename.

### M2

- Severity: Medium
- Status: open
- File: `public/share.php:62`, `public/share.php:72`, `public/download_album.php:93`, `public/download_album.php:98`
- Description: share token перевіряється тільки на non-empty, але не на формат/довжину перед DB query.
- Impact: токени генеруються як 32 hex chars, але endpoint приймає будь-який довгий рядок. Це не SQL injection через prepared statements, але зайва поверхня для DB load і логічна слабкість валідації.
- Reproduction scenario: запит `/share.php?token=` + дуже довгий рядок виконає DB lookup замість швидкого 404.
- Fix: `if (!preg_match('/\A[a-f0-9]{32}\z/', $token))` -> 404 до DB.
- Tests to add: malformed token rejected before DB lookup.
- Verification steps: malformed token повертає 404; валідний generated token працює.

### M3

- Severity: Medium
- Status: open
- File: `public/share.php:7`, `public/download_album.php:14`, `public/admin/login.php:18`
- Description: share page і album ZIP cooldown використовують raw `REMOTE_ADDR`, тоді як login має `TRUSTED_PROXIES` helper.
- Impact: за reverse proxy rate-limit може або злипнути всіх користувачів в один IP, або бути неточним відносно реального client IP. Login вже вирішує це краще.
- Reproduction scenario: поставити застосунок за trusted proxy; login бачить client IP через `X-Forwarded-For`, share/download - ні.
- Fix: винести спільний `client_ip()` helper з trusted proxy логікою і використати для login/share/download.
- Tests to add: unit test для trusted proxy IP extraction.
- Verification steps: за proxy всі три rate-limit механізми бачать однаковий client IP.

### M4

- Severity: Medium
- Status: open
- File: `public/download_album.php:177`, `public/download_album.php:189`
- Description: ZIP cache key враховує album id, variant, album name, count і max dates, але не враховує filenames, `original_name`, `original_sha256`, file sizes або повний список `updated_at`.
- Impact: можливий stale ZIP після регенерації/заміни файлів або нетипових змін, які не змінять max timestamp. Це також ускладнює гарантії після privacy/share змін.
- Reproduction scenario: змінити файл на диску або metadata не так, щоб max `updated_at` змінився; повторний download може віддати старий cache.
- Fix: будувати cache fingerprint з масиву per-photo `id`, `filename`, `original_name`, `original_sha256`, `file_size`, `updated_at`, `created_at`, variant і album privacy/share scope.
- Tests to add: ZIP cache key changes when photo filename/hash/original_name/file_size changes.
- Verification steps: після metadata/file changes генерується новий ZIP.

### M5

- Severity: Medium
- Status: open
- File: `app/includes/auth_functions.php:66`, `public/.htaccess:15`, `public/gallery.php:199`, `public/photo.php:187`, `public/share.php:131`, `public/admin/index.php:159`
- Description: CSP `script-src 'self'` блокує inline handlers, але у коді залишились `onerror` на images і inline `onclick` для bulk delete confirmation.
- Impact: image fallback opacity handler не працює; bulk delete confirmation може не показуватися під CSP, що погіршує safety UX для масового видалення.
- Reproduction scenario: відкрити сторінку з DevTools console; CSP блокує inline handler. Натиснути "Масове видалення" - inline confirm залежить від CSP.
- Fix: перенести `onerror` і bulk delete confirmation у `public/assets/js/main.js` через `addEventListener` / `data-confirm`.
- Tests to add: static test на відсутність `onerror=`/`onclick=` у `public/*.php` і `public/admin/*.php`.
- Verification steps: CSP console clean; confirmation працює без inline JS.

### M6

- Severity: Medium
- Status: open
- File: `public/assets/css/style.css:1`, `app/includes/auth_functions.php:66`, `public/.htaccess:15`
- Description: CSS імпортує Google Fonts через `@import`, але CSP дозволяє styles тільки з `'self'`.
- Impact: browser блокує font CSS або робить зайвий external dependency, що суперечить self-contained plain-PHP MVP і може ламати очікувану типографіку.
- Reproduction scenario: відкрити сторінку з CSP enabled; stylesheet import на `https://fonts.googleapis.com` не дозволений `style-src 'self'`.
- Fix: прибрати `@import` і використовувати system stack або self-host fonts з відповідним `font-src 'self'`.
- Tests to add: static CSP test на відсутність external URLs у CSS або на узгоджений CSP.
- Verification steps: DevTools без CSP errors; візуальна типографіка стабільна offline.

### M7

- Severity: Medium
- Status: open
- File: `public/share.php:92`, `app/includes/header.php:15`
- Description: share pages не виставляють `X-Robots-Tag: noindex, noarchive` і не додають robots meta.
- Impact: якщо приватний share URL десь потрапить у crawler/referrer, сторінка не просить пошуковики не індексувати/не кешувати її.
- Reproduction scenario: відкрити share URL і перевірити headers/meta - `noindex` відсутній.
- Fix: у `public/share.php` перед render додати `header('X-Robots-Tag: noindex, noarchive', true)` і meta robots для HTML.
- Tests to add: header/meta assertion для share route.
- Verification steps: share response містить `X-Robots-Tag: noindex, noarchive`.

### M8

- Severity: Medium
- Status: open
- File: `docs/AI_RELEASE_AUDIT.md:5`, `docs/AI_FIX_PLAN.md:13`, `docs/AI_DOCS_CONSISTENCY_AUDIT.md:5`, `docs/AI_CODE_AUDIT.md:44`, `ROADMAP.md:7`, `ROADMAP.md:10`, `README.md:449`
- Description: документація містить застарілі версії `6.4.6`/`6.4.8`, overclaim на кшталт "ідеально"/"100% готова"/"PASS", старий release example `mygallery_6.4.6_release.zip`, а Roadmap має вже реалізований Drag-and-Drop Upload і видалений Dark/Light Mode як future task.
- Impact: майбутні аудитори/AI-агенти можуть спиратися на неправильні твердження і пропустити реальні проблеми.
- Reproduction scenario: `rg "6.4.6|6.4.8|ідеаль|100% готова|mygallery_6.4.6" docs README.md ROADMAP.md`.
- Fix: позначити старі AI-аудити як historical, прибрати overclaim, оновити release example на `<VERSION>`, синхронізувати Roadmap.
- Tests to add: docs consistency check для `VERSION` і заборонених overclaim phrases.
- Verification steps: `rg` по старих версіях показує лише історичний changelog або явно archived docs.

## 7. Low Issues

### L1

- Severity: Low
- Status: open
- File: `public/assets/js/main.js:147`, `public/assets/js/main.js:385`, `public/assets/js/main.js:472`
- Description: lightbox має `role="dialog"`, `aria-modal="true"` і фокус на close button, але не має focus trap для `Tab`.
- Impact: клавіатурний користувач може табом вийти за межі модального вікна.
- Reproduction scenario: відкрити photo lightbox, натискати `Tab`; фокус не циклічно утримується всередині dialog.
- Fix: додати trap для focusable elements у lightbox і повернення фокусу на trigger після close.
- Tests to add: browser/manual accessibility checklist для Tab/Shift+Tab/Escape.
- Verification steps: Tab і Shift+Tab цикляться всередині lightbox; Escape закриває і повертає фокус.

### L2

- Severity: Low
- Status: open
- File: `public/assets/css/style.css:1375`, `public/assets/css/style.css:1459`
- Description: filter panel на екранах до 760px має 2 колонки і стає 1 колонкою тільки до 480px.
- Impact: date/select поля можуть бути тісними на середніх мобільних ширинах.
- Reproduction scenario: перевірити gallery filters на 500-760px viewport.
- Fix: зробити одну колонку раніше або збільшити мінімальну ширину полів.
- Tests to add: responsive screenshot/manual checklist для 360/480/640/760px.
- Verification steps: поля не стискаються, текст і select не обрізаються.

### L3

- Severity: Low
- Status: open
- File: `tools/cleanup_runtime.php:14`, `README.md:421`
- Description: cleanup runtime чистить `logs`, `sessions`, `trash`, але не `storage/share_ratelimit` і не окремо `storage/download_locks`.
- Impact: runtime state може накопичуватися; `share_ratelimit` уже потрапив у Git.
- Reproduction scenario: створити старий файл у `storage/share_ratelimit`; `tools/cleanup_runtime.php --apply` його не зачепить.
- Fix: додати `share_ratelimit` і `download_locks` до cleanup з safe age.
- Tests to add: cleanup_runtime dry-run test для цих директорій.
- Verification steps: старі runtime files у цих папках видаляються, `.gitkeep` лишається.

### L4

- Severity: Low
- Status: open
- File: `app/includes/functions.php:530`, `app/includes/photo_service.php:267`, `database/schema.sql:14`
- Description: album cover consistency "cover photo belongs to album" enforced у application layer, але не в DB constraint.
- Impact: якщо БД змінити напряму, public album може мати cover_photo_id на фото з іншого альбому. Через admin UI це блокується, тому ризик низький.
- Reproduction scenario: вручну оновити `albums.cover_photo_id` на photo з іншого album_id і відкрити public albums page.
- Fix: залишити як application invariant або додати periodic self_check для cover consistency.
- Tests to add: self_check/health warning для cover_photo_id не зі свого album.
- Verification steps: неконсистентна cover reference показує health warning.

### L5

- Severity: Low
- Status: open
- File: `app/includes/photo_service.php:47`
- Description: upload flow примусово робить `ini_set('memory_limit', '512M')`.
- Impact: це може не спрацювати на hosting або суперечити production policy. При цьому в проєкті вже є `validate_gd_memory_limit()`.
- Reproduction scenario: запуск на хостингу, де `ini_set(memory_limit)` заборонений або memory policy нижча.
- Fix: зробити це конфігурованим або прибрати, покладаючись на documented PHP config і `validate_gd_memory_limit()`.
- Tests to add: test/inspection, що upload не залежить від успішного `ini_set`.
- Verification steps: upload large JPEG поводиться передбачувано при нижчому memory limit.

### L6

- Severity: Low
- Status: open
- File: `public/.htaccess:21`, `public/uploads/.htaccess:10`
- Description: cache headers явно задані для `jpg|jpeg`, але не для generated `webp|avif`.
- Impact: WebP/AVIF derivatives можуть кешуватися менш ефективно залежно від Apache defaults.
- Reproduction scenario: запросити `.webp` або `.avif` derivative і перевірити `Cache-Control`.
- Fix: додати `webp|avif` у FilesMatch/Expires rules.
- Tests to add: manual header checklist або static `.htaccess` test.
- Verification steps: JPEG/WebP/AVIF мають однакову бажану cache policy.

### L7

- Severity: Low
- Status: open
- File: `public/share.php:8`, `public/share.php:18`
- Description: якщо `storage/share_ratelimit` не створюється або файл не відкривається, rate limit тихо не застосовується.
- Impact: при помилці прав доступу share endpoint лишається без rate-limit, хоча сторінка продовжує працювати.
- Reproduction scenario: зробити `storage/` readonly і відкрити share URL багато разів.
- Fix: логувати failure і або fail-closed для invalid token traffic, або показувати health warning про `storage/share_ratelimit`.
- Tests to add: self_check для writable share_ratelimit.
- Verification steps: health/self_check попереджає про недоступний rate-limit storage.

## 8. Informational / Improvements

### I1

- Severity: Info
- Status: verified not current
- File: `app/includes/functions.php:412`, `public/gallery.php:231`, `public/admin/index.php:230`
- Description: попередня проблема "пагінація виводить усі сторінки" не підтвердилась у поточній beta. Є `pagination_window()` з gaps.

### I2

- Severity: Info
- Status: verified not current
- File: `public/admin/albums.php:30`, `public/admin/albums.php:76`
- Description: попередня проблема rollback через повторний `db()` у catch не підтвердилась у поточному файлі. POST actions використовують service functions і catch без rollback у цьому route.

### I3

- Severity: Info
- Status: accepted legacy
- File: `app/includes/file_functions.php:843`, `tools/migrate_legacy_originals.php:14`, `public/uploads/originals/.htaccess:1`
- Description: `public/uploads/originals` залишено як legacy path. Це не виглядає active security hole, бо `.htaccess` має `Require all denied`, але варто тримати це як migration-only сумісність.

### I4

- Severity: Info
- Status: pass with notes
- File: `app/includes/functions.php:154`, `app/includes/functions.php:306`, `app/includes/functions.php:873`, `public/admin/bulk_edit.php:68`
- Description: SQL injection review не знайшов сирої склейки user input у критичних public/admin flows. Dynamic sort whitelist-иться, LIMIT/OFFSET bind-яться як int, dynamic IN у bulk_edit використовує placeholders.

### I5

- Severity: Info
- Status: pass with notes
- File: `app/includes/csrf.php:33`, `app/includes/auth.php:62`, `public/admin/login.php:181`
- Description: CSRF/auth/session рішення сильні: empty token reject, `hash_equals`, one-time token consumption, `session_regenerate_id(true)`, idle timeout, `session_version`, dummy password hash, login buckets username+IP/account/IP.

### I6

- Severity: Info
- Status: pass with notes
- File: `app/includes/photo_service.php:11`, `app/includes/photo_service.php:18`, `app/includes/file_functions.php:46`, `public/uploads/.htaccess:1`
- Description: upload/media pipeline добре захищений: `finfo_file()`, `getimagesize()`, JPEG-only, random filenames, SHA-256 duplicate detection, GD re-encode, EXIF orientation 1-8, cleanup of partial derivatives, uploads PHP execution denied.

## 9. Security Review

- SQL injection: прямих SQLi не підтверджено. Prepared statements використовуються у public/admin flows; sort whitelist у `normalize_gallery_filters()`; FULLTEXT boolean query очищає оператори у `fulltext_boolean_query()`.
- XSS/CSP: output escaping через `h()` з `ENT_QUOTES` є системним плюсом. Залишились CSP-несумісні inline handlers і Google Fonts import, описані в M5/M6.
- CSRF: усі state-changing admin POST routes мають `require_csrf()`: login, logout, upload, edit, delete, bulk edit/delete, albums, tags, share, trash. Token не приймає empty value і перевіряється через `hash_equals`.
- Auth: `require_admin()` використовується в адмінці; login має dummy hash і rate-limit buckets. Username enumeration через error text не підтвердилась.
- Sessions: `session.use_strict_mode`, only cookies, no trans sid, `HttpOnly`, `SameSite=Lax`, conditional `Secure`, private `storage/sessions`, idle timeout і `session_version` invalidation є.
- Uploads: web-shell/double extension/MIME spoofing добре покриті через server-side MIME + image validation + random `.jpg` names + `.htaccess`.
- File access: path traversal значною мірою покритий `valid_photo_filename`, `realpath`, allowed directories, restore ZIP validation.
- Privacy: критичні проблеми є в homepage і public filter metadata; photo page і main gallery grid мають privacy filters.
- Share links: entropy добра (`bin2hex(random_bytes(16))`), expiry/revoke працюють через DB record, але token format validation/noindex/proxy IP треба поліпшити.
- Download album ZIP: access control для private album у public `album_id` branch є; share token дає доступ навмисно; non-admin отримує optimized copy. Cache key і verify behavior потребують фіксів.
- Backup/restore: backup не включає config без `--include-config`, restore має Zip Slip validation і confirmation. `verify_backup.php` має fail-open на missing media.
- Release ZIP: builder має багато правильних excludes, але пропускає `storage/share_ratelimit`.
- Production config: `APP_ENV=production` блокує `APP_DEBUG=true`, non-HTTPS `APP_URL`, root/no-password DB user. HSTS only production+HTTPS.

## 10. Architecture Review

Структура `app/public/config/storage/database/tools/tests/docs` логічна для plain PHP. `public/` задуманий як DocumentRoot, а `app/`, `config/`, `storage/`, `database/`, `tools/`, `backups/` не повинні бути web-accessible. Логіка upload/media винесена в `photo_service.php` і `file_functions.php`; auth/session/CSRF розділені; DB config окремий.

`app/includes/functions.php` став великим і містить різні домени: URL helpers, filters/search, albums, tags, gallery queries, runtime config. Це ще підтримувано для MVP, але privacy-scope logic, filter options, share-access logic і gallery query helpers варто поступово рознести на менші include-файли без створення важкого фреймворку.

Найбільш потрібна архітектурна зміна: єдиний helper для public photo scope і filter options. Зараз homepage, gallery, tags/cameras і album title selection мають різні privacy decisions, через що й виникли витоки.

## 11. Privacy Review

- Private albums: модель є, `albums.is_private` у schema і migration існує.
- Homepage leakage: **critical open** - private photos/stats не фільтруються.
- Gallery leakage: grid/count fetch правильно виключають private albums через `build_gallery_where_clause()`, але filter options і selected album name leak лишаються.
- Public albums page: використовує `get_public_albums_with_covers()` і ховає private albums.
- Photo page: приватне фото без admin повертає 404; prev/next для аноніма фільтрує private.
- Filter options leakage: **high open** - tags/cameras рахуються без privacy join.
- Direct static derivative URLs: **high design risk** - unguessable URL, але не revocable authorization.
- Share access: expired/revoked DB links блокуються; share photo/album може відкривати private content навмисно.
- ZIP download access: public private album блокується; share/admin дозволяється; non-admin отримує optimized files, не originals.

## 12. Database Review

Schema використовує `utf8mb4`, InnoDB, foreign keys, indexes для `taken_at`, `created_at`, `album_id`, `camera_model`, `title`, FULLTEXT для public/admin search, unique constraints для filenames, thumbnails, `original_sha256`, tags і share token.

Таблиці у `schema.sql`: `admins`, `albums`, `photos`, `tags`, `photo_tags`, `login_attempts`, `share_links`. Відсутня `schema_migrations`, хоча `tools/migrate.php` її створює і `tools/backup.php` очікує. Це головний database mismatch.

Migrations виглядають idempotent-oriented: використовують `IF NOT EXISTS`, `information_schema`, prepared DDL variables. `tools/migrate.php` чесно документує, що MySQL DDL не атомарний, і не записує migration як applied при failure.

MySQL/MariaDB compatibility загалом добра, але FULLTEXT і JSON тип треба перевірити на цільовій MariaDB 10.6+ вручну.

## 13. Tools Review

- `setup.php`: CLI-only, створює/оновлює admin через `password_hash`, не повинен лежати у public.
- `migrate.php`: CLI-only, створює `schema_migrations`, запускає SQL migrations по порядку.
- `self_check.php`: перевіряє extensions, folders, schema indexes, CSRF; треба додати `schema_migrations` і `share_ratelimit`.
- `build_release.php`: має strong excludes для `.git`, `dist`, `backups`, sessions/logs/trash/originals/uploads media; треба додати `storage/share_ratelimit`.
- `backup.php`: не включає config без `--include-config`, блокує output у `public/`, включає DB і media; залежить від `schema_migrations`.
- `verify_backup.php`: має strict checks для SQL/manifest JSON, але fail-open на media count mismatch.
- `restore.php`: сильна pre-validation, Zip Slip protection, confirmation `RESTORE`, DB transaction for new-format dumps, media wipe after DB success.
- `cleanup_orphans.php`: dry-run by default, delete only with `--delete`, safe filename/path helpers.
- `cleanup_runtime.php`: dry-run by default, але не чистить share rate-limit.
- `migrate_legacy_originals.php`: dry-run by default, доречний для legacy `public/uploads/originals`.
- `recover_trash.php`: dry-run by default, корисний після аварійного delete.
- `regenerate_images.php`: має dry-run, працює з originals/legacy.
- `backfill_sha256.php`: CLI-only, backfill для duplicate detection.

## 14. Tests Review

Наявні unit tests: `album_privacy_test.php`, `backup_restore_test.php`, `dominant_color_test.php`, `download_album_test.php`, `exif_test.php`, `paths_test.php`, `release_exclusions_test.php`, `share_links_test.php`, `tags_test.php`.

Що покрито добре:

- EXIF helper parsing.
- Safe paths/traversal basics.
- Tag parsing.
- Album privacy helper basics.
- Release exclusions basics.
- Backup/restore static expectations.
- Share links schema basics.
- ZIP filename sanitization.
- Dominant color helper.

Що треба додати:

- Homepage privacy leak regression.
- Public filter options privacy for tags/cameras.
- Private album title leak via `gallery.php?album_id=...`.
- Static derivative access model tests.
- Share malformed token validation.
- Share noindex headers.
- ZIP cache key changes on filename/hash/size/update changes.
- `verify_backup.php` non-zero exit on missing media.
- `database/schema.sql` contains `schema_migrations`.
- CSP static test: no `onerror=`, `onclick=`, external `@import`.
- Upload validation for fake JPEG/large JPEG/duplicate upload.
- Bulk edit tags regression.
- Migration idempotency smoke test.

У цьому середовищі `php tests/run.php` не запускався, бо PHP недоступний.

## 15. Documentation Review

`README.md` загалом докладний і корисний: WampServer, LAMP/Proxmox, DB import, PHP modules, Linux permissions, Apache VirtualHost, migration Windows->Linux, backup, debug off, HTTPS future описані. Але є старий release example `mygallery_6.4.6_release.zip`.

`CHANGELOG.md` актуальний до `v6.4.20`, але історичні згадки старих версій нормальні в changelog.

`ROADMAP.md` не синхронний: Drag-and-Drop Upload уже реалізований, Dark/Light Mode був реалізований/потім видалений у `v6.4.20`, але досі стоїть як future task.

`docs/*.md` містить багато старих AI-аудитів із overclaim: "PASS", "ідеально", "100% готова", "production". Їх треба явно позначити historical або архівними, інакше вони суперечать поточному аудиту.

`.gemini/agents/*.md`, `AGENTS.md`, `CLAUDE.md`, `GEMINI.md` загалом узгоджені в правилах безпеки і не вимагають вигадувати проблеми. Але `GEMINI.md` має згадку про uploaded `v6.4.6 workspace`, що застаріло.

`.github/workflows/build_release.yml` запускає PHP 8.4 і `php tools/build_release.php`, але CI trigger тільки для `main/master`; якщо робота йде у `beta`, release build не перевіряється на push у beta.

## 16. Positive Security Decisions

- Prepared statements і PDO config: `app/includes/db.php:41` з `ERRMODE_EXCEPTION`, `FETCH_ASSOC`, `EMULATE_PREPARES=false`.
- HTML escaping: `app/includes/functions.php:154` використовує `htmlspecialchars(..., ENT_QUOTES, 'UTF-8')`.
- CSRF: `app/includes/csrf.php:33` reject empty token, multiple one-time tokens, `hash_equals`.
- Session hardening: `app/includes/auth_functions.php:39` strict mode, only cookies, trans sid off, private session path.
- Login hardening: `public/admin/login.php:46` buckets username+IP/account/IP; `dummy_password_hash()` проти timing enumeration.
- Session fixation protection: `app/includes/auth.php:65` `session_regenerate_id(true)` після login.
- Session invalidation: `app/includes/auth.php:33` перевіряє `session_version`.
- Upload validation: `app/includes/photo_service.php:11` `finfo_file`, `:18` `getimagesize`, JPEG-only.
- Random filenames: `app/includes/file_functions.php:310` `random_bytes(16)`.
- Duplicate detection: `app/includes/photo_service.php:29` SHA-256.
- Private originals: originals path у `storage/originals`, public download originals тільки admin у `public/download_album.php:183`.
- Path traversal protection: `valid_photo_filename()`, `realpath` checks у `app/includes/file_functions.php`.
- Upload PHP execution denied: `public/uploads/.htaccess:1`.
- Legacy public originals denied: `public/uploads/originals/.htaccess:1`.
- Production guards: `app/includes/functions.php:97` rejects debug/non-HTTPS production, `app/includes/db.php:15` rejects root/no password DB in production.
- Restore validation: `tools/restore.php:30`, `:159`, `:210`, `:225`.
- Release builder forbidden checks: `tools/build_release.php:123`.

## 17. Recommended Fix Order

1. Priority: Critical, complexity: M, affected files: `public/index.php`, maybe new helper in `app/includes/functions.php`.
   Verification steps: homepage never shows private photos/stats; add regression test.

2. Priority: High, complexity: M, affected files: `app/includes/functions.php`, `public/gallery.php`.
   Verification steps: private-only tags/cameras/album names absent from public UI.

3. Priority: High, complexity: S, affected files: `.gitignore`, `tools/build_release.php`, `tests/unit/release_exclusions_test.php`, remove tracked `storage/share_ratelimit/*`.
   Verification steps: release ZIP excludes runtime; Git no longer tracks runtime JSON.

4. Priority: High, complexity: S, affected files: `database/schema.sql`, `public/admin/health.php`, `tools/self_check.php`, tests.
   Verification steps: clean schema includes `schema_migrations`; backup works after clean install.

5. Priority: High, complexity: S/M, affected files: `tools/verify_backup.php`, tests.
   Verification steps: missing media mismatch returns non-zero.

6. Priority: Medium, complexity: S, affected files: `public/share.php`, `public/download_album.php`.
   Verification steps: malformed tokens rejected before DB; valid tokens still work.

7. Priority: Medium, complexity: M, affected files: `public/download_album.php`.
   Verification steps: ZIP cache invalidates when relevant photo fields/files change.

8. Priority: Medium, complexity: S, affected files: `public/assets/js/main.js`, `public/*.php`, `public/admin/index.php`, `public/assets/css/style.css`.
   Verification steps: no CSP console errors; no inline handlers; font import resolved.

9. Priority: Medium, complexity: M/L, affected files: `app/includes/file_functions.php`, image delivery routes, docs.
   Verification steps: private derivatives are protected or privacy model documented as unguessable but not revocable.

10. Priority: Low/Docs, complexity: S, affected files: `README.md`, `ROADMAP.md`, `docs/*.md`, `GEMINI.md`.
    Verification steps: docs no longer overclaim production/PASS and versions match `VERSION`.

## 18. Regression Test Checklist

- Login/logout.
- Invalid login.
- Login rate limiter: username+IP, username-wide, IP-wide.
- Upload valid JPEG.
- Upload invalid file: `.txt`, `.php`, `.png`, fake JPEG.
- Upload large JPEG over app limit and over pixel/memory limits.
- Duplicate upload by SHA-256.
- EXIF orientation 1-8.
- Albums create/rename/delete without deleting photos.
- Private albums hidden from homepage, albums page, gallery, photo page, filters.
- Tags create/edit/delete/merge/prune.
- Bulk edit album/tags and bulk delete.
- Search/filter/sort by q/album/tag/camera/date.
- Pagination with filters preserved.
- Homepage privacy.
- Public filter privacy.
- Photo page private 404 and public ok.
- Prev/next excludes private for anonymous and includes for admin.
- Share link create/open/revoke/expire for photo and album.
- Share malformed token rejected.
- Share noindex/noarchive headers.
- Download original via admin only.
- Download album ZIP public/private/share/admin.
- ZIP cache invalidation.
- Trash delete/restore/purge.
- Cleanup orphans dry-run/delete.
- Regenerate images dry-run/apply.
- Backup/verify/restore, including missing-file verify failure.
- Build release and inspect ZIP contents.
- Self_check.
- `tests/run.php`.
- 404/500 pages.
- CSRF failure on every POST route.
- Unauthorized admin access redirects/blocks.
- Direct private original access denied.
- Direct private derivative access according to chosen model.

## 19. Production Readiness Verdict

Verdict: `Not ready`.

Що блокує production:

- Critical homepage privacy leak.
- Public filter metadata/private album title leakage.
- Static public derivative model не дає revocable privacy для приватних фото.
- Release builder/Git hygiene пропускає `storage/share_ratelimit`.
- `schema_migrations` mismatch може ламати backup після clean schema install.
- `verify_backup.php` може успішно прийняти неповний backup.
- PHP lint/tests/self_check/build_release не були фактично запущені у цьому середовищі.

Що можна виправити після публікації, якщо сайт лишається приватним/локальним:

- Focus trap lightbox.
- Mobile filter layout.
- Cache headers для WebP/AVIF.
- Docs cleanup, якщо production deploy відкладений.

Ризик деплою зараз: приватні фото або metadata можуть бути розкриті публічно, а release/backup процес може створити хибне відчуття безпеки.

## 20. Final Verdict

Що добре: архітектура проста і здебільшого здорова; security foundations сильні; upload/media, sessions, CSRF, prepared statements, private originals, restore validation і production guards зроблені обережно.

Що обов'язково виправити: homepage privacy, public filter privacy, release/runtime leakage, `schema_migrations` schema mismatch і backup verification fail-open.

Що бажано виправити: share token format validation, trusted proxy IP helper для share/download rate limits, ZIP cache fingerprint, CSP inline handlers, external font import, share noindex.

Що можна відкласти: lightbox focus trap, mobile filter polish, cache headers для next-gen images, docs archive cleanup після технічних блокерів.

Чи можна вважати версію stable: для локального навчального MVP - близько до stable після ручної перевірки; для production/public internet - **ні**, доки не закриті Critical/High issues і не пройдені PHP lint/tests/self_check/release ZIP verification.
