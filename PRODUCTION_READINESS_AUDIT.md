# Production Readiness Audit

## 1. Executive Summary

- **Версія проєкту**: 6.1.0.
- **Стан**: робоча папка може містити `.git`, локальний `config/database.php`, фото, логи, сесії та backup; для публікації потрібно використовувати тільки clean release ZIP із `tools/build_release.php`.
- **Локальний запуск**: можливий після налаштування `config/database.php`, застосування міграцій і запуску `php tools/self_check.php`.
- **Production readiness**: partial/near-ready. Перед публікацією потрібно перевірити серверну конфігурацію, HTTPS, права на директорії, не-root DB user і clean release ZIP.
- **Головні ризики**: неправильний DocumentRoot, Nginx без правил замість `.htaccess`, ручний ZIP робочої папки замість release ZIP, слабкий admin password або root DB user у production.

## 2. Blocking Issues Before Production

Blocking issues у коді після стабілізації v6.1.0 не виявлено. Production залежить від правильного деплою.

## 3. Required Deployment Checklist

- [ ] Зібрати реліз: `php tools/build_release.php`.
- [ ] Перевірити ZIP: `unzip -t dist/mygallery_6.1.0_release.zip` або Windows-аналог.
- [ ] Завантажувати на сервер тільки clean ZIP із `dist/`, не ручний архів робочої папки.
- [ ] DocumentRoot/VHost має вказувати на `public/`.
- [ ] Створити `config/database.php` вручну на сервері з production-даними.
- [ ] `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL=https://...`.
- [ ] HTTPS увімкнений; HSTS тільки після перевірки HTTPS.
- [ ] DB user не `root` і має мінімальні потрібні права.
- [ ] Застосувати всі міграції з `database/migrations/`.
- [ ] Запустити `php tools/self_check.php`.
- [ ] Перевірити writable директорії: `storage/originals`, `storage/trash`, `storage/logs`, `storage/sessions`, `public/uploads/large`, `public/uploads/thumbnails`, `backups`.
- [ ] Налаштувати backup і log rotation.

## 4. Manual Tests Before Going Live

- Login/logout, invalid login, rate limiter.
- Upload valid JPEG, invalid file, duplicate JPEG, large JPEG.
- Albums, album covers, tags, bulk edit.
- Search, filters, pagination, photo prev/next.
- Download original тільки для адміна.
- Share links: create/open/revoke/expire.
- Backup, verify backup, restore на тестовому середовищі.
- Direct access до `storage/originals`, `config/database.php`, `storage/logs` має бути заблокований сервером.
- 404/500 pages.
- Clean release ZIP не містить `.git`, `.env`, `config/database.php`, logs, sessions, photos, backups або dist ZIP.

## 5. Final Verdict

**Ready for production: PARTIAL / після правильного деплою.**

Кодова база v6.1.0 вже достатньо зріла для малого production-сайту, але безпечність публікації залежить від clean release workflow, правильного DocumentRoot, HTTPS, production config, не-root DB user, сильного admin password і перевіреного backup/restore.
