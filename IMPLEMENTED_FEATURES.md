# Implemented features

Цей файл описує функціонал, який уже є в поточній версії проєкту. Його мета — не змішувати реалізовані речі з майбутнім roadmap.

## Галерея

- Головна сторінка з останніми фотографіями.
- Сторінка галереї `public/gallery.php`.
- Пагінація по 12 фото.
- Пошук за назвою, описом і назвою файла.
- Фільтрація за альбомом.
- Фільтрація за камерою.
- Фільтрація за датою зйомки.
- Сортування за датою додавання, датою зйомки і назвою.
- Lazy loading для прев’ю.
- Responsive images через `srcset` і `sizes`.
- FULLTEXT-пошук із fallback на `LIKE`, якщо індекс ще не застосований.

## Сторінка фото

- Окрема сторінка фото `public/photo.php`.
- Виведення EXIF-даних.
- Попереднє/наступне фото.
- Лайтбокс із кнопками zoom.
- Zoom колесиком миші.
- Перетягування фото ЛКМ після збільшення.
- Закриття через кнопку або `Esc`.

## Адмінпанель

- Login/logout через PHP Session.
- Idle timeout для адмін-сесії.
- Періодична перевірка, що `admin_id` досі існує в БД.
- Список фото в адмінпанелі.
- Пошук, фільтри і сортування в адмінпанелі.
- Upload JPEG.
- Редагування назви, опису й альбому.
- Видалення фото через POST + CSRF.
- Керування альбомами: створення, перейменування, видалення.

## Upload і обробка зображень

- Дозволено тільки `image/jpeg`.
- MIME перевіряється через `fileinfo`.
- Зображення додатково перевіряється через `getimagesize()`.
- Оригінальні назви файлів не використовуються як серверні імена.
- Серверні імена генеруються випадково.
- Обмеження розміру файла — 30 МБ.
- Обмеження габаритів — 8000x8000 або 50 МП.
- Byte-for-byte оригінал зберігається приватно в `storage/originals`.
- Large-версія зберігається в `public/uploads/large`.
- Thumbnail зберігається в `public/uploads/thumbnails`.
- EXIF Orientation обробляється, включно з варіантами 5 і 7.

## EXIF

- Камера maker/model.
- Lens model, якщо доступний.
- Taken date/time.
- ISO.
- Aperture.
- Exposure time.
- Focal length.
- Flash.
- Orientation.
- Full EXIF JSON у БД.

## Безпека

- PDO prepared statements.
- CSRF-захист для POST-форм.
- HTML escaping через `htmlspecialchars()`.
- Password hashing через `password_hash()`.
- Login rate limiter з bucket-ами `username + IP`, account-only і IP-only.
- Dummy password hash для невідомих username.
- Fail-fast session handling.
- Production guard для `APP_DEBUG`, HTTPS і небезпечного DB-root без пароля.
- HSTS header для HTTPS-запитів у `APP_ENV=production`.
- Приватне сховище оригіналів поза `public/`.
- Заборона виконання PHP у `public/uploads` через `.htaccess`.
- Базові security headers.
- Hardened trash recovery: manifest-записи перевіряються перед restore/purge.

## База даних

- Таблиці `admins`, `albums`, `photos`, `login_attempts`.
- Foreign key `photos.album_id -> albums.id` з `ON DELETE SET NULL`.
- Індекси для дат, камер, альбомів, назв і login limiter.
- Unique indexes для `photos.filename` і `photos.thumbnail_filename`.
- FULLTEXT indexes для публічного й адмінського пошуку.

## CLI tools

- `tools/setup.php` — створення першого адміністратора.
- `tools/self_check.php` — перевірка модулів, структури, конфігів і доступів.
- `tools/cleanup_orphans.php` — пошук/видалення зайвих файлів.
- `tools/migrate_legacy_originals.php` — перенесення старих public originals.
- `tools/recover_trash.php` — відновлення або очищення trash manifest-файлів.

## Документація

- `README.md` — запуск, installation, production, backup.
- `AGENTS.md` — правила для AI/Codex-агента.
- `BUGS.md` — відомі обмеження.
- `POST_MVP_ROADMAP.md` — майбутні задачі.
- `FIXES_APPLIED.md` — уже внесені виправлення.
- `AUDIT_REPORT.md` — короткий актуальний summary аудиту.
- `FULL_PROJECT_AUDIT.md` — детальний аудит.
