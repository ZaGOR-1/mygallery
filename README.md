# My Photo Gallery

MVP персональної фотогалереї на PHP 8.2+, Apache і MySQL/MariaDB. Проєкт зроблений як простий студентський PHP-проєкт без Laravel, React, Bootstrap, Composer і зайвої архітектури.

Поточна версія V6 підтримує JPEG-upload, EXIF, приватні оригінали, прев’ю, large-версії фото, альбоми, теги, пошук, фільтри, сортування, лайтбокс із zoom/pan, адмінпанель, статистику, admin health-check, захищену видачу оптимізованих зображень через `public/media.php`, завантаження оригіналу тільки для адміна, CSRF-захист, login rate limiter і службові CLI-інструменти для перевірки, backup, release-збірки та обслуговування.

## Поточний статус

Проєкт підходить для локального використання, навчання і невеликої персональної галереї. Для production обов’язково треба виконати production-налаштування з цього README: HTTPS, `APP_ENV=production`, `APP_DEBUG=false`, окремий користувач БД, правильний `DocumentRoot` на `public/`, права на папки та перевірка `tools/self_check.php`.

## Можливості

- головна сторінка й адаптивна галерея;
- пагінація по 12 фотографій;
- публічний пошук за назвою й описом; адмінка та token-based share view можуть шукати також за оригінальною назвою файла;
- FULLTEXT-пошук із fallback на `LIKE`, якщо індекси ще не застосовані;
- фільтри за альбомом, тегом, камерою та датою зйомки;
- теги для фотографій з many-to-many зв’язком;
- сортування за датою додавання, датою зйомки і назвою;
- сторінка окремої фотографії з EXIF-даними;
- перехід до попередньої та наступної фотографії;
- лайтбокс на сторінці фото з кнопками зуму, колесиком миші та перетягуванням ЛКМ;
- проста адмінпанель;
- статистика в адмінці: фото, альбоми, теги, EXIF, сховище, камери, об’єктиви, місяці;
- створення, перейменування і видалення альбомів;
- завантаження, редагування і видалення фотографій;
- серверне обмеження невдалих спроб входу;
- CSRF-захист для POST-форм;
- завантаження тільки `image/jpeg`;
- максимальний розмір одного JPEG — 50 МБ;
- обмеження розмірів зображення до 8000x8000 або 50 МП;
- випадкові імена файлів на сервері;
- приватне byte-for-byte зберігання оригінальних JPEG у `storage/originals`;
- веб-версія фото до 4000 px завширшки;
- прев’ю до 600 px завширшки;
- protected image delivery через `media.php`: публічні фото доступні всім, приватні derivatives — тільки адміну або через чинний share token;
- responsive images через `srcset` і `sizes`;
- автоматичне виправлення EXIF Orientation;
- базові security headers;
- службові CLI-інструменти для setup, self-check, cleanup, міграції legacy originals, recovery trash, backup, regenerate images і чистої release-збірки.

## Структура проєкту

```text
app/includes/                         спільні PHP-функції, авторизація, CSRF, шаблони
app/includes/maintenance_functions.php containment і media maintenance lock
app/includes/share_functions.php      lookup/expiry helpers для share links
app/includes/media_access_functions.php protected-media authorization helpers
app/includes/album_zip_functions.php  ZIP naming/cache/cooldown/stream helpers
app/includes/tag_service.php          transactional tag mutations + photo revisions
config/                               конфігурація застосунку і БД
database/schema.sql                   повна структура бази для чистої установки
database/migrations/                  SQL-міграції для вже встановленої бази
public/                               єдина публічна папка сайту
public/admin/                         адміністративні сторінки
public/admin/health.php               web health-check тільки для адміністратора
public/admin/stats.php                статистика контенту і media-сховища
public/admin/download.php             download приватного оригіналу тільки для адміністратора
public/media.php                      захищена видача large/thumbnail/WebP/AVIF
public/assets/                        CSS і JavaScript
public/.htaccess                      переносимі Apache-правила без php_value і ErrorDocument
public/404.php                         сторінка помилки 404
public/500.php                         сторінка помилки 500
public/uploads/large/                 оптимізовані веб-версії JPEG, прямий web-доступ заборонений
public/uploads/thumbnails/            прев’ю JPEG, прямий web-доступ заборонений
public/uploads/originals/             legacy-only папка, нові оригінали тут не зберігати
storage/originals/                    приватні byte-for-byte оригінальні JPEG
storage/trash/                        тимчасовий кошик під час видалення
storage/logs/                         приватні логи застосунку
storage/sessions/                     приватні PHP session-файли
tools/setup.php                       консольне створення першого адміністратора
tools/self_check.php                  швидка перевірка структури, модулів і доступів
tools/build_release.php               збірка чистого release ZIP без приватних файлів
tools/backup.php                      приватний backup БД і media-файлів
tools/verify_backup.php               повна size/SHA-256/stream перевірка backup format v2
tools/restore.php                     staged transactional restore з rollback/recovery journal
tools/regenerate_images.php           регенерація large/thumbnail із storage/originals
tools/cleanup_orphans.php             пошук зайвих файлів і відсутніх media-файлів
tools/migrate_legacy_originals.php    перенесення старих public originals у storage/originals
tools/recover_trash.php               відновлення або очищення trash manifest-файлів
VERSION                               поточна версія проєкту
tools/lib/SimpleZipWriter.php         internal fallback/fault-test ZIP writer
tools/lib/SafeCliZipOutput.php        safe atomic policy для CLI ZIP output
tools/lib/BackupArchiveValidator.php  спільна fail-closed validation для backup/verify/restore
README.md                             основна інструкція запуску
AGENTS.md                             repo-only правила для AI/Codex-агента
docs/IMPLEMENTED_FEATURES.md          що вже реалізовано
CHANGELOG.md                          історія змін версій
docs/BUGS.md                          відомі обмеження і потенційні баги
ROADMAP.md                            майбутні задачі, не список уже реалізованого
docs/AUDIT_REPORT.md                  repo-only короткий підсумок останнього аудиту
docs/SECURITY_AUDIT.md                repo-only детальний технічний аудит
docs/AUDIT_PROMPT.md                  repo-only промпт для повторного аудиту AI-агентом
docs/BACKUP_RESTORE.md                порядок backup і restore
```

Apache або Nginx має відкривати тільки папку `public/`. Папки `app/`, `config/`, `database/`, `storage/`, `tools/` і markdown-документи не повинні бути доступні напряму через браузер.

Релізний ZIP не повинен містити `.git/`, `config/database.php`, реальні фото з `storage/originals` / `public/uploads`, логи `*.log`, session-файли `sess_*`, backup-архіви або тимчасові файли. Перед передачею проєкту іншій людині збирайте чисту копію з `.gitkeep` у порожніх директоріях.

## Важливо про оригінали фото

Нові завантаження зберігають оригінальний JPEG у `storage/originals` без повторного стиснення через GD.

`public/uploads/originals` залишена тільки для сумісності зі старими встановленнями. У новій версії вона має бути порожньою, захищеною через `.htaccess` і не повинна використовуватися як нормальне сховище. Якщо у старому проєкті там були файли, перенесіть їх:

```bash
php tools/migrate_legacy_originals.php
php tools/migrate_legacy_originals.php --apply
```

Старі файли, які в попередніх версіях уже були повторно збережені через GD, неможливо автоматично повернути до початкової якості. Щоб повернути початковий EXIF, ICC-профіль, Nikon MakerNotes і якість, треба повторно завантажити їх із початкових JPEG.

## Користування

- `gallery.php` підтримує GET-фільтри: пошук, альбом, тег, камера, дата зйомки і сортування. Звичайна публічна галерея не шукає за оригінальними назвами файлів; це доступно в адмінці та у token-based share view.
- Альбоми створюються в `admin/albums.php` або прямо під час завантаження/редагування фото.
- Теги вводяться через кому під час завантаження або редагування фото. Один тег можна використовувати для багатьох фото.
- Картки в галереї відкривають сторінку окремої фотографії, щоб EXIF і навігація залишалися доступними без JavaScript.
- На сторінці фото клік по зображенню відкриває лайтбокс.
- В адмінпанелі список фотографій підтримує пошук, фільтри за альбомом/камерою/датою та сортування.

## Встановлення на WampServer у Windows

1. Встановіть WampServer з Apache, PHP 8.2+ і MySQL або MariaDB.
2. Увімкніть PHP-модулі: `pdo_mysql`, `gd`, `exif`, `fileinfo`, `mbstring`, `zip`. У WampServer перевірте, що `php_zip`/`extension=zip` увімкнено саме для Apache і CLI PHP.
3. Скопіюйте проєкт у папку, наприклад:

```text
C:\wamp64\domains\mygallery\
```

4. У WampServer створіть VirtualHost із `DocumentRoot` на папку `public`:

```apache
DocumentRoot "c:/wamp64/domains/mygallery/public"

<Directory "c:/wamp64/domains/mygallery/public/">
    Options -Indexes +FollowSymLinks
    AllowOverride All
    Require local
</Directory>
```

5. У `C:\Windows\System32\drivers\etc\hosts` має бути запис:

```text
127.0.0.1 mygallery
::1 mygallery
```

6. Створіть базу і таблиці:

```powershell
C:\wamp64\bin\mysql\mysql9.1.0\bin\mysql.exe -h 127.0.0.1 -P 3306 -u root --execute="CREATE DATABASE IF NOT EXISTS my_photo_gallery CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
C:\wamp64\bin\mysql\mysql9.1.0\bin\mysql.exe -h 127.0.0.1 -P 3306 -u root my_photo_gallery --execute="source database/schema.sql"
```

7. Скопіюйте `config/database.example.php` у `config/database.php`.
8. Для стандартного локального WampServer можна залишити:

```php
'DB_HOST' => '127.0.0.1',
'DB_PORT' => 3306,
'DB_NAME' => 'my_photo_gallery',
'DB_USER' => 'root',
'DB_PASSWORD' => '',
```

9. У `config/config.php` для локальної розробки перевірте:

```php
'APP_URL' => 'http://mygallery',
'APP_ENV' => 'local',
'APP_DEBUG' => false,
'UPLOAD_MAX_SIZE' => 50 * 1024 * 1024,
'MAX_IMAGE_WIDTH' => 8000,
'MAX_IMAGE_HEIGHT' => 8000,
'MAX_IMAGE_PIXELS' => 50 * 1000 * 1000,
'LARGE_MAX_WIDTH' => 4000,
```

10. Для великих JPEG перевірте PHP-ліміти у `php.ini`:

```ini
upload_max_filesize = 64M
post_max_size = 72M
memory_limit = 512M
```

11. Створіть першого адміністратора з консолі:

```powershell
C:\wamp64\bin\php\php8.3.14\php.exe tools\setup.php
```

12. Запустіть самоперевірку:

```powershell
C:\wamp64\bin\php\php8.3.14\php.exe tools\self_check.php
```

13. Відкрийте `http://mygallery/admin/login.php` і увійдіть.

## Оновлення вже встановленої бази

Якщо база вже існувала раніше, застосуйте міграції по черзі:

```powershell
C:\wamp64\bin\mysql\mysql9.1.0\bin\mysql.exe -h 127.0.0.1 -P 3306 -u root my_photo_gallery --execute="source database/migrations/2026_06_12_add_albums.sql"
C:\wamp64\bin\mysql\mysql9.1.0\bin\mysql.exe -h 127.0.0.1 -P 3306 -u root my_photo_gallery --execute="source database/migrations/2026_06_13_hardening.sql"
C:\wamp64\bin\mysql\mysql9.1.0\bin\mysql.exe -h 127.0.0.1 -P 3306 -u root my_photo_gallery --execute="source database/migrations/2026_06_13_add_tags.sql"
C:\wamp64\bin\mysql\mysql9.1.0\bin\mysql.exe -h 127.0.0.1 -P 3306 -u root my_photo_gallery --execute="source database/migrations/2026_06_15_add_original_sha256.sql"
C:\wamp64\bin\mysql\mysql9.1.0\bin\mysql.exe -h 127.0.0.1 -P 3306 -u root my_photo_gallery --execute="source database/migrations/2026_06_15_add_album_covers.sql"
C:\wamp64\bin\mysql\mysql9.1.0\bin\mysql.exe -h 127.0.0.1 -P 3306 -u root my_photo_gallery --execute="source database/migrations/2026_06_15_add_session_version.sql"
C:\wamp64\bin\mysql\mysql9.1.0\bin\mysql.exe -h 127.0.0.1 -P 3306 -u root my_photo_gallery --execute="source database/migrations/2026_06_15_create_share_links.sql"
C:\wamp64\bin\mysql\mysql9.1.0\bin\mysql.exe -h 127.0.0.1 -P 3306 -u root my_photo_gallery --execute="source database/migrations/2026_06_15_add_album_sort_order.sql"
C:\wamp64\bin\mysql\mysql9.1.0\bin\mysql.exe -h 127.0.0.1 -P 3306 -u root my_photo_gallery --execute="source database/migrations/2026_06_15_add_album_privacy.sql"
C:\wamp64\bin\mysql\mysql9.1.0\bin\mysql.exe -h 127.0.0.1 -P 3306 -u root my_photo_gallery --execute="source database/migrations/2026_06_15_add_photo_dominant_color.sql"
C:\wamp64\bin\mysql\mysql9.1.0\bin\mysql.exe -h 127.0.0.1 -P 3306 -u root my_photo_gallery --execute="source database/migrations/2026_07_10_add_photo_lock_version.sql"
C:\wamp64\bin\mysql\mysql9.1.0\bin\mysql.exe -h 127.0.0.1 -P 3306 -u root my_photo_gallery --execute="source database/migrations/2026_07_10_add_share_target_check.sql"
C:\wamp64\bin\mysql\mysql9.1.0\bin\mysql.exe -h 127.0.0.1 -P 3306 -u root my_photo_gallery --execute="source database/migrations/2026_07_12_hash_share_tokens.sql"
```

SQL-файли не містять `USE`, тому застосовуються до бази, яку ви явно передали в команді `mysql ... database_name < file.sql`.

## Встановлення на LAMP у VM Proxmox

1. Створіть VM з Debian або Ubuntu Server.
2. Встановіть Apache, MySQL/MariaDB, PHP і потрібні модулі:

```bash
sudo apt update
sudo apt install apache2 mysql-server php php-mysql php-gd php-exif php-mbstring php-zip unzip
```

`fileinfo` зазвичай входить у стандартний PHP-пакет. Перевірте capabilities через `php -m | grep -E 'fileinfo|zip'`; `tools/self_check.php` і `/admin/health.php` також позначають відсутній `zip` як помилку.

3. Скопіюйте файли проєкту на сервер, наприклад у `/var/www/mygallery`.
4. Створіть БД і імпортуйте схему:

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS my_photo_gallery CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p my_photo_gallery < database/schema.sql
```

5. Створіть окремого runtime-користувача БД лише з CRUD-правами та окремого maintenance-користувача для migrations/restore:

```sql
CREATE USER 'gallery_runtime'@'localhost' IDENTIFIED BY 'strong_runtime_password';
GRANT SELECT, INSERT, UPDATE, DELETE ON my_photo_gallery.* TO 'gallery_runtime'@'localhost';

CREATE USER 'gallery_maintenance'@'localhost' IDENTIFIED BY 'strong_maintenance_password';
GRANT ALL PRIVILEGES ON my_photo_gallery.* TO 'gallery_maintenance'@'localhost';
FLUSH PRIVILEGES;
```

Web-застосунок у `config/database.php` має використовувати тільки `gallery_runtime`. Облікові дані `gallery_maintenance` задавайте через захищені environment variables лише на час `tools/migrate.php` або контрольованого restore; не зберігайте їх у web/FPM environment.

6. Скопіюйте `config/database.example.php` у `config/database.php` і впишіть runtime-користувача.
7. У `config/config.php` змініть:

```php
'APP_URL' => 'https://gallery.example.com',
'APP_ENV' => 'production',
'APP_DEBUG' => false,
```

8. Створіть першого адміністратора:

```bash
php tools/setup.php
```

9. Надайте Apache право запису тільки до runtime/upload-папок:

```bash
sudo chown -R root:www-data /var/www/mygallery
sudo chown -R www-data:www-data /var/www/mygallery/storage/originals /var/www/mygallery/storage/trash /var/www/mygallery/storage/logs /var/www/mygallery/storage/sessions /var/www/mygallery/storage/share_ratelimit /var/www/mygallery/storage/download_locks /var/www/mygallery/public/uploads/large /var/www/mygallery/public/uploads/thumbnails
sudo find /var/www/mygallery -type d -exec chmod 750 {} \;
sudo find /var/www/mygallery -type f -exec chmod 640 {} \;
sudo chmod 750 /var/www/mygallery/storage/originals /var/www/mygallery/storage/trash /var/www/mygallery/storage/logs /var/www/mygallery/storage/sessions /var/www/mygallery/storage/share_ratelimit /var/www/mygallery/storage/download_locks /var/www/mygallery/public/uploads/large /var/www/mygallery/public/uploads/thumbnails
```

Не використовуйте `chmod 777`.

## Apache VirtualHost

Приклад production-конфігурації:

```apache
<VirtualHost *:80>
    ServerName gallery.example.com
    DocumentRoot /var/www/mygallery/public

    <Directory /var/www/mygallery/public>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>

    ErrorLog ${APACHE_LOG_DIR}/mygallery_error.log
    CustomLog ${APACHE_LOG_DIR}/mygallery_access.log combined
</VirtualHost>
```

Увімкніть сайт і перезапустіть Apache:

```bash
sudo a2ensite mygallery.conf
sudo systemctl reload apache2
```

Для роботи `.htaccess` має бути дозволено `AllowOverride All` або принаймні потрібні категорії `FileInfo`/`Indexes`.

## Nginx Server Block

Якщо ви використовуєте Nginx, зверніть увагу, що файли `.htaccess` будуть проігноровані. Вам необхідно явно закрити прямий доступ до `uploads/large`, `uploads/thumbnails`, `uploads/originals`, а також до `.php` файлів у `uploads`. Оптимізовані зображення має віддавати `media.php`.

Приклад production-конфігурації для Nginx:

```nginx
server {
    listen 80;
    server_name gallery.example.com;
    root /var/www/mygallery/public;
    index index.php index.html;

    error_page 404 /404.php;
    error_page 500 /500.php;

    # Усе uploaded media видається тільки через авторизований media.php.
    location ^~ /uploads/ {
        deny all;
    }

    # Do not use ^~ here: the dotfile deny regex below must still take precedence.
    location /assets/ {
        try_files $uri =404;
    }

    location = / {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root/index.php;
        include fastcgi_params;
    }

    # Виконуються лише відомі public entry points; довільний PHP отримує 404.
    location ~ ^/(?:index|albums|gallery|photo|share|media|download_album|404|500)\.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ ^/admin/(?:index|login|logout|upload|edit|delete|bulk_edit|albums|tags|share|download|health|stats|trash)\.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ (^|/)\. {
        deny all;
    }

    location / {
        return 404;
    }
}
```

Замініть версію/шлях PHP-FPM відповідно до сервера. Після зміни конфігурації обов'язково перевірте, що `/missing-route`, невідомий `*.php`, hidden/control files і прямі `/uploads/*` повертають 403/404, а не homepage із кодом 200.

## Production-налаштування

- `APP_ENV=production`;
- `APP_DEBUG=false`;
- `APP_URL` має починатися з `https://`;
- окремий runtime DB user тільки з `SELECT/INSERT/UPDATE/DELETE`; DDL/restore виконує окремий maintenance user;
- складний пароль адміністратора;
- HTTPS через Let’s Encrypt або інший сертифікат;
- HSTS автоматично додається застосунком для `APP_ENV=production` і HTTPS-запитів;
- якщо HTTPS завершується на reverse proxy, додайте IP proxy у `TRUSTED_PROXIES`, щоб застосунок довіряв `X-Forwarded-Proto: https` тільки від цього proxy;
- Apache/Nginx відкриває тільки `public/`;
- `storage/` не входить у `DocumentRoot`;
- у `public/uploads` не виконується PHP;
- старі файли з `public/uploads/originals` перенесені в `storage/originals`;
- налаштовано backup;
- налаштовано cleanup/logrotate для `storage/logs`, `storage/sessions`, `storage/trash`;
- після зміни конфігурації запущено `php tools/self_check.php`.

Корисні системні налаштування:

```ini
expose_php = Off
```

```apache
ServerTokens Prod
ServerSignature Off
```

Для HTTPS-production застосунок додає HSTS на реальних HTTPS-запитах або на запитах від довіреного reverse proxy з `X-Forwarded-Proto: https`. HTTP -> HTTPS redirect краще налаштовувати на рівні Apache/Nginx або reverse proxy, щоб не ламати локальний HTTP-режим.

Якщо TLS завершується на reverse proxy, у `config/config.php` вкажіть тільки IP-адреси proxy, яким можна довіряти:

```php
'TRUSTED_PROXIES' => ['127.0.0.1'],
```

Або задайте їх через змінну середовища, розділяючи кілька IP комами:

```bash
TRUSTED_PROXIES=127.0.0.1,10.0.0.10
```

Не додавайте в `TRUSTED_PROXIES` IP-адреси звичайних клієнтів або широкі мережі без потреби.

## Службові інструменти

Створення першого адміністратора:

```bash
php tools/setup.php admin
ADMIN_PASSWORD="strong-password" php tools/setup.php admin
printf "strong-password" | php tools/setup.php admin --password-from-stdin
```

`setup.php` більше не вимагає передавати пароль через видимий CLI-аргумент. Найбезпечніші portable-варіанти — `ADMIN_PASSWORD` або `--password-from-stdin`. Якщо вводити пароль інтерактивно, він може бути видимим у консолі.

Самоперевірка структури, конфігурації, модулів і доступів:

```bash
php tools/self_check.php
```

На старій інсталяції спочатку перенесіть legacy originals із публічної папки та перевірте dry-run:

```bash
php tools/migrate_legacy_originals.php
php tools/migrate_legacy_originals.php --apply
```

Після migration шукайте зайві media і лише тоді запускайте destructive cleanup:

```bash
php tools/cleanup_orphans.php
php tools/cleanup_orphans.php --delete
```

`cleanup_orphans.php --delete` fail-closed: DB-referenced public original без private copy або з іншим SHA-256 не видаляється, а весь destructive run зупиняється з non-zero exit. Видаляються лише справжні orphan files і hash-verified public duplicates.

Перевірка trash manifest-файлів після аварійного видалення:

```bash
php tools/recover_trash.php
php tools/recover_trash.php --apply
php tools/recover_trash.php --apply --purge-deleted
```

Перерване restore автоматично продовжується зі станів `restore_in_progress`/`restore_committed`. Recovery приймає live/trash duplicates лише після exact SHA-256 equality; конфліктні або відсутні обидві копії fail-closed і потребують перевірки оператора.

Очищення старих службових файлів (логи, сесії, кошик) старше 7-30 днів:

```bash
php tools/cleanup_runtime.php
php tools/cleanup_runtime.php --apply
```

Maintenance-команди повертають ненульовий exit code, якщо запитану файлову
операцію не вдалося завершити. Файл, який у цей момент утримує інший процес,
`cleanup_runtime.php` безпечно пропускає, окремо рахує як `busy` і не вважає
помилкою запуску.

Для Linux production рекомендується додати це в `cron` для щоденного очищення:
```bash
0 3 * * * /usr/bin/php /var/www/mygallery/tools/cleanup_runtime.php --apply > /dev/null 2>&1
```

Регенерація optimized-зображень із приватних оригіналів:

```bash
php tools/regenerate_images.php --all
php tools/regenerate_images.php --large
php tools/regenerate_images.php --thumbnails
php tools/regenerate_images.php --all --photo-id=123
php tools/regenerate_images.php --all --dry-run
```

JPEG/WebP/AVIF replacements публікуються через same-directory temporary file та atomic rename. WebP/AVIF проходять image-format/size validation і 0640 policy; якщо encoder недоступний або generation failed, stale variant видаляється, а UI fallback-иться на JPEG.

Збірка чистого release ZIP:

```bash
php tools/build_release.php
```

На виході будуть `dist/mygallery_<VERSION>_release.zip`, checksum `*.zip.sha256` і audit metadata `*.zip.provenance.json`. Builder за замовчуванням fail-closed відмовляється працювати з dirty Git tree, unreachable commit або недоступними Git metadata та додає `BUILD_INFO.json` із commit і SHA-256 inventory payload-файлів. Clean payload береться тільки з exact `source_commit` tree, тому Git-ignored IDE/system/custom files не можуть непомітно потрапити в artifact; emergency dirty build використовує лише tracked і non-ignored files. Порядок entries, modes і timestamps стабільні; timestamp береться з валідного `SOURCE_DATE_EPOCH`, інакше з часу reachable Git commit. Два builds одного commit у тому самому toolchain мають бути byte-for-byte однаковими; CI це перевіряє. `--allow-dirty` дозволений лише для явно неперевіреної локальної аварійної збірки; такий artifact отримує dirty/unverified metadata і не є production release. Після закриття ZIP builder повторно читає й хешує кожен payload entry, забороняє symlink/junction path escapes і автоматично блокує `.git/`, `config/database.php`, `.env`, session/log/tmp/share-rate-limit/backup-файли, restore/maintenance locks або реальні фото з upload/storage. Для Markdown діє production allowlist: у корені лишаються `README.md`, `CHANGELOG.md`, `ROADMAP.md`, а в `docs/` — тільки operational/feature docs. Agent, AI prompt та audit reports до release не входять.

Приватний backup:

```bash
php tools/backup.php
php tools/backup.php --include-config
```

Без `--include-config` файл `config/database.php` не потрапляє в backup ZIP. `tools/backup.php` відмовляється створювати backup усередині `public/`, використовує DB consistent snapshot + media maintenance lock і на Linux вимагає приватні права `0700/0600`. Backup format v2 містить DB photo inventory, точний allowlist, розмір і SHA-256 кожного payload-файлу; щойно створений ZIP автоматично проходить ту саму повну перевірку, яку використовують `verify_backup.php` і `restore.php`. Див. також `docs/BACKUP_RESTORE.md`.

Web health-check доступний після входу в адмінку:

```text
/admin/health.php
```

## Резервне копіювання

Регулярно зберігайте:

- дамп бази `my_photo_gallery`;
- `storage/originals`;
- `public/uploads/large`;
- `public/uploads/thumbnails`;
- `config/config.php` і `config/database.php` окремо від публічного репозиторію.

Приклад автоматичного backup ZIP:

```bash
php tools/backup.php
php tools/verify_backup.php backups/mygallery_backup_YYYYMMDD_HHMMSS.zip
```

Для ручного SQL-дампу також можна використати:

```bash
mysqldump -u gallery_user -p my_photo_gallery > backup.sql
```

Backup не можна зберігати всередині `public/` і не можна комітити в Git. `tools/backup.php` блокує `--output` у `public/`, але приватні backup-файли все одно треба зберігати поза `DocumentRoot`. Restore приймає лише поточний format v2, до extraction обмежує сукупний uncompressed size і compression ratio, перевіряє запас вільного місця, повністю перевіряє ZIP і staging-копію media, примусово задає приватним originals права 0700/0600, а потім координує транзакцію БД з directory swap та crash-recovery journal. Детальний порядок описаний у `docs/BACKUP_RESTORE.md`.

## Передача ZIP-архіву

> [!WARNING]
> **НІКОЛИ не створюйте ZIP-архів робочої папки вручну!**
> Ручне архівування може випадково злити ваші секрети (`config/database.php`), файли сесій, логів, а також приватні `.git` та `.env` файли або оригінали фотографій.

Замість ручного архівування **завжди використовуйте спеціальний CLI-інструмент**:

```bash
php tools/build_release.php
```

Цей скрипт безпечно скопіює тільки необхідний код, `.gitkeep`-файли, міграції та актуальні `.md` документи, та згенерує чистий релізний ZIP в папці `dist/`.

## Змінні середовища

`config/config.php` і `config/database.example.php` підтримують змінні середовища:

```bash
APP_URL=https://gallery.example.com
APP_ENV=production
APP_DEBUG=false
TRUSTED_PROXIES=127.0.0.1
DB_HOST=127.0.0.1
DB_PORT=3306
DB_NAME=my_photo_gallery
DB_USER=gallery_user
DB_PASSWORD=strong_password
ALBUM_ZIP_ENABLED=true
ALBUM_ZIP_CACHE_MAX_BYTES=2147483648
ALBUM_ZIP_MAX_GENERATION_SECONDS=120
ALBUM_ZIP_MAX_PHOTOS=200
ALBUM_ZIP_MAX_SOURCE_BYTES=524288000
ALBUM_ZIP_MAX_CONCURRENT_STREAMS=2
SHARE_RATE_LIMIT_MAX_REQUESTS=120
SHARE_RATE_LIMIT_WINDOW_SECONDS=60
SHARE_RATE_LIMIT_TTL_SECONDS=172800
SHARE_RATE_LIMIT_MAX_FILES_PER_SHARD=256
BULK_EDIT_MAX_PHOTOS=200
RESTORE_MAX_UNCOMPRESSED_BYTES=107374182400
RESTORE_MAX_COMPRESSION_RATIO=250
RESTORE_MIN_FREE_BYTES=268435456
```

У production застосунок спеціально не стартує, якщо `APP_DEBUG=true`, `APP_URL` не `https://`, або БД налаштована на `root` без пароля.

## Локальні CLI-перевірки

Якщо WampServer показує попередження на кшталт `Xdebug: File 'c:/wamp64/logs/xdebug.log' could not be opened`, це проблема локальної конфігурації Xdebug, а не MyGallery. Для чистого виводу перевірок можна або виправити шлях `xdebug.log` у php.ini, або запускати службові команди з вимкненим Xdebug:

```bash
php -d xdebug.mode=off tools/self_check.php
php -d xdebug.mode=off tests/run.php
```

Локально недоступні DB suites показуються окремо як `skipped` і не зараховуються до `passed`. Runner ніколи не підключається до звичайного `DB_NAME`: DB suites вимагають окремі `TEST_DB_HOST`, `TEST_DB_PORT`, `TEST_DB_NAME`, `TEST_DB_USER`, `TEST_DB_PASSWORD`, причому `TEST_DB_NAME` має містити `test`. Приклад обов’язкового regression-запуску:

```bash
APP_ENV=test TEST_DB_NAME=my_photo_gallery_test TEST_DB_USER=gallery_test TEST_DB_PASSWORD=test_password REQUIRE_TEST_DB=1 php tests/run.php
```

Будь-який skip або PHP warning/notice/deprecation тоді завершує runner з non-zero. GitHub Actions запускає PHP 8.2/8.4 × MySQL/MariaDB matrix, непорожню fixture gallery, backup → verify → restore з row/hash comparison та окремі Apache/Nginx security smoke checks. Це не скасовує ручну перевірку TLS, браузера та production filesystem.

## Документація

Production release ZIP навмисно не включає repo-only audit/AI/agent документи. Вони лишаються в робочому репозиторії для розробки й повторних аудитів.

- `docs/IMPLEMENTED_FEATURES.md` — що вже реалізовано і не треба повторно планувати в roadmap.
- `ROADMAP.md` — майбутні задачі, розбиті за пріоритетами.
- `docs/BUGS.md` — відомі обмеження і потенційні баги.
- `docs/BACKUP_RESTORE.md` — порядок backup і restore.
- Repo-only: `docs/AUDIT_REPORT.md`, `docs/SECURITY_AUDIT.md`, audit prompts/reports, `docs/UI_UX_RECOMMENDATIONS.md`, `AGENTS.md`, `GEMINI.md`, `CLAUDE.md`, `FULL_PROJECT_AUDIT.md`.

## Після встановлення

1. Запустіть `php tools/self_check.php`.
2. Створіть адміністратора через `php tools/setup.php`.
3. Увійдіть в адмінпанель.
4. Створіть альбом.
5. Завантажте JPEG-файл.
6. Перевірте галерею, пошук, фільтри, сортування і пагінацію.
7. Перевірте сторінку фото, EXIF, перехід до попередньої/наступної фотографії та лайтбокс.
8. Перевірте редагування, зміну альбому та видалення.
9. Запустіть `php tools/cleanup_orphans.php` після тестів, якщо вручну змінювали файли.
