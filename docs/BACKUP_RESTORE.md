# Backup and Restore

Backup містить приватні byte-for-byte оригінали, хеші паролів адміністраторів і share tokens. Зберігайте ZIP поза `public/`/DocumentRoot, не комітьте його та обмежте доступ на рівні ОС. Опція `--include-config` додатково включає DB credentials із `config/database.php`.

## Створення backup

```bash
php tools/backup.php
php tools/backup.php --include-config
php tools/backup.php --output=/private/path/mygallery_backup.zip
```

За замовчуванням архів створюється в приватній папці `backups/`. На Linux інструмент примусово встановлює `0700` для цієї папки та `0600` для ZIP; якщо group/other access не можна прибрати, backup скасовується. Не запускайте backup від `root` без потреби та не зберігайте output у shared-readable директорії. Шлях усередині `public/` блокується. Службові `.gitkeep` і `.htaccess` не копіюються як media payload: після restore вони беруться з інсталяції, у яку виконується відновлення.

Перед читанням БД `backup.php` отримує exclusive `storage/media_maintenance.lock`, а upload/delete/trash restore/regenerate/cleanup tools використовують shared lock. Далі БД читається через `REPEATABLE READ` consistent snapshot. Lock утримується до повного читання media, створення й перевірки ZIP, тому backup не може побачити половину офіційної media lifecycle operation.

Поточний format v2 містить:

- непорожній DML dump `database.sql`, включно зі `schema_migrations`;
- точний allowlist дозволених ZIP entries;
- byte size і SHA-256 для SQL, кожного media-файлу та optional config;
- `photo_inventory`, отриманий із того самого DB snapshot: кожен DB photo мусить мати original/large/thumbnail, derivatives не можуть бути orphan, а `photos.original_sha256` звіряється з приватним original;
- тільки media-імена формату `32hex.jpg|webp|avif` у визначених папках.

Після запису `tools/backup.php` обов’язково повторно відкриває ZIP, читає всі streams і перевіряє manifest. За будь-якої помилки інструмент повертає non-zero та видаляє невдалий output.

## Незалежна перевірка

```bash
php tools/verify_backup.php backups/mygallery_backup_YYYYMMDD_HHMMSS.zip
```

Verifier і restore використовують одну спільну validation function. Перевіряються JSON shape/version, allowlist, відсутність missing/extra/duplicate/unsafe entries, SQL markers, фактична читабельність усіх streams, size та SHA-256.

Backup старого формату без `format_version: 2` або без поточного `photo_inventory` навмисно відхиляється: старий count-only manifest не дає достатніх гарантій цілісності. Перед оновленням зберігайте старий архів як аварійну копію, але після оновлення обов’язково створіть новий format-v2 backup.

## Restore

```bash
php tools/verify_backup.php /private/path/mygallery_backup.zip
php tools/restore.php /private/path/mygallery_backup.zip
```

Коли буде запит, введіть `RESTORE`. Інструмент:

1. повністю перевіряє backup до confirmation;
2. розпаковує всі media у приховані staging-директорії на тому самому filesystem;
3. повторно перевіряє точну кількість записаних bytes і SHA-256;
4. запускає транзакцію БД, застосовує DML dump і записує operation marker у `schema_migrations`;
5. перейменовує поточні media-директорії в rollback-копії та атомарно активує staging;
6. commit-ить БД лише після успішного directory swap;
7. видаляє rollback-копії, marker і `storage/restore_journal.json`.

Якщо staging, SQL або swap завершується помилкою, поточні дані не видаляються або повертаються з rollback-копій. Якщо процес/сервер аварійно зупинився, повторно запустіть `tools/restore.php`: перед роботою з новим ZIP він перевірить journal і DB marker, після чого детерміновано завершить committed restore або поверне старі media для uncommitted restore.

Поки існує `storage/restore_journal.json`, не запускайте сайт і не видаляйте `.restore-stage-*` / `.restore-old-*` вручну. Restore додатково бере exclusive media maintenance lock і відмовляється працювати з symlink/junction media targets.

## Обов’язкова перевірка після restore

```bash
php tools/self_check.php
php tests/run.php
```

На тестовому сервері також перевірте login, albums/tags/share links, private media, download original/album ZIP та SHA-256 кількох відновлених оригіналів. Реальний restore завжди спочатку випробовуйте на disposable DB/копії інсталяції.

## Manual restore

Manual import лишається аварійним варіантом і не має автоматичного rollback: розпакуйте перевірений format-v2 ZIP, імпортуйте `mygallery_backup/database.sql` та скопіюйте media з відповідних підпапок. Перед цим окремо збережіть поточну БД і media.

## Runtime cleanup

Runtime sessions, logs, trash, rate-limit/lock файли та restore staging не входять до backup payload. Звичайні runtime-файли можна прибирати командою:

```bash
php tools/cleanup_runtime.php --apply
```

Не запускайте cleanup вручну для restore journal/staging під час незавершеного restore.
