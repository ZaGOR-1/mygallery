# Implemented Features Archive

Цей файл містить архів уже реалізованих можливостей фотогалереї. Актуальні майбутні задачі залишаються у `POST_MVP_ROADMAP.md`.

Останнє оновлення: 2026-06-12.

## Базовий MVP

Реалізовано:

- головна сторінка;
- адаптивна галерея фотографій;
- пагінація по 12 фотографій;
- сторінка окремої фотографії;
- перехід до попередньої та наступної фотографії;
- відображення EXIF-даних;
- адміністративний вхід;
- завантаження JPEG;
- редагування назви й опису;
- видалення фотографій;
- створення thumbnails;
- створення optimized large JPEG;
- lazy loading для прев’ю;
- зрозумілі flash-повідомлення про помилки та успішні дії.

Ключові файли:

- `public/index.php`;
- `public/gallery.php`;
- `public/photo.php`;
- `public/admin/index.php`;
- `public/admin/login.php`;
- `public/admin/upload.php`;
- `public/admin/edit.php`;
- `public/admin/delete.php`;
- `app/includes/functions.php`.

## Архітектура і переносимість

Реалізовано:

- публічний DocumentRoot у `public/`;
- приватні папки `app/`, `config/`, `database/`, `storage/`, `tools/`;
- окремі `config/config.php`, `config/database.php`, `config/database.example.php`;
- `config/database.php` і приватні файли додані в `.gitignore`;
- приватне зберігання оригіналів у `storage/originals`;
- optimized large images у `public/uploads/large`;
- thumbnails у `public/uploads/thumbnails`;
- приватні logs у `storage/logs`;
- приватні PHP session-файли у `storage/sessions`;
- `tools/setup.php` тільки для CLI;
- `tools/cleanup_orphans.php`;
- `tools/self_check.php`;
- SQL-схема у `database/schema.sql`;
- SQL-міграція для альбомів у `database/migrations/2026_06_12_add_albums.sql`.

## Безпека

Реалізовано:

- PDO prepared statements;
- `htmlspecialchars()` через helper `h()`;
- CSRF-захист для POST-форм;
- logout через POST;
- delete через POST;
- `session_regenerate_id(true)` після login;
- `password_hash()` і `password_verify()`;
- brute-force захист входу через `login_attempts`;
- security headers;
- заборона виконання PHP у `public/uploads`;
- realpath guards для файлових операцій;
- приватне сховище оригіналів поза DocumentRoot;
- приховування системних шляхів і SQL-помилок від користувача.

## JPEG Upload І Обробка Фото

Реалізовано:

- максимальний розмір одного JPEG - 30 МБ;
- перевірка MIME через Fileinfo;
- додаткова перевірка через `getimagesize()`;
- дозволено тільки `image/jpeg`;
- випадкові імена файлів на сервері;
- збереження оригінального імені тільки в БД;
- обмеження розмірів зображення до 8000x8000 або 50 МП;
- перевірка memory_limit перед GD-обробкою;
- автоматичне виправлення EXIF Orientation 1-8;
- збереження EXIF у `exif_json`;
- збереження фактичного розміру оригіналу після upload;
- створення large-версії до 2400 px;
- створення thumbnail до 600 px.

## Пошук І Фільтри

Статус: реалізовано як перша Post-MVP задача.

Реалізовано:

- пошук у публічній галереї за назвою та описом;
- пошук в адмінпанелі за назвою, описом і оригінальною назвою файла;
- фільтр за камерою;
- фільтр за датою зйомки;
- сортування за датою додавання, датою зйомки і назвою;
- збереження GET-параметрів під час пагінації;
- responsive filter panel без JavaScript.

Ключові файли:

- `public/gallery.php`;
- `public/admin/index.php`;
- `app/includes/functions.php`;
- `public/assets/css/style.css`.

## Альбоми

Статус: реалізовано як друга Post-MVP задача.

Реалізовано:

- таблиця `albums`;
- nullable `photos.album_id`;
- foreign key `fk_photos_album_id` з `ON DELETE SET NULL`;
- сторінка `public/admin/albums.php`;
- створення, перейменування і видалення альбомів;
- видалення альбому не видаляє фотографії;
- вибір альбому під час upload;
- зміна альбому під час edit;
- створення нового альбому прямо з upload/edit форми;
- фільтр за альбомом у публічній галереї;
- фільтр за альбомом в адмінпанелі;
- показ альбому на сторінці фото.

Теги не реалізовано. Їх варто додавати тільки якщо простих альбомів стане замало.

## Лайтбокс

Реалізовано:

- відкриття лайтбокса зі сторінки окремої фотографії;
- кнопки zoom in / zoom out;
- zoom колесиком миші;
- drag/pan через ЛКМ;
- fit-to-screen на старті;
- закриття через кнопку, backdrop і `Esc`;
- кнопки не перекривають фотографію;
- галерея залишається progressive enhancement: картки відкривають сторінку фото без JavaScript.

## Документація

Реалізовано й підтримується:

- `README.md` з інструкціями для WampServer і LAMP;
- `AGENTS.md` з правилами архітектури, безпеки й стилю;
- `POST_MVP_ROADMAP.md` для майбутніх задач;
- `IMPLEMENTED_FEATURES.md` для архіву виконаного;
- `BUGS.md` і `AUDIT_REPORT.md` для аудитів і виправлень.
