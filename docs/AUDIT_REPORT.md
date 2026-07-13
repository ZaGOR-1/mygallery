# Audit Report

Актуально для MyGallery 6.4.27. Канонічне джерело — [MYGALLERY_AUDIT.md](../MYGALLERY_AUDIT.md), аудит 2026-07-13 для Git commit `da2a729d401e0476c19661ccb736292fa4e3da45`; SHA-256 source artifact: `032d44d076592bb46a699c9a8546c02345c9972bf79b2fff924ccf4c078f71ce`.

## Статус findings 2026-07-13

| Severity | Знайдено | Поточний статус |
|---|---:|---|
| Critical | 0 | Не знайдено |
| High | 1 | H-01 виправлено у v6.4.26 |
| Medium | 4 | M-01–M-04 виправлено у v6.4.26 |
| Low | 8 | L-01 виправлено у v6.4.26; L-02–L-08 виправлено у v6.4.27 |
| Informational | 1 | I-01 виправлено у v6.4.27 canonical link/identity перевіркою |

H-01 закрито hash-gated cleanup policy для legacy originals. M-01 прив’язує release payload до exact Git tree/non-ignored dirty inventory; M-02 атомарно публікує перевірені WebP/AVIF і прибирає stale variants; M-03 додає resumable trash restore phases та CLI recovery; M-04 задає UTF-8 ZIP flags, повний Windows device-name guard і нову cache fingerprint version.

Low remediation серіалізує album reorder через stable `FOR UPDATE`, робить cooldown і maintenance I/O fail-closed, усуває Nginx dotfile bypass, перевіряє GD resample, захищає restore journal приватним atomic writer та переводить share audit на bounded checked rotation із fallback. I-01 regression перевіряє наявність source, Markdown link, Git commit, SHA-256 і production release exclusion.

## Статус findings 2026-07-12

Усі 12 Medium, 6 Low та 2 Informational знахідки M-01–M-12/L-01–L-06/I-01–I-02 реалізаційно закрито у v6.4.25. До Medium hardening додано transactional album/cover locks, explicit media modes, synchronized log rotation, canonical IPv6 identities, accessibility/upload feedback, strict Nginx routes та reproducible release ZIP із checksum/provenance sidecars. Production deployment усе одно потребує зеленого required-DB CI і staging перевірок.

## Статус findings 2026-07-10

| Severity | Знайдено | Поточний статус |
|---|---:|---|
| Critical | 0 | Не знайдено |
| High | 1 | H-01 незалежного аудиту виправлено у v6.4.24 |
| Medium | 14 | M-01–M-14 незалежного аудиту виправлено у v6.4.24 |
| Low | 16 | Не входили до цього remediation; лишаються backlog/risk review |
| Informational | 4 | I-01 operational, I-02/I-03 docs/maintainability, I-04 server inventory |

## Що закрито у v6.4.24

- fail-closed `TEST_DB_*` isolation незалежно від звичайного DB config; warnings/deprecations fail tests; session regeneration fail-closed;
- no-store/no-referrer для share/private HTML і no-store для media після privacy toggle;
- exact-source album ZIP без fallback/skip, checked add/close та повторна count/size/SHA-256 verification;
- non-blocking per-key/global ZIP locks, shared optimized cache, generation time bound і cache byte quota;
- safe atomic `.zip` output, free-disk preflight та streaming ZIP64 `ZipArchive` для backup/release;
- partial trash rollback/purge зберігає unresolved manifest/status;
- tag mutations блокують rows і bump-ять affected `photos.lock_version`; admin album create strict;
- XFF chain розбирається справа наліво з відкиданням trusted hops;
- CI: PHP 8.2/8.4 × MySQL/MariaDB, non-empty Unicode/JPEG fixture, post-restore row/hash comparison, Apache/Nginx smoke;
- production DB docs розділяють runtime CRUD і maintenance DDL users.

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

- PHP 8.2 і 8.4: lint 93 PHP-файлів, 0 syntax errors;
- PHP 8.2 і 8.4: `php tests/run.php` — 31 passed, 0 failed, 2 skipped, 761 assertions на кожній версії;
- skipped: DB-backed album privacy і share schema/runtime suites, бо локальний `config/database.php` відсутній;
- `REQUIRE_TEST_DB=1` без explicit `TEST_DB_*` і окремий probe з небезпечним non-test DB name коректно завершили runner з exit code 1 до запуску suites;
- `node --check public/assets/js/main.js` пройдено;
- default release коректно заблоковано через dirty worktree; deliberate `--allow-dirty` v6.4.27 build створив 122 entries, пройшов internal та independent stream readback, sidecar SHA-256 check і перевірку відсутності audit/config/media payload;
- `tools/self_check.php` очікувано повернув non-zero: `config/database.php missing`;
- integrity/fault tests покривають corrupt/missing/hash/inventory backup, safe CLI ZIP output, restore/trash partial recovery, CSRF replay/login rotation, session-regeneration failure, cache/privacy policies, exact album ZIP verification/locking, filename corpus, symlink containment, maintenance lock, tag/album concurrency contract, trusted proxy chains, share access/constraint, CI contract, bulk reporting і short ZIP writes.

## Не перевірено в цьому середовищі

- clean install/migrations та DB suites на реальному MySQL 8/MariaDB 10.6;
- реальний backup → verify → restore round-trip із фото;
- виконання нового GitHub Actions matrix/backup-restore/HTTP-smoke workflow на remote runner;
- Apache/Nginx/TLS/cookie/cache headers через цільовий web server;
- manual login/upload/EXIF/private/share/trash/download/browser regression;
- Linux permission assertions на фактичному production filesystem.

Усі code findings H-01, M-01–M-04, L-01–L-08 та I-01 останнього аудиту реалізаційно закриті. Production environment gates лишаються: deploy потребує `tools/self_check.php` без помилок, required DB tests без skips та цільових HTTP/Linux/manual перевірок.
