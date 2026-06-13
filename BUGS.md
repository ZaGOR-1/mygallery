# Known Issues

Останнє оновлення: 2026-06-13

Цей файл містить відомі обмеження, які залишилися після виправлення High/Medium/Low пунктів із `FULL_PROJECT_AUDIT.md`.

## Відкриті проблеми

### Low: Для повної інвалідації старих сесій після зміни пароля ще немає `session_version`

- Файл: `app/includes/auth.php`
- Стан: `admin_id` уже періодично перевіряється в БД. Окремої функції зміни пароля в UI ще немає, тому `session_version` не додано.
- Що зробити: якщо зʼявиться зміна пароля або кілька адміністраторів, додати `password_changed_at` або `session_version`.

### Info: Локальний Xdebug не може відкрити log file

- Середовище: Wamp/PHP CLI.
- Стан: не баг застосунку, але засмічує CLI-вивід.
- Що зробити: створити `c:/wamp64/logs/xdebug.log`, дати права або вимкнути Xdebug для CLI.

## Закриті 2026-06-13

- High: legacy originals перенесені з `public/uploads/originals` у `storage/originals`.
- Medium: `tools/recover_trash.php` більше не довіряє manifest-шляхам без validation.
- Medium: `public/admin/albums.php` не викликає повторний `db()` у `catch`.
- Medium: HSTS додано для production HTTPS.
- Medium: `tools/self_check.php` розширено до перевірки модулів, структури, конфігів, writable-директорій і upload protection files.
- Medium: `tools/cleanup_orphans.php` показує коректний шлях `public/uploads/...`.
- Low: SQL schema/migrations стали portable без `USE my_photo_gallery`.
- Low: upload cleanup використовує helper із логуванням невдалого `unlink`.
- Low: admin-сесія періодично перевіряє існування admin-запису в БД.
- Low: trash manifest більше не записує абсолютні шляхи у JSON.
- Low: `tools/setup.php` більше не залежить від `shell_exec()` / `system()`.
- Low: додано responsive `srcset`/`sizes` для gallery/admin/photo images.
- Low: додано FULLTEXT-індекси й FULLTEXT-пошук із fallback на `LIKE`.
