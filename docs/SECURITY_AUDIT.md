# Security Audit

Актуально для MyGallery v6.4.6 після виправлення medium issues з `FULL_PROJECT_AUDIT.md`.

## 1. Executive Summary

Проєкт має зрілий базовий рівень безпеки для MVP персональної фотогалереї: авторизація адміністратора, CSRF, PDO prepared statements, приватне зберігання оригіналів, JPEG-only upload, release exclusions і CLI-only maintenance tools уже реалізовані.

Критичних або high security-проблем на поточному етапі не зафіксовано. Medium issues з останнього повного аудиту закриті: тимчасовий migration/debug script видалено, share links отримали строк дії, CSP-несумісні inline styles/scripts прибрано з вказаних сторінок, документацію синхронізовано.

## 2. Severity Summary

| Severity | Count |
|---|---:|
| Critical | 0 |
| High | 0 |
| Medium | 0 |
| Low | 6 |
| Info | 7 |

## 3. Security Checklist Results

| Area | Status | Notes |
|---|---|---|
| SQL Injection | Pass | Критичні запити використовують PDO prepared statements і whitelist-підходи для сортування/фільтрів. |
| XSS | Pass with maintenance notes | Вивід екранується через `h()`. Inline UI у medium-scope файлах прибрано для кращої CSP-сумісності. |
| CSRF | Pass | POST-дії в адмінці захищені CSRF-токеном. |
| Auth | Pass | Є session regeneration після login, rate limiter і session version invalidation. |
| Uploads | Pass | Дозволено JPEG, MIME перевіряється через `finfo`, додатково використовується `getimagesize()`, імена файлів випадкові. |
| File Access | Pass | Оригінали зберігаються в `storage/originals`, public uploads не виконують PHP. |
| Share Links | Improved | Нові photo/album share links мають строк дії з дефолтом 30 днів; безстроковий режим лишився явним вибором. |
| Release ZIP | Pass | Release builder виключає `.git`, `config/database.php`, logs, sessions, backup/tmp files і реальні фото. |
| Production Config | Needs operator checklist | Для production потрібні HTTPS, `APP_ENV=production`, `APP_DEBUG=false`, окремий DB user і правильний `DocumentRoot public/`. |

## 4. Fixed Medium Security/Hardening Items

- `temp_migrate.php` видалено з кореня репозиторію.
- `public/admin/share.php` записує `expires_at` для нових share links.
- Форми photo/album share links мають вибір строку дії: 1 день, 7 днів, 30 днів, 90 днів або без строку.
- `public/admin/edit.php`, `public/admin/bulk_edit.php`, `public/gallery.php` і `public/share.php` більше не використовують inline `style`/`onclick`.
- README, AGENTS, CHANGELOG і audit docs приведено до актуального стану.

## 5. Remaining Low-Risk Work

Залишкові low issues описані у `FULL_PROJECT_AUDIT.md`. Вони не блокують MVP, але корисні перед production-polish:

- дружні error layouts для `public/share.php`;
- кращий UX для CSRF failure у bulk edit;
- додаткова existence validation у `public/admin/share.php`;
- посилена path validation у `tools/restore.php`;
- cleanup для `storage/test_sessions`;
- подальше підтримання audit docs без абсолютних локальних посилань.

## 6. Production Checklist

Перед production:

- `APP_ENV=production`;
- `APP_DEBUG=false`;
- `APP_URL=https://...`;
- HTTPS увімкнено;
- Apache/Nginx `DocumentRoot` вказує тільки на `public/`;
- `storage/`, `config/`, `tools/` і `database/` не доступні напряму через браузер;
- БД працює під окремим користувачем, не `root`;
- release ZIP створено через `php tools/build_release.php`;
- backup ZIP не зберігається в `public/`;
- запущено `php tools/self_check.php`.

## 7. Final Verdict

Security-стан нормальний для MVP і локального використання. Для production потрібно виконати checklist вище та поступово закрити low issues з `FULL_PROJECT_AUDIT.md`.
