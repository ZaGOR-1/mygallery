# Known Issues and Operational Notes

Актуально для MyGallery v6.4.8.

## Відомі критичні проблеми

Критичних відомих проблем немає.

Виправлено у v6.4.7: обхід захисту входу від брутфорсу через тривіальну арифметичну CAPTCHA, яка обнуляла часовий локаут (`public/admin/login.php`). Тепер локаут суто час-базований і CAPTCHA його не обходить.

Виправлено у v6.4.8 (High H1): витік приватних оригіналів через ZIP-завантаження альбому (`public/download_album.php`). Публічні (`?album_id=`) та share-токен завантаження тепер отримують лише оптимізовану копію `uploads/large`; byte-for-byte оригінали зі `storage/originals` віддаються тільки залогіненому адміну. Ключ кешу враховує варіант (`orig`/`opt`), тож адмінський архів з оригіналами не може бути відданий не-адміну з кешу.

Решта знахідок аудиту 2026-06-16 (Medium/Low) — у `docs/AUDIT_FINDINGS_2026-06-16.md`, ще не виправлені.

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
