Ти — senior PHP security auditor, backend reviewer і code quality engineer. Твоє завдання — повністю проаналізувати весь PHP-проєкт MyGallery на баги, помилки, security-проблеми, логічні проблеми, production-ризики, проблеми з документацією та потенційні місця, які можуть зламатися.

ВАЖЛИВО:

* Не виправляй код.
* Не видаляй файли.
* Не змінюй логіку проєкту.
* Єдина дозволена зміна — створити або оновити файл `FULL_PROJECT_AUDIT.md` у корені проєкту.
* Якщо `FULL_PROJECT_AUDIT.md` уже існує — повністю перезапиши його новим актуальним аудитом.
* Не показуй у звіті реальні паролі, токени, cookie, session ID, CSRF-токени або секрети. Якщо знайдеш секрет — вкажи тільки файл, рядок і тип проблеми, але не копіюй саме значення.

Проаналізуй повністю весь проєкт, включно з:

* PHP-кодом;
* SQL-схемою;
* SQL-міграціями;
* HTML/PHP templates;
* CSS;
* JavaScript;
* `.htaccess`;
* конфігурацією;
* `tools/` скриптами;
* upload-логікою;
* тегами;
* адмінкою;
* статистикою;
* backup/release tools;
* error pages;
* документацією `.md`;
* структурою папок;
* production/deployment налаштуваннями.

Особливо уважно перевір:

1. SECURITY

Перевір на:

* SQL injection;
* XSS: stored, reflected, DOM-based;
* CSRF;
* path traversal;
* file upload vulnerabilities;
* можливість завантажити або виконати PHP/web-shell;
* небезпечну роботу з `readfile`, `unlink`, `rename`, `copy`, `move_uploaded_file`;
* прямий доступ до приватних файлів;
* доступ до `storage`, `config`, `database`, `.env`, `.git`, backups;
* неправильний `DocumentRoot`;
* небезпечні `.htaccess` правила;
* session fixation;
* session hijacking;
* слабкі cookie/session налаштування;
* brute force login;
* username enumeration;
* timing attacks;
* відсутні security headers;
* HTTPS/HSTS проблеми;
* debug errors у production;
* витік EXIF/metadata;
* небезпечні права директорій;
* випадкове потрапляння приватних файлів у release ZIP.

2. AUTH / ADMIN

Перевір:

* чи всі admin-сторінки захищені `require_admin`;
* чи всі POST-дії мають CSRF;
* чи не можна обійти авторизацію;
* чи сесія перевіряє актуальність admin-користувача;
* чи logout працює правильно;
* чи адмін-сторінки не кешуються;
* чи немає IDOR/BOLA;
* чи download original доступний тільки адміну;
* чи stats/health/download недоступні гостям;
* чи login rate limiter працює коректно;
* чи немає race condition в login limiter.

3. FILE UPLOAD / IMAGE PROCESSING

Перевір:

* MIME type;
* extension;
* `getimagesize`;
* `is_uploaded_file`;
* обмеження розміру;
* JPEG validation;
* EXIF orientation 1–8;
* роботу з великими фото;
* memory limit;
* помилки GD;
* `imagecreatetruecolor`;
* `imagecopyresampled`;
* `imageflip`;
* `imagerotate`;
* створення thumbnail/large;
* збереження справжнього original;
* чи originals лежать приватно;
* видалення фото;
* trash/recovery logic;
* orphan files;
* regenerate images;
* legacy originals migration;
* race conditions при upload/delete/edit.

4. DATABASE

Перевір:

* відповідність PHP-коду SQL-схемі;
* `schema.sql`;
* усі міграції;
* таблиці `photos`, `albums`, `admins`, `login_attempts`, `tags`, `photo_tags`;
* foreign keys;
* indexes;
* unique constraints;
* nullable поля;
* транзакції;
* rollback;
* consistency між файлами і БД;
* portable migrations без жорсткого `USE database_name`;
* сумісність MySQL/MariaDB;
* charset/collation/UTF-8;
* FULLTEXT search;
* fallback search;
* проблеми з дефісами, короткими словами й українськими символами.

5. APPLICATION LOGIC

Перевір:

* маршрути;
* 404/500 обробку;
* `public/404.php`;
* `public/500.php`;
* redirects;
* edge cases;
* порожні значення;
* неправильні ID;
* неіснуючі фото;
* неіснуючі альбоми;
* неіснуючі теги;
* пошук;
* фільтри;
* пагінацію;
* prev/next на `photo.php`;
* edit/delete/upload;
* stats;
* health;
* backup;
* build release;
* regenerate images;
* cleanup orphans.

6. PRODUCTION READY

Перевір:

* `APP_ENV`;
* `APP_DEBUG`;
* HTTPS;
* HSTS;
* cookie secure/httponly/samesite;
* required PHP extensions;
* writable директорії;
* backup/restore;
* log rotation;
* self-check;
* health-check;
* Apache/Nginx notes;
* clean release ZIP;
* чи `tools/build_release.php` реально виключає приватні файли;
* чи release ZIP не містить `.git`, `config/database.php`, `.env`, фото, логи, сесії, backup, tmp, archive-файли.

7. DOCUMENTATION

Перевір:

* `README.md`;
* `AGENTS.md`;
* `CHANGELOG.md`;
* чи документація актуальна;
* чи немає згадок старих версій;
* чи roadmap не містить уже реалізовані задачі;
* чи README відповідає реальній структурі;
* чи інструкції запуску реально робочі.

Запусти доступні локальні перевірки:

* `php -l` для всіх PHP-файлів;
* `node --check public/assets/js/main.js`, якщо Node.js доступний;
* `php tools/self_check.php`, якщо середовище дозволяє;
* `php tools/build_release.php`;
* перевірку створеного ZIP через `unzip -t` або аналог;
* пошук небезпечних файлів у release ZIP;
* пошук секретів;
* пошук `.git`, `.env`, `database.php`, `*.log`, `sess_*`, `*.jpg`, `*.jpeg`, `*.zip`, `*.bak`, `*.tmp` у релізі;
* пошук небезпечних PHP-функцій: `eval`, `exec`, `shell_exec`, `system`, `passthru`, `unserialize`, `include`/`require` зі змінними, ручне складання SQL.

Формат файлу `FULL_PROJECT_AUDIT.md`:

# Full Project Audit

## 1. Executive Summary

Коротко напиши:

* яка це версія проєкту;
* чи проєкт можна запускати локально;
* чи готовий він до production;
* головні ризики;
* загальна оцінка стану.

## 2. Audit Scope

Вкажи:

* кількість PHP-файлів;
* кількість SQL-файлів;
* кількість JS-файлів;
* кількість Markdown-файлів;
* які папки перевірені;
* які команди запускалися;
* які команди не вдалося запустити і чому.

## 3. Severity Summary

Таблиця:

| Severity | Count |
| -------- | ----: |
| Critical |   ... |
| High     |   ... |
| Medium   |   ... |
| Low      |   ... |
| Info     |   ... |

## 4. Critical Issues

Для кожної проблеми:

* ID, наприклад `CRIT-001`;
* severity;
* status: `open`;
* файл і рядок;
* опис;
* чому це небезпечно;
* як проявиться;
* як відтворити;
* конкретне виправлення;
* приклад коду/SQL, якщо доречно;
* як перевірити після виправлення.

Якщо Critical немає — прямо напиши `Critical issues not found`.

## 5. High Issues

Той самий формат.

## 6. Medium Issues

Той самий формат.

## 7. Low Issues

Той самий формат.

## 8. Informational / Improvements

Список корисних покращень, які не є багами.

## 9. Security Review

Окремо дай підсумок по:

* SQLi;
* XSS;
* CSRF;
* auth;
* sessions;
* uploads;
* file access;
* secrets;
* production config;
* release ZIP safety.

## 10. Code Quality Review

Окремо:

* дублювання;
* складність;
* неймінг;
* структура;
* підтримуваність;
* місця, які краще винести у функції;
* місця, де код став занадто складним.

## 11. Database Review

Окремо:

* schema;
* migrations;
* indexes;
* constraints;
* transactions;
* consistency;
* tags/photo_tags;
* FULLTEXT.

## 12. Documentation Review

Окремо:

* що актуальне;
* що застаріле;
* що треба переписати;
* що треба додати;
* які `.md` файли зайві або дублюються.

## 13. Recommended Fix Order

Дай практичний порядок виправлень:

1. Security/production проблеми.
2. Баги, які можуть ламати дані.
3. UX/maintenance.
4. Nice-to-have.

Для кожного пункту вкажи:

* priority;
* complexity: S/M/L;
* affected files;
* verification steps.

## 14. Regression Test Checklist

Дай чекліст ручного тестування:

* login/logout;
* invalid login;
* rate limiter;
* upload photo;
* upload invalid file;
* upload large JPEG;
* EXIF orientation;
* albums;
* tags;
* search;
* filters;
* public gallery;
* photo page;
* prev/next;
* edit photo;
* delete photo;
* recover trash;
* cleanup orphans;
* regenerate images;
* download original;
* admin stats;
* admin health;
* backup;
* build release;
* 404 page;
* 500 page;
* unauthorized admin access;
* CSRF failure;
* direct access to private files.

## 15. Final Verdict

Чітко напиши:

* що вже добре;
* що обов’язково виправити;
* що можна відкласти;
* чи готовий проєкт для локального використання;
* чи готовий проєкт для production;
* чи можна цю версію вважати stable.

Вимоги до якості:

* Пиши українською мовою.
* Не пиши загальні фрази без прив’язки до файлів.
* Для кожної реальної проблеми вказуй файл і бажано рядок.
* Не вигадуй проблеми, якщо їх немає.
* Якщо щось не вдалося перевірити — прямо напиши.
* Якщо проблема залежить від конфігурації сервера — так і познач.
* Не дублюй одну проблему багато разів.
* Звіт має бути практичним, щоб по ньому можна було виправляти проєкт крок за кроком.

Після завершення:

1. Створи або онови `FULL_PROJECT_AUDIT.md` у корені проєкту.
2. У відповіді коротко напиши:

   * що аудит завершено;
   * скільки знайдено Critical/High/Medium/Low;
   * які 3–5 проблем найважливіші;
   * де лежить файл звіту.
