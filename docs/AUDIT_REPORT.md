# Audit Report

Актуально для MyGallery 6.4.23. Повний аудит робочої копії від 2026-07-10 збережений у кореневому `FULL_PROJECT_AUDIT.md`; архівний аудит 2026-06-16 — у `docs/AUDIT_FINDINGS_2026-06-16.md`.

## Статус findings 2026-07-10

| Severity | Знайдено | Поточний статус |
|---|---:|---|
| Critical | 0 | Не знайдено |
| High | 3 | H-01–H-03 виправлено у v6.4.21 |
| Medium | 8 | M-01–M-08 виправлено у v6.4.22 |
| Low | 6 | L-01–L-06 виправлено у v6.4.22 |
| Informational | 5 | I-01–I-05 закриті/підтверджені у v6.4.23 |

## Що закрито у v6.4.23

- I-01: production release allowlist і повний ZIP stream readback лишаються regression-захищеними;
- I-02: web-security baseline підтверджено unit/static тестами та CI HTTP checks для CSP/`nosniff`;
- I-03: maintenance, share, protected-media та album-ZIP helpers винесено у focused includes без framework/DI;
- I-04: exact-one-target CHECK для `share_links` додано до schema/idempotent migration, self-check і admin health;
- I-05: CI має PHP 8.2/8.4 matrix, required DB suites, backup → verify → restore → self-check та HTTP smoke.

## Що закрито у v6.4.22

- request-scoped one-time CSRF tokens і повна rotation після login;
- private/no-store policy для admin/share ZIP та short public cache;
- cross-platform sanitized/case-insensitive unique ZIP entries;
- consistent DB/media backup snapshot, DB photo inventory і media maintenance lock;
- symlink/junction-safe cleanup і restore;
- required `zip` capability у runtime/health/self-check/docs;
- чесні passed/failed/skipped counters та required-DB CI mode;
- production Markdown allowlist і актуальний UI/UX status;
- all-item preflight та exact success/failure IDs для bulk delete;
- Linux backup permissions 0700/0600;
- integer `photos.lock_version` optimistic revision;
- checked ZIP writes та post-write stream verification.

## Фактично перевірено локально

- PHP 8.2 і 8.4: lint 82 PHP-файлів, 0 errors;
- PHP 8.2 і 8.4: `php tests/run.php` — 24 passed, 0 failed, 2 skipped;
- skipped: DB-backed album privacy і share schema/runtime suites, бо локальний `config/database.php` відсутній;
- `REQUIRE_TEST_DB=1` з відсутньою DB коректно повернув non-zero через 2 skips;
- `node --check public/assets/js/main.js` пройдено;
- `tools/build_release.php`: `mygallery_6.4.23_release.zip`, 117 entries; internal verifier і незалежне читання дали 0 stream errors, 0 forbidden internal/secret/media/runtime entries та 0 missing required v6.4.23 entries;
- `tools/self_check.php` очікувано повернув non-zero: `config/database.php missing`;
- integrity/fault tests покривають corrupt/missing/hash/inventory backup, restore rollback/commit recovery, CSRF replay/login rotation, cache policies, filename corpus, symlink containment, maintenance lock, share access/constraint, CI contract, bulk reporting і short ZIP writes.

## Не перевірено в цьому середовищі

- clean install/migrations та DB suites на реальному MySQL 8/MariaDB 10.6;
- реальний backup → verify → restore round-trip із фото;
- виконання нового GitHub Actions matrix/backup-restore/HTTP-smoke workflow на remote runner;
- Apache/Nginx/TLS/cookie/cache headers через цільовий web server;
- manual login/upload/EXIF/private/share/trash/download/browser regression;
- Linux permission assertions на фактичному production filesystem.

Тому code findings закриті, але production deploy залишається умовним до проходження environment checklist вище, `tools/self_check.php` без помилок і DB tests без skips.
