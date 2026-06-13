# Fixes Applied

## 2026-06-13 — High/Medium audit fixes

Джерело: `FULL_PROJECT_AUDIT.md`.

### H-01: Legacy originals у public

- Перенесено 4 legacy JPEG із `public/uploads/originals` у `storage/originals`.
- `public/uploads/originals` залишено тільки з `.gitkeep` і `.htaccess`.
- Файловий стан перевірено через `Get-ChildItem`.
- `cleanup_orphans.php` не вдалося повторити повністю, бо MySQL/MariaDB не був запущений у момент перевірки.

### M-01: Trash recovery manifest validation

- Нові trash manifest entries отримують `area`, `folder`, `filename`, `trash_filename`.
- Додано validation для photo filenames і trash filenames.
- `tools/recover_trash.php` перед restore/purge резолвить manifest entry тільки в дозволені storage/upload/trash paths.
- Старі manifest entries з абсолютними шляхами підтримуються, але проходять base directory validation.

### M-02: Albums transaction error handling

- `public/admin/albums.php` більше не викликає `db()` у `catch`.
- Rollback виконується тільки через уже створений `$pdo`.

### M-03: Production HSTS

- `send_security_headers()` додає `Strict-Transport-Security` тільки коли `APP_ENV=production` і запит реально HTTPS.
- Local `http://mygallery` не отримує HSTS.

### M-04: Documentation state

- Відновлено актуальні службові файли `BUGS.md`, `FIXES_APPLIED.md`, `AUDIT_REPORT.md`.
- Додано `FULL_PROJECT_AUDIT.md` як детальний audit-файл у README/AGENTS/IMPLEMENTED_FEATURES.

### M-05: Self-check scope

- `tools/self_check.php` тепер перевіряє PHP extensions, config files, required directories, writable runtime/upload dirs, upload `.htaccess`, `.gitkeep`, CSRF і EXIF Orientation.

### M-06: Cleanup output path

- `tools/cleanup_orphans.php` тепер показує `public/uploads/<folder>/<filename>` для public media.

## Перевірки

Після змін треба запускати:

```bash
php -l app/includes/functions.php
php -l public/admin/albums.php
php -l tools/recover_trash.php
php -l tools/cleanup_orphans.php
php -l tools/self_check.php
php tools/self_check.php
php tools/recover_trash.php
php tools/cleanup_orphans.php
node --check public/assets/js/main.js
```

## 2026-06-13 — Low audit fixes

Джерело: `FULL_PROJECT_AUDIT.md`.

### L-01: Portable SQL

- `database/schema.sql` більше не створює й не перемикає БД через `CREATE DATABASE` / `USE`.
- SQL migrations більше не містять `USE my_photo_gallery`.
- README-команди явно створюють БД і передають DB name у `mysql ... database_name < file.sql`.

### L-02: Upload cleanup

- Додано `unlink_file_with_log()`.
- `public/admin/upload.php` логуватиме невдале прибирання частково створених files після DB/GD failure.

### L-03: Admin session freshness

- `is_admin_logged_in()` періодично перевіряє, що `admin_id` досі існує в БД.
- Якщо запис адміністратора зник або DB-check впав, сесія очищається.

### L-04: Trash manifest paths

- JSON manifest більше не записує абсолютні `from` / `trash` paths.
- У manifest зберігаються `area`, `folder`, `filename`, `trash_filename`.
- Runtime restore/delete все ще використовує абсолютні шляхи в памʼяті, але не записує їх у JSON.

### L-05: Setup portability

- `tools/setup.php` більше не використовує `shell_exec()` або `system()`.
- Пароль вводиться через portable stdin fallback із попередженням, що його буде видно в консолі.

### L-06: Responsive images

- Додано `photo_responsive_srcset()`, `photo_card_sizes()`, `photo_view_sizes()`.
- Gallery, admin index і photo page отримали `srcset`/`sizes`.

### L-07: FULLTEXT search

- Додано FULLTEXT indexes для public/admin search у schema і hardening migration.
- Gallery/admin search використовує `MATCH ... AGAINST` за наявності індексу.
- Якщо індексів ще немає, код автоматично повертається до `LIKE`.
