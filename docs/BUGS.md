# Known Issues and Operational Notes

Актуально для MyGallery 6.4.23.

## Відомі критичні проблеми

Критичних відомих проблем немає.

Виправлено у v6.4.7: обхід захисту входу від брутфорсу через тривіальну арифметичну CAPTCHA, яка обнуляла часовий локаут (`public/admin/login.php`). Тепер локаут суто час-базований і CAPTCHA його не обходить.

Виправлено раніше (High H1): витік приватних оригіналів через ZIP-завантаження альбому (`public/download_album.php`). Публічні (`?album_id=`) та share-токен завантаження тепер отримують лише оптимізовану копію `uploads/large`; byte-for-byte оригінали зі `storage/originals` віддаються тільки залогіненому адміну. Ключ кешу враховує варіант (`orig`/`opt`), тож адмінський архів з оригіналами не може бути відданий не-адміну з кешу.

Три High-знахідки backup/restore з `FULL_PROJECT_AUDIT.md` закриті у v6.4.21, вісім Medium і шість Low — у v6.4.22, п’ять Informational — закриті або підтверджені у v6.4.23. Деталі та невиконані environment-dependent перевірки наведені в `docs/AUDIT_REPORT.md`. Історичні знахідки аудиту 2026-06-16 залишені в `docs/AUDIT_FINDINGS_2026-06-16.md` як архівний контекст.

## Обмеження, які варто пам'ятати

- Проєкт приймає тільки JPEG/JPG upload. RAW/PNG/WebP upload не підтримується навмисно; WebP/AVIF генеруються як оптимізовані похідні файли.
- Clean release ZIP потрібно збирати тільки через `php tools/build_release.php`; ручний ZIP робочої папки може містити `.git`, `config/database.php`, фото, логи, сесії, backup або внутрішні AI/audit файли.
- Звичайна публічна галерея навмисно не шукає за `photos.original_name`; пошук за оригінальними назвами файлів лишається для адмінки та token-based share view.
- Public share pages у production fail-closed, якщо `storage/share_ratelimit` недоступна для запису; це може тимчасово дати 503, але не відкриває приватні посилання без rate-limit.
- ZIP-завантаження альбомів має cooldown і окремий generation lock на cache-key; для дуже великих production-галерей усе одно може знадобитися фонова генерація архівів.
- На Nginx правила `.htaccess` не працюють, тому треба окремо налаштувати блокування доступу до приватних директорій і виконання PHP в uploads.
- Перед production треба застосувати всі міграції та запустити `php tools/self_check.php`.
- Міграція share-target CHECK навмисно зупиниться, якщо в старій БД є рядки `share_links` без цілі або одночасно з `photo_id` і `album_id`; спочатку виправте такі дані, потім повторіть міграцію.
- Backup-архіви містять приватні оригінали фото, тому їх не можна зберігати в `public/`.
- Backup навмисно abort-иться, якщо DB photo inventory не відповідає canonical media або знайдено orphan derivative; спочатку виправте стан через self-check/cleanup tools, а не обходьте validation.
- Backup ZIP старого формату без `format_version: 2` або без поточного `photo_inventory` навмисно не проходить автоматичний restore: у ньому немає достатніх DB/media/hash гарантій. Для disaster recovery створіть і перевірте новий format-v2 backup поточною версією.
- Якщо `storage/restore_journal.json` лишився після аварійного завершення restore, не запускайте сайт із потенційно проміжним станом. Повторний запуск `tools/restore.php` спочатку читає DB marker і автоматично завершує commit або повертає попередні media.
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
