# Known Issues and Operational Notes

Актуально для MyGallery v6.4.6.

## Відомі критичні проблеми

Критичних відомих проблем після стабілізації v6.4.6 немає.

## Обмеження, які варто пам'ятати

- Проєкт приймає тільки JPEG/JPG upload. RAW/PNG/WebP upload не підтримується навмисно; WebP/AVIF генеруються як оптимізовані похідні файли.
- Clean release ZIP потрібно збирати тільки через `php tools/build_release.php`; ручний ZIP робочої папки може містити `.git`, `config/database.php`, фото, логи, сесії або backup.
- На Nginx правила `.htaccess` не працюють, тому треба окремо налаштувати блокування доступу до приватних директорій і виконання PHP в uploads.
- Перед production треба застосувати всі міграції та запустити `php tools/self_check.php`.
- Backup-архіви містять приватні оригінали фото, тому їх не можна зберігати в `public/`.
- `download_album.php` генерує ZIP на льоту; у v6.4.6 додано cooldown/rate limit, але для великого production-сайту варто додати кешування архівів або фонову генерацію.
- Якщо репозиторій клонується з нуля, потрібно переконатися у наявності файлів `.gitkeep` у порожніх папках (наприклад, `storage/trash/`), інакше `tools/self_check.php` може повертати помилку.

## Regression checklist після змін

- Login/logout і rate limiter.
- Upload одного та кількох JPEG.
- Duplicate detection через `original_sha256`.
- Albums, private albums, covers, sort order, tags і bulk edit.
- Share links: create/open/revoke/expire.
- Download album ZIP і cooldown.
- Backup/verify/restore на тестовому середовищі.
- Clean release ZIP без приватних файлів.
