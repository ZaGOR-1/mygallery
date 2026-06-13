# Audit Report Summary

Останнє оновлення: 2026-06-13

Детальний аудит: `FULL_PROJECT_AUDIT.md`.

## Статус High/Medium

- Critical: 0
- High: 1 знайдено, виправлено.
- Medium: 6 знайдено, виправлено.
- Low: 7 знайдено, виправлено або зведено до документованого майбутнього `session_version` після появи зміни пароля.

## Що виправлено

- Legacy originals перенесені з `public/uploads/originals` у `storage/originals`.
- Trash recovery більше не виконує restore/purge без validation manifest-записів.
- Albums admin action rollback більше не викликає повторний `db()` у `catch`.
- HSTS додається для production HTTPS.
- `self_check.php` розширено до реальної перевірки структури, модулів, конфігів і доступів.
- `cleanup_orphans.php` показує коректні public upload paths.
- README, AGENTS, IMPLEMENTED_FEATURES, BUGS, FIXES_APPLIED і roadmap синхронізовані.
- SQL schema/migrations стали portable без `USE`.
- Upload cleanup логуватиме невдалі `unlink`.
- Admin-сесія перевіряє існування admin-запису в БД.
- Trash manifest більше не записує абсолютні шляхи.
- `tools/setup.php` більше не залежить від shell-викликів.
- Додано responsive `srcset`/`sizes`.
- Додано FULLTEXT-пошук із fallback на `LIKE`.

## Залишкові ризики

Див. `BUGS.md` і `POST_MVP_ROADMAP.md`.
