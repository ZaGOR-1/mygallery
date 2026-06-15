# Production Readiness Audit

## 1. Executive Summary

- **Версія проєкту**: 6.4.6.
- **Стан**: кодова база стабілізована після виправлення v6.4.5 issues. Робоча папка може містити `.git`, локальний `config/database.php`, фото, логи, сесії, backups і dist-архіви; для публікації потрібно використовувати тільки clean release ZIP із `tools/build_release.php`.
- **Локальний запуск**: можливий після налаштування `config/database.php`, застосування всіх міграцій і запуску `php tools/self_check.php`.
- **Production readiness**: near-ready / залежить від правильного деплою. Перед публікацією треба перевірити серверну конфігурацію, HTTPS, права на директорії, не-root DB user, сильний admin password і backup/restore на тестовому середовищі.
- **Головні ризики**: неправильний DocumentRoot, Nginx без правил замість `.htaccess`, ручний ZIP робочої папки замість release ZIP, слабкий admin password або root DB user у production.

## 2. Blocking Issues Before Production

Blocking issues у коді після стабілізації v6.4.6 не виявлено. Production-запуск залежить від правильного deployment checklist нижче.

## 3. Required Deployment Checklist

- [ ] Зібрати реліз: `php tools/build_release.php`.
- [ ] Перевірити ZIP: `unzip -t dist/mygallery_6.4.6_release.zip` або Windows-аналог.
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
- Albums, album covers, private albums, tags, bulk edit.
- Search, filters, pagination, photo prev/next.
- Download original тільки для адміна.
- Download album ZIP: перевірити cooldown/rate limit.
- Share links: create/open/revoke/expire.
- Backup, verify backup, restore на тестовому середовищі.
- Direct access до `storage/originals`, `config/database.php`, `storage/logs` має бути заблокований сервером.
- 404/500 pages.
- Clean release ZIP не містить `.git`, `.env`, `config/database.php`, logs, sessions, photos, backups або dist ZIP.

## 5. Final Verdict

**Ready for production: PARTIAL / після правильного деплою.**

Кодова база v6.4.6 достатньо зріла для малого production-сайту. Безпечність публікації залежить від clean release workflow, правильного DocumentRoot, HTTPS, production config, не-root DB user, сильного admin password і перевіреного backup/restore.
