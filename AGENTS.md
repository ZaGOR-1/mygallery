# AGENTS.md

Інструкції для AI/Codex-агента, який змінює цей репозиторій.

## Про проєкт

Це MVP персональної фотогалереї на PHP. Проєкт має залишатися простим, зрозумілим і придатним для студентської роботи.

Основний функціонал уже реалізований:

- JPEG-upload;
- EXIF;
- приватні оригінали в `storage/originals`;
- large-версії і thumbnails;
- пошук, фільтри, сортування;
- FULLTEXT-пошук із fallback на LIKE;
- альбоми;
- адмінпанель;
- CSRF;
- login rate limiter;
- лайтбокс із zoom/pan;
- production HSTS для HTTPS;
- responsive images через `srcset`/`sizes`;
- CLI tools для setup, self-check, cleanup, legacy migration і trash recovery.

Перед плануванням нової фічі перевір `IMPLEMENTED_FEATURES.md`, щоб не реалізовувати повторно те, що вже є.

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
- PHP mbstring.

Не використовуй без окремого дозволу:

- Laravel;
- Symfony;
- React;
- Vue;
- Bootstrap;
- jQuery;
- Node.js як частину runtime;
- ORM;
- Composer-пакети;
- сторонні CSS-фреймворки.

`node --check` можна використовувати тільки як dev-перевірку JavaScript, але застосунок не повинен залежати від Node.js.

## Середовища запуску

Проєкт має працювати у двох основних середовищах.

### Локальна розробка

- Windows 10/11;
- WampServer;
- Apache;
- PHP;
- MySQL або MariaDB;
- адреса `http://mygallery/`;
- Apache VirtualHost з `DocumentRoot` на папку `public`.

### Майбутній сервер

- VM у Proxmox;
- Debian або Ubuntu Server;
- LAMP;
- HTTPS на production;
- окремий користувач БД;
- `APP_ENV=production`;
- `APP_DEBUG=false`.

Код не повинен залежати від Windows і має переноситися на Linux без зміни основної логіки.

## Переносимість

- Не використовуй абсолютні шляхи виду `C:\wamp64\...` у коді.
- Для файлових шляхів використовуй `__DIR__`, `dirname()` і `DIRECTORY_SEPARATOR`.
- Не змішуй URL-адреси з файловими шляхами.
- Враховуй чутливість Linux до регістру символів.
- Назви файлів і папок пиши малими латинськими літерами.
- Не використовуй пробіли або кирилицю в назвах файлів.
- Не прив’язуй код до конкретної IP-адреси.
- URL сайту зберігай у конфігурації як `APP_URL`.
- Для формування посилань використовуй спільну функцію `url()`.

## Актуальна структура

```text
mygallery/
├── app/
│   └── includes/
│       ├── auth.php
│       ├── csrf.php
│       ├── functions.php
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
│       └── 2026_06_13_hardening.sql
├── public/
│   ├── admin/
│   │   ├── albums.php
│   │   ├── delete.php
│   │   ├── edit.php
│   │   ├── index.php
│   │   ├── login.php
│   │   ├── logout.php
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
│   ├── gallery.php
│   ├── index.php
│   └── photo.php
├── storage/
│   ├── originals/                 приватні byte-for-byte оригінали
│   ├── trash/                     тимчасовий кошик і manifest-файли
│   ├── logs/
│   └── sessions/
├── tools/
│   ├── cleanup_orphans.php
│   ├── migrate_legacy_originals.php
│   ├── recover_trash.php
│   ├── self_check.php
│   └── setup.php
├── README.md
├── AGENTS.md
├── IMPLEMENTED_FEATURES.md
├── POST_MVP_ROADMAP.md
├── FIXES_APPLIED.md
├── AUDIT_REPORT.md
├── FULL_PROJECT_AUDIT.md
├── BACKUP_RESTORE.md
└── docs/
    ├── BUGS.md
    └── AUDIT_PROMPT.md
```

## Правила для upload

- Дозволяй тільки `image/jpeg`.
- Перевіряй MIME через `finfo_file()`.
- Додатково перевіряй файл через `getimagesize()`.
- Не довіряй розширенню файла.
- Не використовуй оригінальне ім’я як ім’я файла на сервері.
- Оригінальне ім’я можна зберігати тільки в БД.
- Генеруй випадкове унікальне ім’я.
- Byte-for-byte оригінал зберігай у `storage/originals`.
- Optimized large version зберігай у `public/uploads/large`.
- Thumbnail зберігай у `public/uploads/thumbnails`.
- Не зберігай нові оригінали в `public/uploads/originals`.
- Максимальний розмір одного файла — 30 МБ.
- Максимальні габарити — 8000x8000 або 50 МП.
- Виправляй EXIF Orientation.
- Перевіряй права запису.
- Не використовуй `chmod 777`.

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
- логувати infrastructure-помилки в приватний лог.

Поточні таблиці:

- `admins`;
- `albums`;
- `photos`;
- `login_attempts`.

SQL-файли portable і не містять `USE my_photo_gallery;`. README-команди мають явно передавати назву БД у CLI.

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
- не відкривай `storage/` через браузер;
- `tools/*.php` запускай тільки з CLI;
- не зберігай стандартний пароль адміністратора у відкритому вигляді;
- не коміть `config/database.php`;
- не клади приватні фото, session-файли, логи або backup у release ZIP.

Для production:

- `APP_ENV=production`;
- `APP_DEBUG=false`;
- `APP_URL=https://...`;
- не `root`/empty DB password;
- HTTPS;
- бажано HSTS тільки для HTTPS production.

## Адмін-сесії

Поточний стан:

- є idle timeout;
- `admin_id` періодично перевіряється в БД;
- сесія стартує fail-fast;
- CSRF регенерується після login.

Майбутнє P1-покращення:

- за потреби додати `session_version` або `password_changed_at`, щоб старі сесії інвалідовувалися після зміни пароля.

## Delete і trash recovery

Поточний delete використовує `storage/trash` і JSON manifest, щоб можна було розібратися після аварійного завершення.

При зміні delete/recover коду:

- не видаляй одразу без можливості recovery;
- не залишай БД і файли в неконсистентному стані;
- усі delete-дії тільки POST + CSRF;
- після змін перевір `tools/recover_trash.php`.

Майбутнє P1-покращення: після успішного restore manifest має видалятися або переходити в done-стан.

## HTML, CSS і JavaScript

- Використовуй семантичний HTML5.
- Галерею реалізуй через CSS Grid.
- Дизайн темний і мінімалістичний.
- Сайт адаптивний.
- Не використовуй зовнішні UI-бібліотеки.
- JavaScript тільки там, де він справді потрібний.
- Основний функціонал має працювати без JavaScript.
- Фільтри і пагінація мають працювати через GET-параметри.
- Фільтр альбому має зберігатися під час пагінації.
- Для зображень використовуй `loading="lazy"`.
- Додавай коректні `alt`-атрибути.

## Документація

Підтримуй документи в актуальному стані:

- `README.md` — як встановити, оновити, перенести, зробити backup і запустити tools.
- `IMPLEMENTED_FEATURES.md` — що вже зроблено.
- `POST_MVP_ROADMAP.md` — тільки майбутні задачі.
- `docs/BUGS.md` — відомі обмеження і потенційні баги.
- `FIXES_APPLIED.md` — історія вже внесених виправлень.
- `AUDIT_REPORT.md` — короткий актуальний summary аудиту.
- `FULL_PROJECT_AUDIT.md` — детальний аудит із findings і статусом виправлень.
- `docs/AUDIT_PROMPT.md` — промпт для повторного аудиту AI-агентом.
- `BACKUP_RESTORE.md` — порядок backup і restore.

Якщо реалізуєш пункт із roadmap:

1. прибери або онови його в `POST_MVP_ROADMAP.md`;
2. додай у `IMPLEMENTED_FEATURES.md`;
3. якщо це виправлення ризику — онови `docs/BUGS.md`;
4. якщо змінюється запуск або структура — онови `README.md`;
5. якщо змінюються правила для майбутнього агента — онови `AGENTS.md`.

## Порядок роботи

Перед реалізацією великого завдання:

1. Переглянь структуру репозиторію.
2. Прочитай `README.md`, `IMPLEMENTED_FEATURES.md`, `docs/BUGS.md` і `POST_MVP_ROADMAP.md`.
3. Склади короткий план.
4. Перевір, які файли вже існують.
5. Не видаляй наявну роботу без необхідності.
6. Реалізуй функціонал невеликими логічними частинами.
7. Перевір синтаксис PHP.
8. Перевір JS, якщо змінювався JS.
9. Переглянь diff.
10. Онови документацію.
11. Опиши, що перевірено, а що не вдалося перевірити.

## Перевірка після змін

Для PHP:

```bash
php -l path/to/file.php
```

Для JavaScript:

```bash
node --check public/assets/js/main.js
```

Для проєкту:

```bash
php tools/self_check.php
php tools/cleanup_orphans.php
```

Перевір вручну:

- login/logout;
- CSRF;
- upload валідного JPEG;
- відмову для не-JPEG;
- EXIF і orientation;
- створення thumbnail/large;
- приватне зберігання оригіналу в `storage/originals`;
- пошук/фільтри/сортування/пагінацію;
- створення, перейменування і видалення альбому;
- редагування фото;
- видалення фото;
- лайтбокс і zoom/pan;
- recovery/cleanup tools, якщо змінювався delete або файлове сховище.

Не стверджуй, що перевірка пройшла, якщо вона фактично не запускалася.

## Критерії готовності

Завдання завершене лише тоді, коли:

- реалізовано запитаний функціонал;
- немає псевдокоду;
- PHP-файли не мають синтаксичних помилок;
- SQL-схема відповідає коду;
- посилання працюють відповідно до `APP_URL` і `DocumentRoot public`;
- секрети не потрапили до Git або release ZIP;
- README відповідає актуальному стану проєкту;
- AGENTS.md відповідає актуальному стану проєкту;
- IMPLEMENTED_FEATURES/docs/BUGS/ROADMAP не суперечать одне одному;
- описані всі невиконані перевірки або обмеження.

## Release hygiene

Не додавай у релізний архів `.git/`, `config/database.php`, завантажені JPEG, `*.log`, `sess_*`, backup-архіви або тимчасові файли. У порожніх runtime-директоріях залишай тільки `.gitkeep` і службові `.htaccess`, якщо вони потрібні.


## Maintenance tools

Після будь-яких змін у release/build/backup/upload логіці перевіряй:

```bash
php tools/build_release.php
php -l tools/build_release.php
php -l tools/backup.php
php -l tools/regenerate_images.php
```

`tools/build_release.php` має залишатися безпечним: не додавати `.git/`, `config/database.php`, `.env`, `sess_*`, `*.log`, `*.zip`, backup/tmp-файли або реальні фото.

`/admin/health.php` і `/admin/download.php` завжди мають бути тільки за `require_admin()`.


## V6 implementation notes for agents

- Keep tag support in `tags` and `photo_tags`; do not store comma-separated tags inside `photos`.
- Apply tag changes through `parse_tags_input()` and `sync_photo_tags()` so validation and pruning remain consistent.
- Keep `/admin/stats.php` admin-only.
- Error pages must stay standalone and must not require an active session.
