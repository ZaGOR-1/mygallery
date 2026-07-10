# Security Audit

Актуально для MyGallery 6.4.23. Детальні finding IDs і reproduction scenarios наведені у кореневому `FULL_PROJECT_AUDIT.md`.

## Поточний code status

Під час аудиту 2026-07-10 Critical findings не знайдено. High H-01–H-03 закриті у v6.4.21, Medium M-01–M-08 і Low L-01–L-06 — у v6.4.22, Informational I-01–I-05 закриті або підтверджені у v6.4.23. Це не замінює перевірку конкретної production-конфігурації.

| Область | Стан коду |
|---|---|
| SQL injection | PDO native prepared statements; dynamic sort/filter values мають whitelist |
| XSS | output проходить `h()`/`htmlspecialchars(ENT_QUOTES)`; UI actions не покладаються на inline handlers |
| Auth/session | password hashing, rate limit, session ID regeneration, idle timeout, DB freshness check і `session_version` |
| CSRF | request-scoped one-time token, replay rejection, parallel-tab history і повна privilege-boundary rotation |
| Upload/media | JPEG MIME/image checks, random names, private originals, protected derivatives через `media.php` |
| Private albums/share | explicit privacy filters, high-entropy expiring/revocable tokens, private media no-store, exact-one-target DB CHECK |
| Album ZIP | originals лише адміну; share/admin no-store; generation lock; safe single-segment entry names |
| Backup/restore | exact manifest/hash validation, consistent snapshot/inventory, restrictive permissions, staged swap/rollback/recovery |
| Filesystem tools | path allowlists та symlink/junction containment перед destructive operations |
| Release | secret/media/runtime exclusions, production-doc allowlist, checked writer і full ZIP stream readback |

## Обов’язковий production checklist

- `APP_ENV=production`, `APP_DEBUG=false`, HTTPS `APP_URL`;
- `DocumentRoot` тільки `public/`, окремі Nginx deny rules замість покладання на `.htaccess`;
- окремий non-root DB user та застосовані всі migrations;
- `php tools/self_check.php` проходить, включно з `zip`, `photos.lock_version` і `chk_share_links_exactly_one_target`;
- `REQUIRE_TEST_DB=1 php tests/run.php` проходить без skips;
- backup ZIP зберігається поза web root із 0700/0600 або stricter permissions;
- виконаний disposable backup → verify → restore test і звірені SHA-256 originals;
- вручну перевірені login/rate-limit/CSRF, private/share access, headers, upload/trash/download flows;
- release створений тільки через `php tools/build_release.php` і додатково перевірений на цільовому сервері.

## Verdict

Усі відомі code findings аудиту 2026-07-10 закриті або, для позитивних I-01/I-02, підтверджені regression-перевірками. Production readiness є умовною до фактичного проходження checklist у цільовому LAMP/HTTPS середовищі.
