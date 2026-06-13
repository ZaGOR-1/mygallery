# Known limitations and potential bugs

Критичних відомих уразливостей у цьому release package не зафіксовано, але є кілька обмежень і потенційних проблем, які варто тримати в полі зору.

## P1: Legacy originals

`public/uploads/originals` залишена тільки для сумісності зі старими версіями. На Apache вона захищена `.htaccess`, але на Nginx або неправильно налаштованому сервері `.htaccess` не допоможе.

Що робити:

```bash
php tools/migrate_legacy_originals.php
php tools/migrate_legacy_originals.php --apply
php tools/cleanup_orphans.php
```

Після міграції папка `public/uploads/originals` має бути порожньою, окрім `.gitkeep`/`.htaccess`.

## P1: SQL-файли прив’язані до `my_photo_gallery`

`schema.sql` і міграції за замовчуванням використовують базу `my_photo_gallery`. Якщо на сервері база має іншу назву, перед імпортом треба змінити або прибрати `USE my_photo_gallery;`.

Це не runtime-баг, але це може зламати deployment на нестандартній назві БД.

## P1: Trash recovery потребує ручного запуску

Якщо сервер впаде під час видалення фото, у `storage/trash` можуть залишитися manifest-файли. Їх треба перевірити вручну:

```bash
php tools/recover_trash.php
```

Поточний recovery tool корисний, але його варто покращити: після успішного restore manifest має прибиратися або переходити в done-стан.

## P1: Admin session freshness

Адмін-сесія має idle timeout, але бажано додати перевірку, що admin-запис досі існує в БД. Інакше після видалення або зміни адміна стара сесія може жити до timeout.

## P1: HSTS ще не ввімкнений автоматично

Production уже вимагає `https://APP_URL`, але HSTS header ще не додається автоматично. Це треба додати тільки для `APP_ENV=production` і HTTPS, щоб не зламати локальний WampServer на HTTP.

## P1: GD і memory edge cases

Upload має перевірки MIME, розміру, габаритів і пікселів, але для дуже великих або пошкоджених JPEG бажано ще точніше перевіряти результати GD-функцій і memory limit.

## P2: Пошук через SQL LIKE

Пошук через `LIKE` нормальний для невеликої персональної галереї. Якщо фото стане дуже багато, варто перейти на FULLTEXT або інший індексований пошук.

## P2: Немає bulk upload

Фото завантажуються по одному. Це не баг, але для реального наповнення галереї буде незручно.

## Operational notes

- Потрібні PHP extensions: `pdo_mysql`, `gd`, `fileinfo`, `exif`, `mbstring`.
- `tools/self_check.php` має запускатися після деплою і після зміни конфігурації.
- Runtime cleanup/log rotation для `storage/logs`, `storage/sessions`, `storage/trash` треба налаштовувати окремо на сервері.
- Backup має включати БД, `storage/originals`, `public/uploads/large` і `public/uploads/thumbnails`.
