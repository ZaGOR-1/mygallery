# My Photo Gallery

MVP персональної фотогалереї на PHP 8.2+, Apache і MySQL/MariaDB. Проєкт зроблений як простий студентський PHP-проєкт без Laravel, React, Bootstrap, Composer і зайвої архітектури.

Поточна версія V6 підтримує JPEG-upload, EXIF, приватні оригінали, прев’ю, large-версії фото, альбоми, теги, пошук, фільтри, сортування, лайтбокс із zoom/pan, адмінпанель, статистику, admin health-check, завантаження оригіналу тільки для адміна, CSRF-захист, login rate limiter і службові CLI-інструменти для перевірки, backup, release-збірки та обслуговування.

## Поточний статус

Проєкт підходить для локального використання, навчання і невеликої персональної галереї. Для production обов’язково треба виконати production-налаштування з цього README: HTTPS, `APP_ENV=production`, `APP_DEBUG=false`, окремий користувач БД, правильний `DocumentRoot` на `public/`, права на папки та перевірка `tools/self_check.php`.

## Можливості

- головна сторінка й адаптивна галерея;
- пагінація по 12 фотографій;
- пошук за назвою, описом і назвою файла;
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
- responsive images через `srcset` і `sizes`;
- автоматичне виправлення EXIF Orientation;
- базові security headers;
- службові CLI-інструменти для setup, self-check, cleanup, міграції legacy originals, recovery trash, backup, regenerate images і чистої release-збірки.

## Структура проєкту

```text
app/includes/                         спільні PHP-функції, авторизація, CSRF, шаблони
config/                               конфігурація застосунку і БД
database/schema.sql                   повна структура бази для чистої установки
database/migrations/                  SQL-міграції для вже встановленої бази
public/                               єдина публічна папка сайту
public/admin/                         адміністративні сторінки
public/admin/health.php               web health-check тільки для адміністратора
public/admin/stats.php                статистика контенту і media-сховища
public/admin/download.php             download приватного оригіналу тільки для адміністратора
public/assets/                        CSS і JavaScript
public/.htaccess                      переносимі Apache-правила без php_value і ErrorDocument
public/404.php                         сторінка помилки 404
public/500.php                         сторінка помилки 500
public/uploads/large/                 оптимізовані веб-версії JPEG
public/uploads/thumbnails/            прев’ю JPEG
public/uploads/originals/             legacy-only папка, нові оригінали тут не зберігати
storage/originals/                    приватні byte-for-byte оригінальні JPEG
storage/trash/                        тимчасовий кошик під час видалення
storage/logs/                         приватні логи застосунку
storage/sessions/                     приватні PHP session-файли
tools/setup.php                       консольне створення першого адміністратора
tools/self_check.php                  швидка перевірка структури, модулів і доступів
tools/build_release.php               збірка чистого release ZIP без приватних файлів
tools/backup.php                      приватний backup БД і media-файлів
tools/regenerate_images.php           регенерація large/thumbnail із storage/originals
tools/cleanup_orphans.php             пошук зайвих файлів і відсутніх media-файлів
tools/migrate_legacy_originals.php    перенесення старих public originals у storage/originals
tools/recover_trash.php               відновлення або очищення trash manifest-файлів
VERSION                               поточна версія проєкту
tools/lib/SimpleZipWriter.php         pure-PHP ZIP writer для release/backup
README.md                             основна інструкція запуску
AGENTS.md                             правила для AI/Codex-агента
docs/IMPLEMENTED_FEATURES.md          що вже реалізовано
CHANGELOG.md                          історія змін версій
docs/BUGS.md                          відомі обмеження і потенційні баги
ROADMAP.md                            майбутні задачі, не список уже реалізованого
docs/AUDIT_REPORT.md                  короткий підсумок останнього аудиту
docs/SECURITY_AUDIT.md                детальний технічний аудит
docs/AUDIT_PROMPT.md                  промпт для повторного аудиту AI-агентом
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

- `gallery.php` підтримує GET-фільтри: пошук, альбом, тег, камера, дата зйомки і сортування.
- Альбоми створюються в `admin/albums.php` або прямо під час завантаження/редагування фото.
- Теги вводяться через кому під час завантаження або редагування фото. Один тег можна використовувати для багатьох фото.
- Картки в галереї відкривають сторінку окремої фотографії, щоб EXIF і навігація залишалися доступними без JavaScript.
- На сторінці фото клік по зображенню відкриває лайтбокс.
- В адмінпанелі список фотографій підтримує пошук, фільтри за альбомом/камерою/датою та сортування.

## Встановлення на WampServer у Windows

1. Встановіть WampServer з Apache, PHP 8.2+ і MySQL або MariaDB.
2. Увімкніть PHP-модулі: `pdo_mysql`, `gd`, `exif`, `fileinfo`, `mbstring`.
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
```

SQL-файли не містять `USE`, тому застосовуються до бази, яку ви явно передали в команді `mysql ... database_name < file.sql`.

## Встановлення на LAMP у VM Proxmox

1. Створіть VM з Debian або Ubuntu Server.
2. Встановіть Apache, MySQL/MariaDB, PHP і потрібні модулі:

```bash
sudo apt update
sudo apt install apache2 mysql-server php php-mysql php-gd php-exif php-mbstring unzip
```

`fileinfo` зазвичай входить у стандартний PHP-пакет. Перевірте його через `php -m | grep fileinfo`.

3. Скопіюйте файли проєкту на сервер, наприклад у `/var/www/mygallery`.
4. Створіть БД і імпортуйте схему:

```bash
mysql -u root -p -e "CREATE DATABASE IF NOT EXISTS my_photo_gallery CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p my_photo_gallery < database/schema.sql
```

5. Створіть окремого користувача БД:

```sql
CREATE USER 'gallery_user'@'localhost' IDENTIFIED BY 'strong_password';
GRANT ALL PRIVILEGES ON my_photo_gallery.* TO 'gallery_user'@'localhost';
FLUSH PRIVILEGES;
```

6. Скопіюйте `config/database.example.php` у `config/database.php` і впишіть production-користувача.
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
sudo chown -R www-data:www-data /var/www/mygallery/storage/originals /var/www/mygallery/storage/trash /var/www/mygallery/storage/logs /var/www/mygallery/storage/sessions /var/www/mygallery/public/uploads/large /var/www/mygallery/public/uploads/thumbnails
sudo find /var/www/mygallery -type d -exec chmod 750 {} \;
sudo find /var/www/mygallery -type f -exec chmod 640 {} \;
sudo chmod 750 /var/www/mygallery/storage/originals /var/www/mygallery/storage/trash /var/www/mygallery/storage/logs /var/www/mygallery/storage/sessions /var/www/mygallery/public/uploads/large /var/www/mygallery/public/uploads/thumbnails
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

Якщо ви використовуєте Nginx, зверніть увагу, що файли `.htaccess` будуть проігноровані. Вам необхідно явно закрити доступ до `.php` файлів у директорії `uploads`, а також заборонити доступ до приватних тек.

Приклад production-конфігурації для Nginx:

```nginx
server {
    listen 80;
    server_name gallery.example.com;
    root /var/www/mygallery/public;
    index index.php index.html;

    # Блокуємо виконання PHP у папці uploads
    location ^~ /uploads/ {
        location ~ \.php$ {
            deny all;
        }
    }

    # Всі інші запити направляємо на index.php
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Забороняємо доступ до прихованих файлів (напр. .git, .env)
    location ~ /\. {
        deny all;
    }
}
```

## Production-налаштування

- `APP_ENV=production`;
- `APP_DEBUG=false`;
- `APP_URL` має починатися з `https://`;
- окремий користувач БД, не `root` без пароля;
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

Пошук зайвих JPEG-файлів, legacy public originals і DB-записів із відсутніми файлами:

```bash
php tools/cleanup_orphans.php
php tools/cleanup_orphans.php --delete
```

Перенесення старих оригіналів із `public/uploads/originals`:

```bash
php tools/migrate_legacy_originals.php
php tools/migrate_legacy_originals.php --apply
```

Перевірка trash manifest-файлів після аварійного видалення:

```bash
php tools/recover_trash.php
php tools/recover_trash.php --apply
php tools/recover_trash.php --apply --purge-deleted
```

Очищення старих службових файлів (логи, сесії, кошик) старше 7-30 днів:

```bash
php tools/cleanup_runtime.php
php tools/cleanup_runtime.php --apply
```

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

Збірка чистого release ZIP:

```bash
php tools/build_release.php
```

На виході буде `dist/mygallery_<VERSION>_release.zip`, наприклад `dist/mygallery_6.1.0_release.zip`. Скрипт автоматично блокує ZIP, якщо у нього потрапляє `.git/`, `config/database.php`, `.env`, session/log/tmp/backup-файли або реальні фото з upload/storage.

Приватний backup:

```bash
php tools/backup.php
php tools/backup.php --include-config
```

Без `--include-config` файл `config/database.php` не потрапляє в backup ZIP. `tools/backup.php` відмовляється створювати backup усередині `public/`, щоб приватний ZIP випадково не став доступним через браузер. Див. також `docs/BACKUP_RESTORE.md`.

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
```

Для ручного SQL-дампу також можна використати:

```bash
mysqldump -u gallery_user -p my_photo_gallery > backup.sql
```

Backup не можна зберігати всередині `public/` і не можна комітити в Git. `tools/backup.php` блокує `--output` у `public/`, але приватні backup-файли все одно треба зберігати поза `DocumentRoot`. Детальний порядок restore описаний у `docs/BACKUP_RESTORE.md`.

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
```

У production застосунок спеціально не стартує, якщо `APP_DEBUG=true`, `APP_URL` не `https://`, або БД налаштована на `root` без пароля.

## Документація

- `docs/IMPLEMENTED_FEATURES.md` — що вже реалізовано і не треба повторно планувати в roadmap.
- `ROADMAP.md` — майбутні задачі, розбиті за пріоритетами.
- `docs/BUGS.md` — відомі обмеження і потенційні баги.
- `docs/AUDIT_REPORT.md` — короткий підсумок останнього аудиту.
- `docs/SECURITY_AUDIT.md` — детальний технічний аудит.
- `docs/AUDIT_PROMPT.md` — готовий промпт для повторного аудиту AI-агентом.
- `docs/BACKUP_RESTORE.md` — порядок backup і restore.
- `AGENTS.md` — правила для AI/Codex-агента, який буде змінювати проєкт.

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
