# Post-MVP Roadmap

Цей roadmap описує майбутній розвиток фотогалереї після поточного MVP. Він не дублює вже реалізовані речі. Уже готовий функціонал винесено в `IMPLEMENTED_FEATURES.md`.

Поточний MVP уже має: JPEG-upload, EXIF, приватні оригінали, прев’ю, large-версії, альбоми, пошук, фільтри, сортування, адмінпанель, CSRF, login rate limiter, cleanup tools і лайтбокс із zoom/pan.

Головний принцип: проєкт має залишатися простим студентським PHP-проєктом без Laravel, React, Bootstrap, Composer і складної архітектури.

## Чи нормальний цей roadmap

Так, roadmap у цій версії став нормальним: він більше не тримає реалізований пошук/альбоми як майбутні задачі, а розділяє справді потрібні покращення на технічний hardening, корисні функції та великі опціональні розширення.

Найбільш правильний порядок розвитку:

1. Спочатку закрити невеликі production-ризики: SQL-міграції без жорсткого `USE`, legacy originals, HSTS, session freshness, trash recovery.
2. Потім додати admin health page, download оригіналу для адміна і CLI regeneration.
3. Потім робити bulk upload і зручнішу адмінку.
4. Теги, WebP/AVIF, RAW/NEF, API і приватні альбоми — тільки після стабілізації базового JPEG workflow.

## Пріоритети

- `P1` — треба зробити першим, бо це або production-безпека, або дуже практична користь.
- `P2` — корисно після P1, але не блокує нормальне використання.
- `P3` — приємне UX/feature-покращення.
- `P4` — велике розширення, яке різко збільшує складність.

## Розмір задач

- `S` — 1-2 файли, можна зробити швидко.
- `M` — кілька файлів, потрібні тести вручну.
- `L` — змінює архітектуру або багато сценаріїв.

---

# P1 — технічні виправлення перед новими фічами

## P1/S: Прибрати жорстку прив’язку SQL-міграцій до `my_photo_gallery`

Проблема: у `database/schema.sql` і міграціях є `USE my_photo_gallery;`. Це нормально для локального WampServer, але незручно, якщо на production база має іншу назву.

Що зробити:

- або прибрати `USE` з міграцій і README-команди завжди передавати назву БД у CLI;
- або залишити `schema.sql` для чистої локальної установки, але створити `database/migrations/*.portable.sql` без `USE`;
- у README чітко описати обидва сценарії.

Ймовірні файли:

- `database/schema.sql`;
- `database/migrations/2026_06_12_add_albums.sql`;
- `database/migrations/2026_06_13_hardening.sql`;
- `README.md`.

Критерії готовності:

- міграції можна імпортувати командою `mysql -u user -p database_name < migration.sql`;
- міграції не перемикаються випадково на іншу базу;
- README не суперечить SQL-файлам.

Ризик: якщо просто прибрати `CREATE DATABASE`/`USE` зі `schema.sql`, локальний quick start стане менш зручним. Краще мати окремий portable-варіант або дуже чітку інструкцію.

## P1/S: Остаточно закрити тему legacy originals

Проблема: `public/uploads/originals` залишена для сумісності. На Apache вона закрита `.htaccess`, але на Nginx або поганому хостингу `.htaccess` може не працювати.

Що зробити:

- залишити папку тільки з `.gitkeep` і `.htaccess` або повністю прибрати її з нової інсталяції;
- у `cleanup_orphans.php` явно пропонувати `migrate_legacy_originals.php`, якщо знайдено файли;
- у README написати, що ця папка legacy-only і має бути порожньою;
- для Nginx додати приклад deny-правила.

Ймовірні файли:

- `public/uploads/originals/`;
- `tools/cleanup_orphans.php`;
- `README.md`;
- можливо `public/uploads/.htaccess`.

Критерії готовності:

- новий upload ніколи не пише у `public/uploads/originals`;
- self-check або cleanup попереджає, якщо там є реальні JPEG;
- документація прямо каже, що це не нормальне сховище.

## P1/S: Покращити `tools/recover_trash.php`

Проблема: trash recovery уже є, але поведінку з manifest-файлами треба зробити чіткішою. Після успішного restore manifest має прибиратися або переноситися в окремий `recovered`/`done` стан, щоб не плутати адміністратора.

Що зробити:

- після успішного restore видаляти manifest або перейменовувати його в `.done`;
- додати режим `--list` або залишити dry-run за замовчуванням;
- показувати підсумок: скільки manifest знайдено, скільки відновлено, скільки очищено;
- обробляти частковий restore: коли частина файлів уже повернута, а частина відсутня.

Ймовірні файли:

- `tools/recover_trash.php`;
- `README.md`;
- `BUGS.md`.

Критерії готовності:

- повторний запуск після успішного restore не показує стару успішно відновлену операцію як активну проблему;
- dry-run нічого не змінює;
- `--apply` явно повідомляє, що було зроблено.

## P1/M: Перевіряти актуальність admin-сесії в БД

Проблема: якщо admin-запис видалили або змінили пароль, стара сесія може залишатися валідною до timeout.

Що зробити:

- у `is_admin_logged_in()` періодично перевіряти, що `admin_id` досі існує;
- додати поле `password_changed_at` або `session_version` тільки якщо справді треба invalidation після зміни пароля;
- не робити запит до БД на кожен asset/request, але для admin-сторінок це нормально.

Ймовірні файли:

- `app/includes/auth.php`;
- `database/schema.sql` або міграція, якщо буде `session_version`;
- `public/admin/login.php`.

Критерії готовності:

- після видалення admin-запису стара сесія більше не дає доступ;
- після зміни пароля можна інвалідовувати старі сесії;
- idle timeout продовжує працювати.

## P1/S: Додати HSTS для production

Проблема: production вимагає HTTPS, але HSTS ще не налаштований.

Що зробити:

- додати `Strict-Transport-Security` тільки коли `APP_ENV=production` і `APP_URL` починається з `https://`;
- не вмикати HSTS у локальному `http://mygallery`, щоб не зламати WampServer;
- описати це в README.

Ймовірні файли:

- `app/includes/functions.php` або місце, де віддаються security headers;
- `README.md`.

Критерії готовності:

- на local HTTP HSTS не віддається;
- на production HTTPS HSTS віддається;
- немає warning/headers already sent.

## P1/M: Посилити перевірки помилок GD і пам’яті

Проблема: upload уже має обмеження розміру і габаритів, але GD-функції краще перевіряти максимально явно.

Що зробити:

- перевіряти результат `imagecreatetruecolor()`;
- перевіряти результат `imagecopyresampled()`;
- акуратніше оцінювати memory limit для великих JPEG;
- повертати дружню помилку, якщо зображення завелике для поточного PHP memory limit;
- не залишати частково створені файли.

Ймовірні файли:

- `app/includes/functions.php`;
- `public/admin/upload.php`;
- `tools/self_check.php`.

Критерії готовності:

- при нестачі пам’яті користувач бачить нормальну помилку;
- логи містять технічну причину;
- часткові `large`/`thumbnail` не залишаються після невдалого upload.

---

# P1 — практичні функції з найбільшою користю

## P1/M: Admin health page

Проблема: зараз є `tools/self_check.php`, але адміністратору зручніше бачити технічний стан у браузері після входу.

Що додати:

- сторінку `public/admin/health.php`;
- перевірку PHP-версії;
- перевірку модулів `pdo_mysql`, `gd`, `exif`, `fileinfo`, `mbstring`;
- перевірку прав запису в `storage/originals`, `storage/trash`, `storage/logs`, `storage/sessions`, `public/uploads/large`, `public/uploads/thumbnails`;
- показ `upload_max_filesize`, `post_max_size`, `memory_limit`;
- перевірку підключення до БД;
- попередження про legacy files у `public/uploads/originals`;
- посилання на README-команди для CLI tools.

Ймовірні файли:

- `public/admin/health.php`;
- `app/includes/functions.php`;
- `app/includes/header.php`;
- `public/assets/css/style.css`;
- `README.md`.

Критерії готовності:

- сторінка доступна тільки після `require_admin()`;
- секрети БД і повні системні шляхи не показуються;
- проблеми видно як `OK`, `Warning`, `Error`.

## P1/M: Download оригіналу тільки для адміністратора

Поточний стан: оригінали лежать приватно в `storage/originals`, а публічно показується optimized large version.

Що додати:

- кнопку `Завантажити оригінал` на сторінці photo/admin тільки для admin;
- endpoint `public/admin/download.php?id=...`;
- перевірку доступу через `require_admin()`;
- віддачу файла через `readfile()` з правильними headers;
- перевірку `realpath`, щоб не було path traversal.

Ймовірні файли:

- `public/admin/download.php`;
- `public/photo.php` або admin photo/edit page;
- `app/includes/functions.php`.

Критерії готовності:

- гість не може скачати оригінал;
- admin може скачати тільки файл, який є в БД;
- endpoint не відкриває довільні файли зі `storage/`.

## P1/M: CLI-регенерація thumbnail/large

Проблема: якщо змінити `LARGE_MAX_WIDTH`, якість JPEG або thumbnail width, старі фото залишаться в старому розмірі.

Що додати:

- `tools/regenerate_images.php`;
- режими `--thumbnails`, `--large`, `--all`;
- `--dry-run` за замовчуванням або окремий режим;
- обмеження batch-size;
- логування помилок;
- пропуск фото без оригіналу.

Ймовірні файли:

- `tools/regenerate_images.php`;
- `app/includes/functions.php`;
- `README.md`.

Критерії готовності:

- оригінали не перезаписуються;
- можна відновити `public/uploads/large` і `public/uploads/thumbnails` із `storage/originals`;
- скрипт показує підсумок успіхів і помилок.

## P1/M: Backup/restore інструкції або скрипт

Проблема: README описує backup, але для реального сервера краще мати більш формальний процес.

Що додати:

- `tools/backup.sh` для Linux або окремий документ `BACKUP_RESTORE.md`;
- список того, що входить у backup;
- список того, що не повинно входити у backup;
- приклад restore на чисту VM;
- шаблон cron або systemd timer.

Ймовірні файли:

- `BACKUP_RESTORE.md`;
- можливо `tools/backup.sh`;
- `README.md`.

Критерії готовності:

- можна відновити галерею на новій машині тільки з backup і README;
- backup не потрапляє в `public/`;
- паролі не друкуються у лог.

## P1/M: Масове завантаження JPEG

Проблема: зараз фото завантажуються по одному. Для реальної фотогалереї це швидко стане незручно.

Що додати:

- `<input type="file" multiple>`;
- обробку кількох JPEG за один POST;
- окремий результат для кожного файла: завантажено або помилка;
- обмеження кількості файлів за раз, наприклад 10;
- чистку частково створених файлів при помилках.

Ймовірні файли:

- `public/admin/upload.php`;
- `app/includes/functions.php`;
- `config/config.php`;
- `README.md`.

Критерії готовності:

- один поганий файл не ламає весь batch;
- результати показуються по кожному файлу;
- не перевищуються `post_max_size` і `memory_limit` без нормального повідомлення.

Рекомендація: не робити одразу drag-and-drop/AJAX. Спочатку зробити простий multiple upload через стандартний HTML input.

---

# P2 — покращення адмінки і навігації

## P2/M: Масові дії в адмінці

Що додати:

- checkbox-вибір кількох фото;
- масове видалення через POST + CSRF;
- масове перенесення в альбом;
- фільтри `без опису`, `без EXIF`, `без дати зйомки`;
- підтвердження з кількістю вибраних фото.

Ризики:

- не допустити випадкове видалення великої кількості фото;
- всі дії тільки POST;
- бажано використовувати trash manifest для кожного фото.

## P2/S: Сторінка статистики

Що показувати:

- загальна кількість фото;
- кількість альбомів;
- сумарний розмір оригіналів;
- сумарний розмір large/thumbnails;
- найчастіша камера;
- кількість фото з EXIF і без EXIF;
- роки/місяці зйомки;
- найбільші файли.

Ймовірні файли:

- `public/admin/stats.php`;
- `app/includes/functions.php`;
- `public/assets/css/style.css`.

## P2/M: Архів за роками і місяцями

Що додати:

- сторінку `public/archive.php`;
- групування фото за `taken_at`;
- fallback на `created_at`, якщо EXIF-дати немає;
- посилання з головного меню.

Користь: це дуже природна навігація для фотогалереї й простіше за складну систему тегів.

## P2/M: FULLTEXT-пошук для великої галереї

Поточний пошук через `LIKE` нормальний для невеликої персональної галереї. Якщо фото стане багато, можна перейти на FULLTEXT.

Що додати:

- FULLTEXT index на `title`, `description`, `original_name`;
- fallback на `LIKE`, якщо FULLTEXT недоступний;
- перевірку сумісності MySQL/MariaDB.

Ризик: FULLTEXT по-різному поводиться з короткими словами, мовами й стоп-словами. Не треба робити це зарано.

## P2/M: Responsive images

Що додати:

- `public/uploads/medium`, наприклад 1200 px;
- `srcset` для thumbnails/medium/large;
- `sizes` у gallery cards;
- CLI-генерацію medium для старих фото.

Ризик: без `tools/regenerate_images.php` ця задача незручна для вже завантажених фото.

## P2/M: Теги

Альбоми вже реалізовані як один альбом на фото. Теги потрібні тільки якщо одного альбому стане замало.

Що додати:

- таблиці `tags` і `photo_tags`;
- UI для додавання тегів під час upload/edit;
- фільтр за тегом у галереї;
- сторінку або список тегів.

Ризик: це ускладнює SQL, UI і редагування. Не робити, поки альбомів достатньо.

---

# P3 — UX і необов’язкові покращення

## P3/S: Покращення лайтбокса

Можна додати:

- подвійний клік: 100% <-> 200%;
- клавіші `ArrowLeft` / `ArrowRight` для переходу між фото;
- показ `1 з N`;
- preload попереднього/наступного фото;
- touch pinch zoom для телефона.

Ризик: JS може розростися. Touch/pinch треба тестувати на реальному телефоні.

## P3/S: Кращий UX upload

Можна додати:

- client-side preview перед upload;
- показ вибраного файла, розміру і MIME;
- попередження, якщо файл більший за поточний ліміт;
- після успішного upload вести одразу на сторінку фото або залишати в адмінці за налаштуванням.

## P3/M: Sitemap і SEO-дрібниці

Що додати:

- `sitemap.xml` або `sitemap.php`;
- canonical URL для сторінок фото;
- кращі meta descriptions;
- Open Graph image для фото.

Для приватної/локальної галереї це не потрібно. Для публічної — корисно.

## P3/M: WebP/AVIF як додатковий формат

JPEG workflow уже стабільний. WebP/AVIF варто додавати тільки після backup і regeneration tools.

Що додати:

- перевірку підтримки GD/Imagick;
- генерацію WebP/AVIF поруч із JPEG;
- fallback на JPEG;
- CLI-регенерацію для старих фото.

Ризик: AVIF/WebP підтримка залежить від збірки PHP/GD. Це легко зробить деплой складнішим.

---

# P4 — великі розширення, які не потрібні зараз

## P4/L: RAW/NEF workflow

Не додавати RAW у звичайний upload. Для Nikon NEF потрібен окремий workflow:

- зберігати RAW як архівний файл;
- окремо мати JPEG-preview;
- не намагатися обробляти RAW через GD;
- можливо використовувати зовнішні CLI tools, але це вже не простий MVP.

## P4/L: Приватні альбоми, користувачі і ролі

Не потрібно для персональної галереї. Це різко додає складність:

- users;
- roles;
- permissions;
- public/private visibility;
- password reset;
- email;
- складніші тести безпеки.

## P4/L: REST API

Не потрібно, поки немає мобільного застосунку або зовнішнього клієнта. Якщо додавати API, треба думати про auth tokens, rate limit, CORS і версіонування.

---

# Рекомендований план на найближчі ітерації

## Ітерація 1: маленький production-hardening

1. Portable SQL migrations без жорсткого `USE my_photo_gallery`.
2. HSTS тільки для production HTTPS.
3. Покращений `recover_trash.php`.
4. Admin session freshness.
5. Документація для Nginx deny на legacy originals.

## Ітерація 2: адміністраторські інструменти

1. `public/admin/health.php`.
2. `public/admin/download.php` для оригіналу.
3. `tools/regenerate_images.php`.
4. Детальніший backup/restore документ.

## Ітерація 3: зручність наповнення

1. Multiple JPEG upload.
2. Upload preview.
3. Масові дії в адмінці.

## Ітерація 4: навігація і масштабування

1. Archive by year/month.
2. Stats page.
3. FULLTEXT-пошук, якщо `LIKE` стане повільним.
4. Теги, якщо альбомів стане замало.

## Що не робити зараз

- Не переписувати на Laravel.
- Не додавати React/Vue.
- Не додавати реєстрацію користувачів.
- Не додавати RAW/NEF у звичайну upload-форму.
- Не додавати API без реальної потреби.
- Не робити WebP/AVIF до появи `regenerate_images.php` і backup/restore процесу.
