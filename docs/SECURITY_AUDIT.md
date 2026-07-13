# Security Audit

Актуально для MyGallery 6.4.27. Детальні finding IDs і reproduction scenarios наведені у канонічному [MYGALLERY_AUDIT.md](../MYGALLERY_AUDIT.md): Git commit `da2a729d401e0476c19661ccb736292fa4e3da45`, SHA-256 `032d44d076592bb46a699c9a8546c02345c9972bf79b2fff924ccf4c078f71ce`.

## Поточний code status

Під час незалежного аудиту 2026-07-13 знайдено 1 High, 4 Medium, 8 Low і 1 Informational finding. H-01/M-01–M-04/L-01 закриті у v6.4.26; L-02–L-08 та traceability I-01 — у v6.4.27. Відкритих code findings цього аудиту немає. Це не замінює перевірку конкретної production-конфігурації.

| Область | Стан коду |
|---|---|
| SQL injection | PDO native prepared statements; dynamic sort/filter values мають whitelist |
| XSS | output проходить `h()`/`htmlspecialchars(ENT_QUOTES)`; UI actions не покладаються на inline handlers |
| Auth/session | password hashing, rate limit, fail-closed session ID regeneration, idle timeout, DB freshness check і `session_version` |
| CSRF | request-scoped one-time token, replay rejection, parallel-tab history і повна privilege-boundary rotation |
| Upload/media | JPEG MIME/image checks, random names, private originals, protected derivatives через `media.php`, no-store privacy-toggle policy |
| Private albums/share | explicit privacy filters, high-entropy expiring/revocable tokens, private media no-store, exact-one-target DB CHECK |
| Album ZIP | strict sources, chunk-deadline STORE writer, UTF-8/cross-platform names, exact readback, non-blocking locks/quota і fail-closed checked cooldown state |
| Backup/restore | streaming ZIP64, cumulative/ratio/free-space limits, exact validation, private atomic journal, 0700/0600 originals staging, consistent snapshot та atomic recovery |
| Tests/CI | explicit isolated TEST_DB only, warning-fail runner, PHP/MySQL/MariaDB matrix, pinned Actions/Dependabot, restore fixture, Apache/Nginx smoke |
| Filesystem tools | path allowlists, symlink/junction containment, hash-gated cleanup, resumable trash restore та truthful maintenance exit codes |
| Audit logging | token fingerprints, private files, synchronized bounded rotation, exact append result і observable fallback |
| Release | fail-closed reachable Git provenance, exact-commit/non-ignored payload inventory, safe tree/link checks, post-close verification та reproducible sidecars |

## Обов’язковий production checklist

- `APP_ENV=production`, `APP_DEBUG=false`, HTTPS `APP_URL`;
- `DocumentRoot` тільки `public/`, окремі Nginx deny rules замість покладання на `.htaccess`;
- окремий runtime DB user тільки з CRUD та окремий maintenance user для migrations/restore;
- `php tools/self_check.php` проходить, включно з `zip`, `photos.lock_version` і `chk_share_links_exactly_one_target`;
- explicit `TEST_DB_*` + `REQUIRE_TEST_DB=1 php tests/run.php` проходить без skips/warnings;
- backup ZIP зберігається поза web root із 0700/0600 або stricter permissions;
- виконаний disposable backup → verify → restore test і звірені SHA-256 originals;
- вручну перевірені login/rate-limit/CSRF, private/share access, headers, upload/trash/download flows;
- release створений тільки через `php tools/build_release.php` і додатково перевірений на цільовому сервері.

## Verdict

Code findings останнього аудиту закриті. Production environment gate залишається: readiness є умовною до фактичного проходження checklist у цільовому LAMP/HTTPS середовищі.
