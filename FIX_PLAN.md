# Fix Plan

Дата: 2026-06-11

## 1. Збереження справжнього оригіналу фотографії

* Статус: Fixed
* Ризик: High для якості фото і EXIF, Medium для безпеки
* Фактична поведінка: `public/admin/upload.php` викликає `save_corrected_original()`, а ця функція відкриває JPEG через GD і перезаписує файл через `imagejpeg()`. Через це оригінал не є byte-for-byte копією upload-а.
* Файли: `public/admin/upload.php`, `app/includes/functions.php`, `README.md`
* Виправлення: переносити upload у storage/originals через `move_uploaded_file()`, створювати `large` і `thumbnail` як похідні файли, orientation застосовувати тільки до похідних.
* Перевірка: SHA-256 source/uploaded original має збігатися; EXIF читається з original.

## 2. CSRF із порожнім токеном

* Статус: Fixed
* Ризик: Medium
* Фактична поведінка: `verify_csrf()` приводить session token до string і не відхиляє порожній session token окремо.
* Файли: `app/includes/csrf.php`, `tools/self_check.php`
* Виправлення: явно відхиляти missing/non-string/empty submitted і session token.
* Перевірка: правильний, неправильний, порожній, відсутній request token і відсутній session token.

## 3. EXIF Orientation 1-8

* Статус: Fixed
* Ризик: Medium
* Фактична поведінка: Orientation 5 і 7 переплутані; orientation застосовується до збереженого original.
* Файли: `app/includes/functions.php`, `tools/self_check.php`
* Виправлення: виправити transpose/transverse, застосовувати orientation тільки до derived images.
* Перевірка: GD-тест на несиметричному 3x2 зображенні для всіх orientation 1-8.

## 4. Захист оригіналів від прямого доступу

* Статус: Fixed
* Ризик: Medium
* Фактична поведінка: existing originals лежать у `public/uploads/originals` і можуть відкриватися напряму, якщо Apache не забороняє доступ.
* Файли: `app/includes/functions.php`, `public/admin/upload.php`, `public/uploads/originals/.htaccess`, `README.md`, `.gitignore`
* Виправлення: нові originals зберігати у `storage/originals`; для legacy `public/uploads/originals` додати Apache deny.
* Перевірка: public pages використовують large/thumbnail; прямий URL до public original має давати 403.

## 5. Rate limiting входу

* Статус: Fixed
* Ризик: Medium
* Фактична поведінка: `register_failed_login()` робить SELECT, потім INSERT/UPDATE без транзакції або lock.
* Файли: `public/admin/login.php`
* Виправлення: виконувати update лічильника у транзакції з `SELECT ... FOR UPDATE`.
* Перевірка: invalid login не розкриває існування username; записи cleanup-ляться.

## 6. Узгоджене видалення файлів і записів БД

* Статус: Fixed
* Ризик: Medium
* Фактична поведінка: DB record видаляється перед unlink; при помилці unlink можливі orphan-файли.
* Файли: `public/admin/delete.php`, `app/includes/functions.php`
* Виправлення: переміщати файли у `storage/trash`, видаляти DB record у транзакції, після commit остаточно прибирати файли; при DB failure повертати файли.
* Перевірка: dry static review, без видалення реальних фото.

## 7. APP_DEBUG і журналювання

* Статус: Fixed
* Ризик: Medium
* Фактична поведінка: `APP_DEBUG` є в config, але не налаштовує `display_errors` і logging.
* Файли: `app/includes/functions.php`, `config/config.php`, `.gitignore`
* Виправлення: додати runtime error settings і `app_log_exception()` у `storage/logs`.
* Перевірка: lint; production behavior статично перевірити за config path.

## 8. Обмеження великих зображень і пам’яті GD

* Статус: Fixed
* Ризик: Medium
* Фактична поведінка: є pixel limit, але немає оцінки пам’яті перед `imagecreatefromjpeg()`.
* Файли: `app/includes/functions.php`, `public/admin/upload.php`
* Виправлення: додати estimate проти `memory_limit` з запасом.
* Перевірка: lint; oversized dimensions/memory статично.

## 9. Заборона кешування адмінських сторінок

* Статус: Fixed
* Ризик: Low
* Фактична поведінка: session може додавати no-cache, але немає явної функції для всіх admin pages.
* Файли: `app/includes/auth.php`, `app/includes/functions.php`
* Виправлення: після `require_admin()` виставляти `Cache-Control: no-store, no-cache, must-revalidate, max-age=0`, `Pragma: no-cache`, `Expires: 0`.
* Перевірка: curl admin redirect/auth headers where possible.

## 10. ZIP, Git і конфігурація

* Статус: Fixed
* Ризик: Low
* Фактична поведінка: `.gitignore` не містить storage, архіви, IDE/tmp backup patterns.
* Файли: `.gitignore`, `README.md`
* Виправлення: розширити ignore і README ZIP-інструкціями.
* Перевірка: `git status --short --ignored`.

## Виконані зміни

- `storage/originals` додано як приватне сховище byte-for-byte оригіналів.
- `public/uploads/originals/.htaccess` блокує прямий HTTP-доступ до legacy originals.
- Upload переносить файл через `move_uploaded_file()` і створює `large`/`thumbnail` як похідні.
- Orientation 5 і 7 виправлено; orientation застосовується тільки до похідних файлів.
- CSRF тепер відхиляє missing/non-string/empty session або submitted token.
- Login rate-limit оновлюється у транзакції з `SELECT ... FOR UPDATE`.
- Delete-flow переміщує файли в `storage/trash`, видаляє DB record у транзакції, потім остаточно чистить trash або відновлює файли при помилці.
- `APP_DEBUG` керує `display_errors`; PHP/app logs пишуться в `storage/logs`.
- Перед GD додана оцінка пам’яті відносно `memory_limit`.
- Admin pages після `require_admin()` отримують no-store headers.
- `.gitignore` розширено для storage, logs, backups, archives, IDE/tmp.
- README оновлено щодо storage, ZIP, legacy originals і неможливості відновити вже перезбережений EXIF/якість.
- Додано `tools/self_check.php` для CSRF і Orientation 1-8.

## Виконані команди і результати

- `php -l` для всіх PHP-файлів: синтаксичних помилок немає.
- `node --check public/assets/js/main.js`: помилок немає.
- `php tools/self_check.php`: `Self-check passed.`
- `curl http://mygallery/uploads/originals/<file>.jpg`: `403 Forbidden`.
- `curl http://mygallery/uploads/test.php`: `403 Forbidden`.
- `curl http://mygallery/`: `200 OK` із security headers.
- `curl http://mygallery/admin/index.php`: `302` на login із no-cache/session headers.

## Needs manual testing

- Authenticated upload реального JPEG і перевірка SHA-256 original до/після upload.
- Перевірка збереження EXIF/Nikon MakerNotes на реальному Nikon D7100 JPEG.
- Authenticated edit/delete на тестовому фото.
- Production mode з `APP_DEBUG=false`.
- Вимкнені PHP extensions GD/EXIF/Fileinfo.
- Повна runtime-перевірка підпапки `/MyPhotoGallery/`.
- Linux LAMP runtime на Debian/Ubuntu.
