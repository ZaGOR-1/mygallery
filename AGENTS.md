# AGENTS.md

Інструкції для AI/Codex/LLM-агента, який аналізує або змінює цей репозиторій.

Цей файл є **головним джерелом правил** для всіх AI-агентів у проєкті. Якщо є `CLAUDE.md`, `GEMINI.md`, `.cursor/rules/*` або `.github/copilot-instructions.md`, вони мають посилатися на цей файл і не дублювати його повністю.

## Про проєкт

**MyGallery** — персональна фотогалерея на чистому PHP без Laravel/Composer/React. Актуальну версію завжди перевіряй у файлі `VERSION`, а не в інструкціях для агентів. Проєкт має залишатися простим, зрозумілим, переносимим і придатним для студентської роботи, але з нормальними production/security-практиками.

Основний функціонал уже реалізований:

- публічна галерея фото;
- адмінпанель;
- JPEG-upload;
- EXIF-метадані;
- EXIF Orientation 1–8;
- приватні byte-for-byte оригінали в `storage/originals`;
- optimized large images;
- thumbnails;
- responsive images через `srcset` / `sizes`;
- WebP/AVIF variants, якщо підтримуються сервером;
- пошук, фільтри, сортування, пагінація;
- FULLTEXT-пошук із fallback на `LIKE`;
- альбоми;
- приватні альбоми;
- обкладинки альбомів;
- сортування альбомів;
- теги через таблиці `tags` і `photo_tags`;
- share links для фото/альбомів;
- expired/revoked share links;
- download original тільки для адміна;
- download album ZIP із лімітами/cooldown;
- dominant color для фото;
- duplicate detection через `original_sha256`;
- кошик/trash recovery;
- backup/verify/restore tools;
- release builder;
- cleanup/runtime tools;
- health check;
- self check;
- 404/500 error pages;
- CSRF;
- login rate limiter;
- `session_version` для інвалідації старих сесій після зміни пароля;
- production HSTS для HTTPS;
- базові security headers;
- unit/regression tests.

Перед плануванням нової фічі перевір `docs/IMPLEMENTED_FEATURES.md`, `CHANGELOG.md` і `ROADMAP.md`, щоб не реалізувати повторно те, що вже є.

## Головний принцип

Не перетворюй проєкт на великий фреймворк-додаток. Краще простий, зрозумілий, безпечний PHP-код, ніж складна архітектура заради архітектури.

AI-агент має:

1. спочатку читати існуючий код;
2. перевіряти, чи фіча вже реалізована;
3. робити маленькі логічні зміни;
4. не переписувати весь проєкт без потреби;
5. оновлювати документацію після змін;
6. запускати перевірки;
7. чесно писати, що перевірено, а що ні.

## Технології

Використовуй:

- PHP 8.2 або новіший;
- Apache 2.4;
- MySQL 8.0 або MariaDB 10.6+;
- PDO;
- HTML5;
- CSS3;
- чистий JavaScript;
- PHP GD;
- PHP EXIF;
- PHP Fileinfo;
- PHP mbstring;
- PHP ZipArchive або fallback-реалізацію, якщо вона вже є в `tools/lib/`.

Локальний WampServer може мати PHP за шляхом приблизно:

```text
C:\wamp64\bin\php\php8.3.14\php.exe
```

Але **не прописуй абсолютні Windows-шляхи в коді проєкту**.

Не використовуй без окремого дозволу:

- Laravel;
- Symfony;
- React;
- Vue;
- Bootstrap;
- jQuery;
- ORM;
- Composer-пакети;
- Node.js як runtime-залежність;
- сторонні CSS/JS UI-фреймворки.

`node --check` можна використовувати тільки як dev-перевірку JavaScript. Застосунок не повинен залежати від Node.js у runtime.

## Середовища запуску

Проєкт має працювати у двох основних середовищах.

### Локальна розробка

- Windows 10/11;
- WampServer;
- Apache;
- PHP;
- MySQL або MariaDB;
- адреса `http://mygallery/`;
- Apache VirtualHost із `DocumentRoot` на папку `public`.

### Майбутній сервер / production

- VM у Proxmox або VPS;
- Debian/Ubuntu Server;
- LAMP;
- HTTPS;
- окремий користувач БД;
- `APP_ENV=production`;
- `APP_DEBUG=false`;
- `APP_URL=https://...`;
- `DocumentRoot` тільки на `public`.

Код не повинен залежати від Windows і має переноситися на Linux без зміни основної логіки.

## Переносимість

- Не використовуй абсолютні шляхи виду `C:\wamp64\...` у коді.
- Для файлових шляхів використовуй `__DIR__`, `dirname()`, `realpath()` і `DIRECTORY_SEPARATOR`.
- Не змішуй URL-адреси з файловими шляхами.
- Враховуй чутливість Linux до регістру символів.
- Назви файлів і папок пиши малими латинськими літерами.
- Не використовуй пробіли або кирилицю в назвах файлів.
- Не прив’язуй код до конкретної IP-адреси.
- URL сайту зберігай у конфігурації як `APP_URL`.
- Для формування посилань використовуй спільну функцію `url()` або існуючий helper.

## Актуальна структура проєкту

Структура може трохи відрізнятися, але агент має орієнтуватися на такий стан:

```text
mygallery/
├── app/
│   └── includes/
│       ├── auth.php
│       ├── auth_functions.php
│       ├── csrf.php
│       ├── db.php
│       ├── file_functions.php
│       ├── functions.php
│       ├── gallery_functions.php
│       ├── photo_service.php
│       ├── header.php
│       └── footer.php
├── config/
│   ├── config.php
│   ├── database.example.php
│   └── database.php              локальний файл, не комітити і не класти в release ZIP
├── database/
│   ├── schema.sql
│   └── migrations/
│       ├── 2026_06_12_add_albums.sql
│       ├── 2026_06_13_hardening.sql
│       ├── 2026_06_13_add_tags.sql
│       ├── 2026_06_15_add_album_covers.sql
│       ├── 2026_06_15_add_album_privacy.sql
│       ├── 2026_06_15_add_album_sort_order.sql
│       ├── 2026_06_15_add_original_sha256.sql
│       ├── 2026_06_15_add_photo_dominant_color.sql
│       ├── 2026_06_15_add_session_version.sql
│       └── 2026_06_15_create_share_links.sql
├── public/
│   ├── admin/
│   │   ├── albums.php
│   │   ├── bulk_edit.php
│   │   ├── delete.php
│   │   ├── download.php
│   │   ├── edit.php
│   │   ├── health.php
│   │   ├── index.php
│   │   ├── login.php
│   │   ├── logout.php
│   │   ├── share.php
│   │   ├── stats.php
│   │   └── upload.php
│   ├── assets/
│   │   ├── css/style.css
│   │   └── js/main.js
│   ├── uploads/
│   │   ├── large/
│   │   ├── thumbnails/
│   │   └── originals/             legacy-only, нові оригінали тут не зберігати
│   ├── .htaccess
│   ├── 404.php
│   ├── 500.php
│   ├── download_album.php
│   ├── gallery.php
│   ├── index.php
│   ├── media.php
│   ├── photo.php
│   └── share.php
├── storage/
│   ├── originals/                 приватні byte-for-byte оригінали
│   ├── trash/                     тимчасовий кошик і manifest-файли
│   ├── logs/
│   ├── share_ratelimit/           runtime-ліміти для public share token
│   ├── download_locks/            runtime-ліміти для ZIP download
│   └── sessions/
├── tools/
│   ├── backfill_sha256.php
│   ├── backup.php
│   ├── build_release.php
│   ├── cleanup_orphans.php
│   ├── cleanup_runtime.php
│   ├── migrate_legacy_originals.php
│   ├── recover_trash.php
│   ├── regenerate_images.php
│   ├── restore.php
│   ├── self_check.php
│   ├── setup.php
│   ├── verify_backup.php
│   └── lib/
│       └── SimpleZipWriter.php
├── tests/
│   ├── run.php
│   ├── bootstrap.php
│   └── unit/
│       ├── exif_test.php
│       ├── paths_test.php
│       ├── release_exclusions_test.php
│       └── tags_test.php
├── docs/
│   ├── AUDIT_PROMPT.md
│   ├── AUDIT_PRODUCTION_PROMPT.md
│   ├── AUDIT_REPORT.md
│   ├── AUDIT_SECURITY_PROMPT.md
│   ├── BACKUP_RESTORE.md
│   ├── BUGS.md
│   ├── IMPLEMENTED_FEATURES.md
│   ├── SECURITY_AUDIT.md
│   └── UI_UX_RECOMMENDATIONS.md
├── AGENTS.md
├── CLAUDE.md
├── GEMINI.md
├── CHANGELOG.md
├── README.md
├── ROADMAP.md
└── VERSION
```

Якщо фактична структура відрізняється — спочатку перевір репозиторій, а не вигадуй файли.

## База даних

Використовуй PDO з параметрами:

```php
PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
PDO::ATTR_EMULATE_PREPARES => false,
```

Правила:

- тільки prepared statements;
- кодування `utf8mb4`;
- окремий користувач БД на production;
- не використовувати `mysqli`;
- не показувати SQL-помилки користувачу;
- логувати infrastructure-помилки в приватний лог;
- SQL-файли мають бути portable і не містити `USE my_photo_gallery;`;
- README-команди мають явно передавати назву БД у CLI;
- кожна міграція ОБОВ'ЯЗКОВО має бути idempotent (DDL у MySQL автокомітиться, тож транзакція не зробить її атомарною — ідемпотентність єдиний надійний захист від часткового збою);
- повторний запуск міграцій не має ламатися на `Duplicate column`, `Duplicate key`, `Table already exists`.

Поточні таблиці:

- `admins`;
- `albums`;
- `photos`;
- `login_attempts`;
- `tags`;
- `photo_tags`;
- `share_links`.

Важливі поля/фічі БД:

- `photos.original_sha256`;
- `admins.session_version`;
- `albums.cover_photo_id`;
- `albums.is_private` або аналогічне поле приватності;
- `albums.sort_order`;
- `photos.dominant_color`;
- FULLTEXT indexes для пошуку, якщо вони є в схемі.

## Правила для upload і зображень

- Дозволяй тільки JPEG для оригінального upload, якщо інше явно не реалізовано.
- Перевіряй MIME через `finfo_file()`.
- Додатково перевіряй файл через `getimagesize()`.
- Не довіряй розширенню файла.
- Не використовуй оригінальне ім’я як ім’я файла на сервері.
- Оригінальне ім’я можна зберігати тільки в БД.
- Генеруй випадкове унікальне ім’я.
- Byte-for-byte оригінал зберігай у `storage/originals`.
- Optimized large version зберігай у `public/uploads/large`.
- Thumbnail зберігай у `public/uploads/thumbnails`.
- Не виводь direct `/uploads/large/...` або `/uploads/thumbnails/...` URL у HTML. Видавай derivatives через `public/media.php`, щоб приватні альбоми перевіряли admin session або share token.
- `public/uploads/large/.htaccess` і `public/uploads/thumbnails/.htaccess` мають забороняти прямий web-доступ; PHP читає ці файли з диска через `media.php`.
- WebP/AVIF variants створюй тільки як похідні файли, не як заміну приватного оригіналу.
- Не зберігай нові оригінали в `public/uploads/originals`.
- Максимальний розмір одного файла — 50 МБ, якщо config не задає інше.
- Максимальні габарити — 8000x8000 або 50 МП, якщо config не задає інше.
- Виправляй EXIF Orientation.
- Перевіряй права запису.
- Не використовуй `chmod 777`.
- Перевіряй `imagecreatetruecolor()`, `imagecopyresampled()`, `imageflip()`, `imagerotate()` на помилки.
- Не ігноруй критичні GD-помилки через `@`, якщо це може приховати реальну проблему.

## EXIF

Використовуй `exif_read_data()` і не допускай warning/notice через відсутні поля.

Зчитуй, коли доступно:

- виробника камери;
- модель камери;
- об’єктив;
- дату і час зйомки;
- ISO;
- діафрагму;
- витримку;
- фокусну відстань;
- спалах;
- орієнтацію;
- ширину і висоту.

Коректно обробляй дробові EXIF-значення: `1/250`, `28/10`, `35/1`.

Якщо значення відсутнє, у UI показуй `Немає даних` або не показуй поле, залежно від контексту. Не показуй сирі PHP warnings.

## Альбоми, приватність і share links

- Приватні альбоми не мають показуватися в публічній галереї.
- Фото з приватних альбомів не мають відкриватися через звичайні публічні URL без дозволу.
- Share links мають працювати тільки через безпечні токени.
- Share tokens мають бути достатньо випадковими.
- Expired/revoked share links мають блокувати доступ.
- Share link на видалене фото/альбом має коректно давати 404 або відповідну помилку.
- `public/admin/share.php` завжди має бути тільки за `require_admin()` і CSRF для POST.
- `public/share.php` не має давати доступ до адмін-функцій або приватних оригіналів.

## Теги

- Теги зберігай у `tags` і `photo_tags`.
- Не зберігай comma-separated tags у `photos`.
- Для змін тегів використовуй `parse_tags_input()` і `sync_photo_tags()`, якщо ці helpers існують.
- Валідуй довжину і символи тегів.
- Екрануй теги в HTML.
- Після видалення/редагування фото можна прибирати невикористані теги через `prune_unused_tags()`, але не викликай його зайво багато разів.

## Безпека

Обов’язково:

- екрануй HTML через `htmlspecialchars()` із `ENT_QUOTES`;
- використовуй PHP Session для адміністратора;
- після входу викликай `session_regenerate_id(true)`;
- паролі створюй через `password_hash()`;
- паролі перевіряй через `password_verify()`;
- обмежуй невдалі спроби входу через `login_attempts`;
- використовуй CSRF для всіх POST-форм;
- delete/update/create виконуй тільки через POST;
- перевіряй ID із запиту;
- не показуй системні шляхи;
- не показуй SQL-помилки;
- не дозволяй виконання PHP у `public/uploads`;
- не дозволяй прямий браузерний доступ до `public/uploads/large` і `public/uploads/thumbnails`;
- не відкривай `storage/` через браузер;
- `tools/*.php` запускай тільки з CLI;
- не зберігай стандартний пароль адміністратора у відкритому вигляді;
- не коміть `config/database.php`;
- не клади приватні фото, session-файли, логи, backup або dist-архіви в release ZIP.

Для production:

- `APP_ENV=production`;
- `APP_DEBUG=false`;
- `APP_URL=https://...`;
- не `root`/empty DB password;
- HTTPS;
- HSTS тільки для HTTPS production;
- secure cookies;
- no-store headers для адмінки;
- `DocumentRoot` тільки на `public`.

## Адмін-сесії

Поточний стан має підтримувати:

- idle timeout;
- періодичну перевірку `admin_id` у БД;
- fail-fast session start;
- CSRF regeneration після login;
- `session_version` для інвалідації старих сесій після зміни пароля.

Не ламай ці механізми.

## Delete, trash recovery і bulk operations

Delete має використовувати `storage/trash` і JSON manifest, щоб можна було розібратися після аварійного завершення.

При зміні delete/recover/bulk-коду:

- не видаляй одразу без можливості recovery, якщо поточна логіка передбачає trash;
- не залишай БД і файли в неконсистентному стані;
- усі delete/update-дії тільки POST + CSRF;
- після змін перевір `tools/recover_trash.php`;
- для транзакцій завжди роби rollback при винятках;
- bulk edit має коректно працювати з тегами, альбомами, приватністю й порожніми значеннями.

## Backup, restore і release

- `tools/backup.php` не має створювати backup у `public`.
- Backup містить приватні оригінали, тому його не можна публікувати.
- Дамп БД має включати `schema_migrations`, інакше після restore міграції перезапустяться по вже мігрованих даних.
- `tools/verify_backup.php` має перевіряти manifest і файли.
- `tools/restore.php` має спочатку перевіряти backup, manifest, SQL і дозволені шляхи, а вже потім змінювати БД/файли.
- `tools/restore.php` застосовує SQL-дамп у транзакції (rollback при збої); тому `tools/backup.php` не має генерувати `LOCK TABLES`/`UNLOCK TABLES` (вони викликають неявний COMMIT у MySQL).
- Медіа-файли стираються тільки після успішного відновлення БД, а не раніше.
- Restore має бути захищений від path traversal у ZIP entries.
- `tools/build_release.php` має створювати clean ZIP.
- Release ZIP не має містити `.git`, `.env`, `config/database.php`, logs, sessions, share-rate-limit files, backups, dist, uploaded media, `temp_*.php`, `*.bak`, `*.tmp`, `*.zip`.

## HTML, CSS і JavaScript

- Використовуй семантичний HTML5.
- Галерею реалізуй через CSS Grid.
- Дизайн темний і мінімалістичний, якщо інше не задано.
- Сайт адаптивний.
- Не використовуй зовнішні UI-бібліотеки.
- JavaScript тільки там, де він справді потрібний.
- Основний функціонал має працювати без JavaScript.
- Фільтри і пагінація мають працювати через GET-параметри.
- Фільтри мають зберігатися під час пагінації.
- Для зображень використовуй `loading="lazy"`, якщо доречно.
- Додавай коректні `alt`-атрибути.

## Документація

Підтримуй документи в актуальному стані:

- `README.md` — встановлення, оновлення, міграції, production, backup, release, troubleshooting.
- `CHANGELOG.md` — історія версій.
- `ROADMAP.md` — тільки майбутні задачі.
- `docs/IMPLEMENTED_FEATURES.md` — що вже зроблено.
- `docs/BUGS.md` — відомі обмеження і потенційні баги.
- `docs/AUDIT_REPORT.md` — актуальний короткий summary аудиту.
- `docs/SECURITY_AUDIT.md` — аудит безпеки.
- `docs/AUDIT_PROMPT.md` — промпт для повного AI-аудиту.
- `docs/AUDIT_SECURITY_PROMPT.md` — промпт для security-аудиту.
- `docs/AUDIT_PRODUCTION_PROMPT.md` — промпт для production readiness-аудиту.
- `docs/BACKUP_RESTORE.md` — порядок backup і restore.
- `docs/UI_UX_RECOMMENDATIONS.md` — UX/UI ідеї, якщо файл існує.

Якщо реалізуєш пункт із roadmap:

1. прибери або онови його в `ROADMAP.md`;
2. додай у `docs/IMPLEMENTED_FEATURES.md` та `CHANGELOG.md`;
3. якщо це виправлення ризику — онови `docs/BUGS.md`;
4. якщо змінюється запуск, структура або production — онови `README.md`;
5. якщо змінюються правила для майбутнього агента — онови `AGENTS.md`.

## Порядок роботи AI-агента

Перед реалізацією великого завдання:

1. Переглянь структуру репозиторію.
2. Прочитай `README.md`, `CHANGELOG.md`, `docs/IMPLEMENTED_FEATURES.md`, `docs/BUGS.md`, `ROADMAP.md` і цей `AGENTS.md`.
3. Склади короткий план.
4. Перевір, які файли вже існують.
5. Не видаляй наявну роботу без необхідності.
6. Реалізуй функціонал невеликими логічними частинами.
7. Перевір синтаксис PHP.
8. Перевір JS, якщо змінювався JS.
9. Запусти релевантні tools/tests.
10. Переглянь diff.
11. Онови документацію.
12. Опиши, що перевірено, а що не вдалося перевірити.

## Перевірка після змін

Для PHP:

```bash
php -l path/to/file.php
```

Для JavaScript:

```bash
node --check public/assets/js/main.js
```

Для тестів:

```bash
php tests/run.php
```

Для проєкту:

```bash
php tools/self_check.php
php tools/build_release.php
```

Для release ZIP:

```bash
unzip -t dist/*.zip
```

Якщо `unzip` недоступний на Windows — використовуй інший спосіб перевірки архіву, але не стверджуй, що ZIP перевірено, якщо фактично перевірки не було.

Перевір вручну:

- login/logout;
- invalid login і rate limiter;
- CSRF failure;
- upload валідного JPEG;
- відмову для не-JPEG;
- duplicate upload;
- EXIF і orientation;
- створення thumbnail/large/WebP/AVIF, якщо підтримується;
- приватне зберігання оригіналу в `storage/originals`;
- пошук/фільтри/сортування/пагінацію;
- створення, перейменування, приватність і видалення альбому;
- обкладинку альбому;
- редагування фото;
- теги;
- bulk edit;
- видалення фото;
- trash/recover;
- share links create/open/revoke/expire;
- приватні альбоми у public/share;
- download original;
- download album ZIP;
- health/stats;
- backup/verify/restore;
- cleanup/regenerate/backfill tools;
- 404/500;
- clean release ZIP.

Не стверджуй, що перевірка пройшла, якщо вона фактично не запускалася.

## Критерії готовності

Завдання завершене лише тоді, коли:

- реалізовано запитаний функціонал;
- немає псевдокоду;
- PHP-файли не мають синтаксичних помилок;
- SQL-схема відповідає коду;
- міграції не ламають повторний запуск, якщо це можливо;
- посилання працюють відповідно до `APP_URL` і `DocumentRoot public`;
- секрети не потрапили до Git або release ZIP;
- clean release ZIP збирається через `tools/build_release.php`;
- README відповідає актуальному стану проєкту;
- AGENTS.md відповідає актуальному стану проєкту;
- docs/BUGS/ROADMAP/IMPLEMENTED_FEATURES/CHANGELOG не суперечать одне одному;
- описані всі невиконані перевірки або обмеження.

## Release hygiene

Не додавай у релізний архів:

- `.git/`;
- `.env`;
- `config/database.php`;
- завантажені JPEG/WebP/AVIF;
- `storage/originals/*`;
- `storage/share_ratelimit/*`, крім `.gitkeep`;
- `storage/download_locks/*`, крім `.gitkeep`;
- `public/uploads/large/*`;
- `public/uploads/thumbnails/*`;
- `*.log`;
- `sess_*`;
- backup-архіви;
- `dist/`;
- `temp_*.php`;
- `*.bak`;
- `*.tmp`;
- приватні SQL dumps.

У порожніх runtime-директоріях залишай тільки `.gitkeep` і службові `.htaccess`, якщо вони потрібні.

## Maintenance tools

Після будь-яких змін у release/build/backup/upload/delete/share/download логіці перевіряй:

```bash
php tools/build_release.php
php -l tools/build_release.php
php -l tools/backup.php
php -l tools/restore.php
php -l tools/regenerate_images.php
```

`tools/build_release.php` має залишатися безпечним і не додавати приватні файли.

`/admin/health.php`, `/admin/stats.php`, `/admin/download.php`, `/admin/share.php` завжди мають бути тільки за `require_admin()`.
