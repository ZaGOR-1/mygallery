Ти — senior PHP code auditor, QA engineer і backend reviewer. Проаналізуй повністю мій PHP-проєкт MyGallery на баги, помилки, потенційні баги, логічні проблеми, security-ризики, проблеми з базою даних, міграціями, документацією і production-ready станом.

ВАЖЛИВО:

* Не виправляй код автоматично.
* Не видаляй файли.
* Не змінюй структуру проєкту.
* Єдина дозволена зміна — створити або оновити файл `FULL_PROJECT_AUDIT.md` у корені проєкту.
* Якщо файл уже існує — повністю перезапиши його новим актуальним аудитом.
* Не копіюй у звіт реальні паролі, токени, session ID, CSRF-токени або приватні ключі. Якщо знайдеш секрет — вкажи тільки файл, рядок і тип проблеми.

Проєкт: MyGallery — PHP-фотогалерея на чистому PHP. У проєкті є публічна галерея, адмінка, завантаження JPEG, EXIF, альбоми, теги, share links, приватні оригінали, backup/restore tools, release builder, health check, self check, error pages і документація.

Перевір повністю:

1. PHP-код:

* синтаксис;
* логічні баги;
* дублювання коду;
* неправильні `return`, `redirect`, `try/catch`;
* некоректну обробку помилок;
* місця, де код може впасти на edge cases;
* змішування HTML/PHP/SQL;
* якість архітектури.

2. Security:

* SQL injection;
* XSS;
* CSRF;
* auth bypass;
* IDOR/BOLA;
* path traversal;
* session fixation/hijacking;
* upload PHP-shell;
* прямий доступ до приватних файлів;
* безпеку `admin/download.php`;
* безпеку `share.php`;
* brute force login;
* username enumeration;
* leakage secrets;
* production debug leaks.

3. Upload/images:

* JPEG validation;
* MIME type;
* `getimagesize`;
* `is_uploaded_file`;
* random file names;
* EXIF orientation;
* memory limit;
* GD errors;
* large/thumbnail generation;
* private originals;
* duplicate detection;
* delete/recover/regenerate;
* orphan files.

4. Database:

* `schema.sql`;
* усі migrations;
* чи міграції idempotent;
* чи schema відповідає PHP-коду;
* foreign keys;
* indexes;
* constraints;
* `photos`, `albums`, `tags`, `photo_tags`, `share_links`, `admins`, `login_attempts`;
* `original_sha256`;
* `session_version`;
* FULLTEXT search;
* MySQL/MariaDB compatibility;
* UTF-8 / українські символи.

5. Admin:

* login/logout;
* CSRF на POST-діях;
* upload/edit/delete;
* bulk actions;
* albums;
* tags;
* stats;
* health;
* download original;
* share link management;
* чи всі admin-сторінки захищені авторизацією.

6. Public pages:

* `index.php`;
* `gallery.php`;
* `photo.php`;
* `share.php`;
* `404.php`;
* `500.php`;
* пошук;
* фільтри;
* pagination;
* prev/next navigation;
* неіснуючі ID;
* expired/revoked share links.

7. Tools:

* `tools/setup.php`;
* `tools/self_check.php`;
* `tools/build_release.php`;
* `tools/backup.php`;
* `tools/verify_backup.php`;
* `tools/restore.php`;
* `tools/cleanup_orphans.php`;
* `tools/cleanup_runtime.php`;
* `tools/regenerate_images.php`;
* `tools/recover_trash.php`;
* `tools/backfill_sha256.php`;
* чи вони безпечні;
* чи не видаляють зайве;
* чи не кладуть backup у public;
* чи build_release збирає чистий ZIP.

8. Release ZIP:
   Запусти `php tools/build_release.php` і перевір, що в release ZIP немає:

* `.git`;
* `.env`;
* `config/database.php`;
* `*.log`;
* `sess_*`;
* приватних фото;
* backup ZIP;
* `dist`;
* `storage/originals/*.jpg`;
* `public/uploads/**/*.jpg`;
* `*.bak`;
* `*.tmp`;
* тимчасових файлів типу `temp_*.php`.

9. Documentation:
   Перевір:

* `README.md`;
* `CHANGELOG.md`;
* `docs/BUGS.md`;
* `ROADMAP.md`;
* `docs/IMPLEMENTED_FEATURES.md`;
* `docs/BACKUP_RESTORE.md`;
* `docs/AUDIT_REPORT.md`;
* `docs/SECURITY_AUDIT.md`;
* `PRODUCTION_READINESS_AUDIT.md`;
* `AGENTS.md`;
* чи немає застарілих версій;
* чи README відповідає реальній структурі;
* чи roadmap не містить уже реалізовані задачі;
* чи документація не вводить в оману.

Запусти доступні перевірки:

* `php -l` для всіх PHP-файлів;
* `node --check public/assets/js/main.js`, якщо Node.js доступний;
* `php tests/run.php`, якщо можливо;
* `php tools/self_check.php`, якщо можливо;
* `php tools/build_release.php`;
* перевір release ZIP через `unzip -t` або Windows-аналог.

Якщо якась команда не запускається через відсутність PHP extensions, MySQL, Node.js або іншого середовища — чесно напиши це у звіті.

Файл `FULL_PROJECT_AUDIT.md` оформи так:

# Full Project Audit

## 1. Executive Summary

Коротко:

* яка версія проєкту;
* чи можна запускати локально;
* чи готовий до production;
* головні ризики;
* загальна оцінка стану.

## 2. Audit Scope

Вкажи:

* кількість PHP/SQL/JS/Markdown файлів;
* які папки перевірені;
* які команди запускались;
* що не вдалося перевірити.

## 3. Severity Summary

| Severity | Count |
| -------- | ----: |
| Critical |   ... |
| High     |   ... |
| Medium   |   ... |
| Low      |   ... |
| Info     |   ... |

## 4. Critical Issues

Якщо немає — напиши `Critical issues not found`.

Для кожної проблеми:

* ID;
* severity;
* status: open;
* файл і рядок;
* опис;
* impact;
* як проявиться;
* як відтворити;
* конкретне виправлення;
* як перевірити після виправлення.

## 5. High Issues

Той самий формат.

## 6. Medium Issues

Той самий формат.

## 7. Low Issues

Той самий формат.

## 8. Informational / Improvements

Корисні покращення, які не є багами.

## 9. Security Review

Окремо оцінити:

* SQLi;
* XSS;
* CSRF;
* auth;
* sessions;
* uploads;
* file access;
* share links;
* backup/restore;
* release ZIP;
* production config.

## 10. Architecture Review

Окремо оцінити:

* структуру;
* підтримуваність;
* складність;
* дублювання;
* чи проєкт не став занадто складним.

## 11. Database Review

Окремо оцінити:

* schema;
* migrations;
* indexes;
* constraints;
* idempotency;
* tags;
* share links;
* original_sha256;
* session_version.

## 12. Tools Review

Оцінити всі CLI tools і вказати, які можуть бути небезпечними або нестабільними.

## 13. Documentation Review

Оцінити всі `.md` файли:

* що актуальне;
* що застаріле;
* що треба переписати;
* що треба додати;
* що краще видалити.

## 14. Recommended Fix Order

Дай порядок виправлень:

1. Critical.
2. High.
3. Medium.
4. Low.
5. Info/nice-to-have.

Для кожного пункту:

* priority;
* complexity: S/M/L;
* affected files;
* verification steps.

## 15. Regression Test Checklist

Дай чекліст ручного тестування:

* login/logout;
* invalid login;
* upload JPEG;
* upload invalid file;
* duplicate upload;
* albums;
* tags;
* search/filter;
* photo page;
* prev/next;
* edit/delete;
* bulk edit;
* share links;
* expired/revoked share links;
* download original;
* stats;
* health;
* backup/verify/restore;
* build release;
* 404/500;
* CSRF failure;
* unauthorized admin access;
* direct private file access.

## 16. Final Verdict

Чітко напиши:

* що добре;
* що обов’язково виправити;
* що бажано виправити;
* що можна відкласти;
* чи готовий проєкт локально;
* чи готовий до production;
* чи можна вважати версію stable.

Вимоги:

* Пиши українською.
* Не вигадуй проблеми.
* Не називай проєкт “ідеальним” або “100% безпечним”.
* Для кожної проблеми вказуй конкретний файл і бажано рядок.
* Якщо щось не вдалося перевірити — прямо напиши.
* Якщо проблема залежить від Apache/Nginx/WampServer — прямо так і напиши.
* Не дублюй одну проблему багато разів.
* Звіт має бути практичним, щоб по ньому можна було виправляти проєкт крок за кроком.

Після завершення:

1. Створи або онови `FULL_PROJECT_AUDIT.md`.
2. У відповіді коротко напиши:

   * аудит завершено;
   * скільки знайдено Critical/High/Medium/Low/Info;
   * які 3–5 найважливіших висновків;
   * де лежить файл аудиту.
