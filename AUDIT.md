Ти — senior PHP security/code auditor. Потрібно повністю проаналізувати мій PHP-проєкт фотогалереї на потенційні баги, помилки, security-ризики, логічні проблеми, production-проблеми, застарілу документацію та все, що може зламатися або бути небезпечним.

ВАЖЛИВО:

* Не виправляй код автоматично.
* Не видаляй файли.
* Не змінюй структуру проєкту.
* Єдина дозволена зміна — створити окремий файл аудиту в корені проєкту.
* Назва файлу: `FULL_PROJECT_AUDIT.md`.
* Якщо файл уже існує — перезапиши його актуальним повним аудитом.
* У звіті не показуй реальні паролі, токени, секрети або приватні ключі. Якщо знайдеш секрет — вкажи тільки файл, рядок і тип проблеми, але не копіюй саме значення.

Проаналізуй увесь проєкт повністю, включно з:

* PHP-кодом;
* SQL-міграціями та схемою бази даних;
* HTML-шаблонами;
* CSS;
* JavaScript;
* `.htaccess`;
* файлами конфігурації;
* tools/scripts;
* storage/public uploads логікою;
* README, AGENTS, BUGS, ROADMAP, AUDIT_REPORT, IMPLEMENTED_FEATURES та іншими `.md` файлами;
* структурою папок;
* production/deployment налаштуваннями.

Особливо уважно перевір:

1. Безпеку:

* SQL injection;
* XSS: stored, reflected, DOM-based;
* CSRF;
* path traversal;
* file upload vulnerabilities;
* можливість завантажити або виконати PHP/web-shell;
* прямий доступ до приватних файлів;
* доступ до `storage`, `config`, `database`, `.env`, `.git`, backups;
* небезпечні `.htaccess` правила;
* неправильний DocumentRoot;
* session hijacking / session fixation;
* слабкі cookie/session налаштування;
* brute force захист login;
* username enumeration;
* timing attacks;
* відсутність security headers;
* проблеми з HTTPS/HSTS;
* витік debug-помилок у production;
* витік EXIF/metadata;
* небезпечні права файлів і директорій.

2. Авторизацію та адмінку:

* чи всі admin-дії реально захищені авторизацією;
* чи всі POST-дії мають CSRF;
* чи не можна обійти admin middleware;
* чи немає старих сесій, які залишаються валідними після зміни пароля;
* чи правильно працює logout;
* чи немає кешування приватних/admin-сторінок;
* чи немає IDOR/BOLA проблем.

3. Завантаження та обробку фото:

* MIME type перевірки;
* розширення файлів;
* `getimagesize`;
* `is_uploaded_file`;
* обмеження розміру;
* перевірку JPEG;
* EXIF orientation, включно з 1–8;
* роботу з великими фото;
* memory limit;
* помилки GD;
* створення preview/thumbnail;
* збереження справжнього оригіналу;
* чи оригінали не лежать публічно;
* видалення фото;
* orphan files;
* recovery/trash логіку;
* міграцію legacy originals;
* race conditions при upload/delete/edit.

4. Базу даних:

* відповідність PHP-коду SQL-схемі;
* міграції;
* foreign keys;
* індекси;
* constraints;
* nullable поля;
* транзакції;
* атомарність операцій;
* rollback при помилках;
* неконсистентність між файлами і БД;
* жорстко прописані назви БД типу `USE database_name`;
* сумісність із MySQL/MariaDB;
* проблеми з charset/collation/UTF-8.

5. Логіку застосунку:

* 404/500 обробку;
* неправильну маршрутизацію;
* edge cases;
* race conditions;
* дублювання коду;
* приховані логічні баги;
* неправильні redirects;
* неправильну роботу з порожніми значеннями;
* некоректні альбоми/фільтри/пошук;
* роботу з українськими символами;
* залежність від `mbstring`;
* поведінку при відсутніх PHP extensions.

6. Production-ready:

* `APP_DEBUG`;
* `APP_ENV`;
* HTTPS;
* HSTS;
* cookie secure/httponly/samesite;
* вимоги до PHP extensions;
* права на папки;
* backup/restore;
* cleanup scripts;
* cron-задачі;
* логування;
* ротацію логів;
* self-check/health-check;
* інструкції для Apache/Nginx;
* чи не потрапляють приватні файли в релізний ZIP.

7. Документацію:

* чи README актуальний;
* чи AGENTS.md відповідає реальному стану коду;
* чи BUGS.md містить актуальні known issues;
* чи POST_MVP_ROADMAP.md містить саме майбутні задачі, а не вже реалізовані;
* чи IMPLEMENTED_FEATURES.md відповідає реальному функціоналу;
* чи AUDIT_REPORT.md не вводить в оману;
* чи немає суперечностей між `.md` файлами;
* чи інструкції запуску реально робочі.

Виконай доступні локальні перевірки, якщо середовище дозволяє:

* `php -l` для всіх PHP-файлів;
* перевірку JavaScript через `node --check`, якщо доступний Node.js;
* перевірку SQL-файлів на очевидні помилки;
* пошук небезпечних функцій PHP: `eval`, `exec`, `shell_exec`, `system`, `passthru`, `unserialize`, `include`/`require` зі змінними, небезпечний `move_uploaded_file`, ручне складання SQL;
* пошук секретів, токенів, паролів, debug-файлів;
* пошук тимчасових, backup, log, session, uploaded файлів;
* перевірку, чи немає `.git`, `.env`, `database.php`, `.sql`, `.zip`, `.bak`, `.log`, `sess_*` у публічних або релізних папках.

Формат файлу `FULL_PROJECT_AUDIT.md` зроби таким:

# Full Project Audit

## 1. Executive Summary

Короткий загальний висновок:

* чи проєкт можна запускати локально;
* чи можна запускати в production;
* головні ризики;
* загальна оцінка стану.

## 2. Audit Scope

Опиши, що саме перевірено:

* кількість PHP/SQL/JS/MD файлів;
* які папки перевірені;
* які автоматичні команди запускалися;
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
* як відтворити або за яких умов проявиться;
* конкретне виправлення;
* приклад коду або SQL, якщо доречно;
* як перевірити після виправлення.

## 5. High Issues

Той самий формат.

## 6. Medium Issues

Той самий формат.

## 7. Low Issues

Той самий формат.

## 8. Informational / Improvements

Список не критичних, але корисних покращень.

## 9. Security Review

Окремий підсумок по:

* SQLi;
* XSS;
* CSRF;
* auth;
* sessions;
* uploads;
* file access;
* secrets;
* production config.

## 10. Code Quality Review

Окремо:

* дублювання;
* складність;
* неймінг;
* структура;
* підтримуваність;
* місця, які краще винести у функції.

## 11. Database Review

Окремо:

* schema;
* migrations;
* indexes;
* constraints;
* transactions;
* consistency.

## 12. Documentation Review

Окремо:

* що актуальне;
* що застаріле;
* що треба переписати;
* що треба додати.

## 13. Recommended Fix Order

Дай практичний порядок виправлень:

1. Спочатку критичні security/production проблеми.
2. Потім баги, які можуть ламати дані.
3. Потім UX/maintenance.
4. Потім nice-to-have.

Для кожного пункту вкажи:

* пріоритет;
* приблизну складність: S/M/L;
* які файли змінювати;
* як перевірити.

## 14. Regression Test Checklist

Дай чекліст ручного тестування після виправлень:

* login/logout;
* upload photo;
* EXIF orientation;
* albums;
* search/filter;
* edit photo;
* delete photo;
* restore/recover trash;
* cleanup orphans;
* migrate legacy originals;
* production config;
* invalid files upload;
* large image upload;
* unauthorized access;
* CSRF failure;
* direct file access.

## 15. Final Verdict

Чітко напиши:

* що вже добре;
* що обов’язково виправити;
* що можна відкласти;
* чи готовий проєкт для локального навчального використання;
* чи готовий для production.

Вимоги до якості аудиту:

* Не пиши загальні фрази без прив’язки до файлів.
* Для кожної реальної проблеми вказуй конкретний файл і бажано рядок.
* Не вигадуй проблеми, якщо їх немає.
* Якщо щось не вдалося перевірити — прямо напиши це.
* Якщо проблема лише потенційна і залежить від конфігурації сервера — так і познач.
* Не дублюй одну й ту саму проблему багато разів.
* Пиши українською мовою.
* Зроби звіт практичним, щоб я міг потім відкривати `FULL_PROJECT_AUDIT.md` і по ньому виправляти проєкт крок за кроком.

Після завершення:

1. Створи або онови файл `FULL_PROJECT_AUDIT.md` у корені проєкту.
2. У відповіді коротко напиши:

   * що аудит завершено;
   * скільки знайдено Critical/High/Medium/Low;
   * які 3–5 проблем найважливіші;
   * де лежить файл звіту.
