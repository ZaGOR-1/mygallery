# My Photo Gallery

MVP персональної фотогалереї на PHP 8.2+, Apache і MySQL/MariaDB. Проєкт підтримує завантаження JPG/JPEG, створення прев’ю через GD, читання EXIF, просту адмінпанель, редагування та видалення фотографій.

## Можливості

- головна сторінка і адаптивна галерея;
- пагінація по 12 фотографій;
- сторінка окремої фотографії з EXIF-даними;
- перехід до попередньої та наступної фотографії;
- адміністративний вхід через PHP Session;
- CSRF-захист для POST-форм;
- завантаження тільки `image/jpeg`;
- максимальний розмір одного JPEG - 30 МБ;
- обмеження розмірів зображення до 8000x8000 або 50 МП;
- випадкові імена файлів на сервері;
- веб-версія фото до 2400 px завширшки;
- прев’ю до 600 px завширшки;
- автоматичне виправлення орієнтації JPEG;
- темний мінімалістичний дизайн без CSS-фреймворків.

## Структура

```text
app/includes/              спільні PHP-функції, авторизація, CSRF, шаблони
config/                    конфігурація застосунку і БД
database/schema.sql        структура бази
public/                    єдина публічна папка сайту
public/admin/              адміністративні сторінки
public/assets/             CSS і JavaScript
public/.htaccess           переносимі Apache-правила без php_value
public/uploads/originals   оригінальні JPEG
public/uploads/large       оптимізовані веб-версії
public/uploads/thumbnails  прев’ю
tools/setup.php            консольне створення першого адміністратора
tools/cleanup_orphans.php  перевірка зайвих файлів у uploads
```

Apache має дивитися саме в `public/`. Папки `app/`, `config/`, `database/`, `tools/` і `README.md` не повинні бути доступні напряму через браузер.

## Встановлення на WampServer у Windows

1. Встановіть WampServer з Apache, PHP 8.2+ і MySQL або MariaDB.
2. Увімкніть PHP-модулі: `pdo_mysql`, `gd`, `exif`, `fileinfo`.
3. Скопіюйте проєкт у папку, наприклад `C:\wamp64\domains\mygallery\`.
4. У WampServer створіть VirtualHost із `DocumentRoot`:

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

6. Перезапустіть DNS у WampServer або весь WampServer.
7. Створіть базу та таблиці:

```powershell
C:\wamp64\bin\mysql\mysql9.1.0\bin\mysql.exe -h 127.0.0.1 -P 3306 -u root --execute="source database/schema.sql"
```

8. Скопіюйте `config/database.example.php` у `config/database.php`.
9. Для стандартного WampServer можна залишити:

```php
'DB_HOST' => '127.0.0.1',
'DB_PORT' => 3306,
'DB_NAME' => 'my_photo_gallery',
'DB_USER' => 'root',
'DB_PASSWORD' => '',
```

10. У `config/config.php` перевірте:

```php
'APP_URL' => 'http://mygallery',
'APP_ENV' => 'local',
'APP_DEBUG' => true,
'UPLOAD_MAX_SIZE' => 30 * 1024 * 1024,
'MAX_IMAGE_WIDTH' => 8000,
'MAX_IMAGE_HEIGHT' => 8000,
'MAX_IMAGE_PIXELS' => 50 * 1000 * 1000,
'LARGE_MAX_WIDTH' => 2400,
```

Для великих JPEG також перевірте PHP-ліміти у `php.ini` або Apache/PHP-FPM конфігурації:

```ini
upload_max_filesize = 32M
post_max_size = 40M
memory_limit = 512M
```

11. Створіть першого адміністратора з консолі:

```powershell
C:\wamp64\bin\php\php8.3.14\php.exe tools\setup.php
```

12. Відкрийте `http://mygallery/admin/login.php` і увійдіть.

Схема створює таблиці `admins`, `photos` і `login_attempts`. Остання потрібна для серверного обмеження невдалих спроб входу.

## Встановлення на LAMP у VM Proxmox

1. Створіть VM з Debian або Ubuntu Server.
2. Встановіть Apache, PHP, MySQL/MariaDB і потрібні модулі:

```bash
sudo apt update
sudo apt install apache2 mysql-server php php-mysql php-gd php-exif php-fileinfo
```

3. Скопіюйте файли проєкту на сервер, наприклад у `/var/www/mygallery`.
4. Створіть БД і імпортуйте схему:

```bash
mysql -u root -p < database/schema.sql
```

5. Створіть окремого користувача БД:

```sql
CREATE USER 'gallery_user'@'localhost' IDENTIFIED BY 'strong_password';
GRANT ALL PRIVILEGES ON my_photo_gallery.* TO 'gallery_user'@'localhost';
FLUSH PRIVILEGES;
```

6. Скопіюйте `config/database.example.php` у `config/database.php` і впишіть користувача production.
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

9. Надайте Apache право запису тільки до upload-папок:

```bash
sudo chown -R root:www-data /var/www/mygallery
sudo chown -R www-data:www-data /var/www/mygallery/public/uploads/originals /var/www/mygallery/public/uploads/large /var/www/mygallery/public/uploads/thumbnails
sudo find /var/www/mygallery -type d -exec chmod 750 {} \;
sudo find /var/www/mygallery -type f -exec chmod 640 {} \;
sudo chmod 750 /var/www/mygallery/public/uploads/originals /var/www/mygallery/public/uploads/large /var/www/mygallery/public/uploads/thumbnails
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

Для роботи `public/uploads/.htaccess` у Apache має бути дозволено `AllowOverride All`.

`ServerTokens Prod` налаштовується в основній конфігурації Apache, а не в `.htaccess`. Локальний `.htaccess` прибирає `X-Powered-By`, але повністю приховати `Server` header можна тільки на рівні Apache config.

## Перенесення з Windows на Linux

1. Експортуйте базу через phpMyAdmin або `mysqldump`.
2. Скопіюйте файли проєкту.
3. Скопіюйте папки `public/uploads/originals`, `public/uploads/large` і `public/uploads/thumbnails`.
4. Імпортуйте дамп БД на Linux.
5. Оновіть `config/database.php` і `APP_URL`.
6. Налаштуйте Apache так, щоб `DocumentRoot` вказував на `public/`.
7. Виставте права для upload-папок.

## Резервне копіювання

Регулярно зберігайте:

- дамп бази `my_photo_gallery`;
- папки `public/uploads/originals`, `public/uploads/large` і `public/uploads/thumbnails`;
- файли `config/config.php` і `config/database.php` окремо від публічного репозиторію.

Приклад дампу:

```bash
mysqldump -u gallery_user -p my_photo_gallery > backup.sql
```

Щоб знайти JPEG-файли в `public/uploads`, яких уже немає в базі:

```bash
php tools/cleanup_orphans.php
```

Щоб видалити знайдені orphan-файли:

```bash
php tools/cleanup_orphans.php --delete
```

## Production-налаштування

- встановіть `APP_DEBUG` у `false`;
- використовуйте складний пароль адміністратора;
- використовуйте окремого користувача БД;
- налаштуйте HTTPS через Let’s Encrypt або інший сертифікат;
- переконайтеся, що Apache відкриває тільки `public/`;
- переконайтеся, що PHP-файли в `public/uploads` не виконуються.
- вимкніть зайві version headers:

```ini
expose_php = Off
```

```apache
ServerTokens Prod
ServerSignature Off
```

Застосунок також віддає базові security headers: `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy` і `Content-Security-Policy`.
Додатково налаштовано `Permissions-Policy` і прості cache headers для `public/assets` та JPEG у `public/uploads`.
Для cache headers Apache має мати увімкнені `mod_headers` і `mod_expires`, а для `.htaccess` має бути дозволено `AllowOverride FileInfo` або `AllowOverride All`.

## Після встановлення

1. Створіть адміністратора через `php tools/setup.php`.
2. Увійдіть в адмінпанель.
3. Завантажте JPEG-файл.
4. Перевірте галерею, сторінку фото, EXIF, редагування та видалення.
