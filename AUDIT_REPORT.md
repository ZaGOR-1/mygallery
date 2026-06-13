# Audit report summary

Актуальний короткий підсумок аудиту для hardened release package.

## Загальний висновок

Проєкт у поточному стані виглядає добре для навчального PHP-проєкту і невеликої персональної фотогалереї. У коді не виявлено критичних проблем на рівні очевидного SQL injection, stored/reflected XSS, обходу адмінки, CSRF-дірки або завантаження PHP-shell замість фото.

## Що добре

- Release package не містить `.git/`, `config/database.php`, логів, session-файлів або приватних JPEG.
- Apache/Nginx має відкривати тільки `public/`.
- Нові оригінали зберігаються приватно в `storage/originals`.
- Upload приймає тільки `image/jpeg` і додатково перевіряє файл через `getimagesize()`.
- SQL-запити виконуються через PDO prepared statements.
- POST-дії захищені CSRF.
- Адмін-вхід має password hashing і login rate limiter.
- `APP_DEBUG` за замовчуванням вимкнений.
- Production startup блокує небезпечні налаштування.
- Є CLI tools для setup, self-check, cleanup, legacy migration і trash recovery.

## Що ще бажано виправити

Пріоритетні пункти перенесені в `POST_MVP_ROADMAP.md` і `BUGS.md`:

1. Зробити SQL-міграції portable без жорсткого `USE my_photo_gallery`.
2. Остаточно закрити legacy originals у `public/uploads/originals`.
3. Покращити поведінку `tools/recover_trash.php` після успішного restore.
4. Додати перевірку актуальності admin-сесії в БД.
5. Додати HSTS тільки для production HTTPS.
6. Посилити перевірки GD/memory edge cases.

## Перевірки, які варто робити після змін

```bash
php -l path/to/file.php
php tools/self_check.php
php tools/cleanup_orphans.php
```

Також вручну перевірити:

- login/logout;
- upload валідного JPEG;
- відмову для не-JPEG;
- EXIF і orientation;
- пошук/фільтри/сортування;
- створення/редагування/видалення альбому;
- редагування і видалення фото;
- recovery сценарій після перерваного видалення, якщо змінювався delete/recover код.

## Статус документації

Документація оновлена так, щоб реалізовані можливості були в `IMPLEMENTED_FEATURES.md`, а майбутні задачі — в `POST_MVP_ROADMAP.md`. Це прибирає стару проблему, коли roadmap одночасно містив уже реалізований пошук/альбоми і майбутні задачі.
