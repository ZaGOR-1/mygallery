# Known Issues and Operational Notes

Актуально для MyGallery v6.1.0.

## Відомі критичні проблеми

Критичних відомих проблем після стабілізації v6.1.0 немає.

## Обмеження, які варто пам'ятати

- Проєкт приймає тільки JPEG/JPG. RAW/PNG/WebP upload не підтримується навмисно.
- Clean release ZIP потрібно збирати тільки через `php tools/build_release.php`; ручний ZIP робочої папки може містити `.git`, `config/database.php`, фото, логи, сесії або backup.
- На Nginx правила `.htaccess` не працюють, тому треба окремо налаштувати блокування доступу до приватних директорій і виконання PHP в uploads.
- Перед production треба застосувати всі міграції та запустити `php tools/self_check.php`.
- Backup-архіви містять приватні оригінали фото, тому їх не можна зберігати в `public/`.

## Regression checklist після змін

- Login/logout і rate limiter.
- Upload одного та кількох JPEG.
- Duplicate detection через `original_sha256`.
- Albums, covers, tags і bulk edit.
- Share links: create/open/revoke/expire.
- Backup/verify/restore.
- Clean release ZIP без приватних файлів.
