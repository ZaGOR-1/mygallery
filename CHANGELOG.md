# Changelog

## v6.4.27

- Independent-audit Low remediation: album reorder locks the full row set in stable order; album ZIP cooldown state uses checked seek/truncate/exact-write/flush and fails closed with an operational log; Nginx no longer lets the assets location bypass dotfile denial, with real-server CI checks for both hidden and normal assets.
- Maintenance/media hardening: recovery, runtime cleanup and legacy-original migration now return non-zero for requested I/O failures while reporting lock-busy files separately; dominant-color extraction handles injected GD resample failure; restore journals use private atomic writes, enforce 0600 and reject unsafe symlinks/modes.
- Audit reliability: share audit append and bounded rotation run under one lock with exact-write verification and an `app.log` fallback. Current audit summaries identify the canonical source by commit and SHA-256, and regression tests verify the link, identity and release exclusion policy.

## v6.4.26

- Independent-audit High remediation: `cleanup_orphans.php --delete` now distinguishes unreferenced files from DB-referenced legacy originals. A referenced public original is deleted only when a private copy exists and both SHA-256 hashes are identical; legacy-only/hash-mismatched originals stop destructive cleanup with a migration instruction.
- Release/media Medium remediation: clean releases derive payload only from the exact Git commit tree, while dirty emergency builds use tracked plus non-ignored files. WebP/AVIF are encoded to same-directory temporary files, validated, permissioned and atomically published; unsupported/failed encoders remove stale variants.
- Recovery/ZIP Medium remediation: trash restore now has durable `restore_in_progress`/`restore_committed` phases, per-operation locking, hash-verified resumable mixed-file reconciliation and CLI continuation after interruption. Album ZIP sets UTF-8 flags in local/central headers, blocks Windows device names before a dot and invalidates old cache fingerprints.
- Regression/docs: added ignored-Git-payload, atomic derivative, raw ZIP-header/reserved-name and interrupted-trash-state coverage. Public derivative restore permissions and current audit-document references were corrected while updating the operational guidance.

## v6.4.25

- Full-audit Low/Informational remediation: photo/album and cover changes now close their transaction gaps with a consistent photo-before-album lock order; all generated derivatives enforce 0640 and migrated originals enforce 0600 under an exclusive media lock; log append/rotation share one lock; IPv6 limiter identities are canonical. Shared UI adds a skip link, live status/error feedback, reduced-motion handling, scoped/captioned tables and real upload byte progress while preserving normal no-JS POST.
- Deployment/release reproducibility: the production Nginx sample and CI use explicit PHP route allowlists with hard 404 fallback. Release payload order, timestamps and modes are canonicalized from `SOURCE_DATE_EPOCH` or the reachable commit epoch; every ZIP now receives SHA-256 and provenance sidecars, and CI compares two builds of the same commit byte-for-byte.
- Follow-up full-audit Medium remediation: album ZIP generation now uses a chunk-deadline streaming STORE writer and deadline-aware readback; trash delete locks the photo row and requires a complete original/large/thumbnail manifest; destructive maintenance tools use the exclusive media lock. Release builds probe Git without shell-specific redirection, fail closed when metadata is unavailable, prune excluded trees without skipping siblings, reject path links/escapes and re-hash the finished ZIP against `BUILD_INFO.json`.
- Restore/search/share/CI hardening: restore enforces cumulative ZIP/compression/free-space limits plus 0700/0600 private staging; copied share URLs are absolute `APP_URL` URLs; indexed search uses FULLTEXT without an `OR %LIKE%` scan; GitHub Actions are commit-SHA pinned with Dependabot updates; stale static tests and audit summary were synchronized.
- Security and privacy: share-link secrets are now stored only as SHA-256 hashes with a short admin hint; raw URLs are shown once after creation. A migration upgrades existing links and clears legacy raw tokens.
- Availability: album ZIP cache hits use a compact verified sidecar and central-directory metadata instead of re-hashing and decompressing every photo. Global/per-client gates, generation/source limits, stream concurrency controls, HTTP validators and byte-range responses reduce CPU, I/O and bandwidth abuse.
- Recovery and runtime safety: cleanup is manifest-aware and runs under the exclusive media-maintenance lock; runtime directories/files use private permissions; share rate-limit storage is sharded, TTL-cleaned and inode-bounded; application and PHP logs rotate with retention.
- Input and admin hardening: public/admin scalar request helpers reject array-shaped parameters without warnings, private/missing albums share the same 404 response, trash sorting uses timestamps, bulk edit has an explicit maximum and exact affected-row checks, setup requires a 12-character admin password, and error pages map client/server statuses correctly.
- Delivery and release hygiene: media supports ETag/Last-Modified/Range after authorization, share media has request throttling, GitHub Actions are commit-pinned with Dependabot updates, self-check validates APP_URL and runtime permissions, CLI extension profiles are task-specific, and clean release builds include BUILD_INFO.json with commit and SHA-256 inventory while excluding VCS/runtime/internal files.

## v6.4.24

- Independent audit High/Medium remediation: test DB access is fail-closed behind explicit `TEST_DB_*` credentials and a test-named database; regular installed DB config is never probed. The runner buffers output, promotes warnings/notices/deprecations to failures, reports suite/assertion counts, and admin login aborts if session ID regeneration fails.
- Privacy/export/recovery hardening: share/private-photo HTML and all media are private/no-store with no-referrer for private HTML; album ZIP is exact-source/fail-closed, checks every add/close and reopens every archive for count/size/SHA-256 verification; ZIP generation uses non-blocking per-key/global locks, shared optimized cache scope, time limit and LRU-style byte quota; partial trash rollback/purge keeps an atomic unresolved manifest visible in admin UI.
- Operations/concurrency/CI: backup/release use streaming ZIP64-capable `ZipArchive`, free-disk preflight and safe sibling-temp output publishing; tag rename/merge/delete lock rows and bump every affected photo revision; admin album create rejects duplicates without changing privacy; X-Forwarded-For is parsed right-to-left across trusted hops; CI covers PHP 8.2/8.4 × MySQL/MariaDB, a non-empty Unicode/media backup fixture with post-restore row/hash comparison, and real Apache/Nginx security smoke tests. Production docs split runtime CRUD and maintenance DDL DB users.

## v6.4.23

- Audit Informational closure (I-01–I-05): reconfirmed the clean production release policy and existing web-security baseline; extracted media maintenance, share access, protected-media access and album ZIP behavior into focused include modules, reducing `public/download_album.php` from 357 to 206 lines and keeping controllers free of helper declarations.
- Database defense-in-depth: `share_links` now has the idempotently deployed `chk_share_links_exactly_one_target` CHECK, so each token targets exactly one photo or album. Schema, migration, self-check, admin health and DB/static regression coverage all enforce the invariant.
- CI coverage: GitHub Actions now tests PHP 8.2 and 8.4, requires DB suites, performs backup → verify → restore → self-check, and runs HTTP smoke checks for the homepage, admin login, 404 behavior, CSP and `nosniff`. Full browser, Apache/Nginx, TLS and non-empty production-data validation remain deployment checks.

## v6.4.22

- Audit Medium fixes (M-01–M-08): request-scoped one-time CSRF tokens no longer evict early forms and are rotated across login; sensitive admin/share album ZIP responses are private/no-store while public ZIP caching is explicitly short; backup now combines an exclusive media lifecycle lock, `REPEATABLE READ` consistent DB snapshot and DB-bound photo inventory; cleanup/restore reject symlink/junction targets; `zip` is a required health/self-check capability; test skips are counted separately and fail CI when `REQUIRE_TEST_DB=1`; stale audit/UI/docs rules were synchronized; album ZIP entry names are cross-platform sanitized, byte-limited and case-insensitively deduplicated.
- Audit Low fixes (L-01–L-06): release Markdown uses a categorical production allowlist; bulk delete performs an all-item preflight and reports exact successful/failed IDs; backup directory/archive permissions are forced to 0700/0600 on Linux; login discards all pre-auth CSRF tokens; photo optimistic locking uses atomic `lock_version` compare/increment via the new idempotent `2026_07_10_add_photo_lock_version.sql` migration; `SimpleZipWriter` detects short writes/flush/close failures and release builds reopen/read every ZIP stream.
- Tests/CI/docs: added maintenance-lock, symlink containment, backup inventory, ZIP corruption/short-write, cache-header, filename sanitization, bulk result, CSRF privilege-boundary, precise optimistic-lock and runner-semantics coverage. CI now requires its MySQL service and performs a real empty-media backup → verify pass.

## v6.4.21

- Reliability: closed audit findings H-01–H-03 in the backup/restore pipeline. Backup format v2 now excludes media control files, records an exact allowlist plus size and SHA-256 for `database.sql`, optional config and every media file, and automatically reopens and validates the finished ZIP. `verify_backup.php` and `restore.php` use the same strict streaming validator and reject empty SQL, missing/extra/duplicate/unsafe entries, hash/size mismatches and damaged streams.
- Reliability: restore now extracts and re-hashes all media into same-filesystem staging directories before any database mutation. The restored DML, a durable `schema_migrations` operation marker and atomic directory swaps are coordinated under one DB transaction; old media directories remain available for compensating rollback. A private restore journal lets the next run deterministically finish a committed restore or roll back an uncommitted/interrupted one.
- Tests/docs: added corrupt-backup and atomic restore recovery regression tests; documented format-v2 compatibility, mandatory verification, staging, recovery and remaining real-DB/manual verification requirements.

## v6.4.20

- Audit follow-up: closed the current `FULL_PROJECT_AUDIT.md` Medium/Low/Info findings. Added stricter album-cover privacy fallback, one-time CSRF token cleanup for the legacy fallback path, safer album ZIP streaming when cache rename fails, clearer `tools/self_check.php` failure output, stronger GitHub Actions coverage for `beta`/DB/tests/release ZIP content, a repository `.gitattributes` line-ending policy, documentation consistency fixes, and a small helper extraction to `app/includes/gallery_functions.php`.
- Audit follow-up: closed the later `FULL_PROJECT_AUDIT.md` High/Medium/Low/Info findings. Public gallery search no longer uses `photos.original_name` outside token-based share views, upload now initializes `updated_at` so optimistic locking works on first edit, production share links fail closed when rate-limit storage is unavailable, album ZIP generation is locked per cache key, and release ZIP builds exclude internal AI/agent/audit artifacts.
- UI: removed the light theme entirely — the gallery is now dark-only. Deleted the `:root[data-theme="light"]` token block, the `html[data-theme="light"]` `color-scheme` rule and the `.theme-toggle` / `.theme-icon-*` styles from `public/assets/css/style.css`; removed the theme-toggle button and its SVG icons plus the inline `localStorage` theme-restore script from `app/includes/header.php`; and removed the toggle click handler from `public/assets/js/main.js`. The refined dark palette and modern component styling from v6.4.19 are kept as the single theme; no `data-theme` attribute is set anywhere anymore.

## v6.4.19

- UI: rebuilt the visual design into a cohesive, modern theme system, with a focus on fixing the previously poor light theme.
  - **Light theme** is now a warm ivory palette (`#f6f4ef` background, warm borders, refined amber-gold accent `#bd8a2e`) instead of the old flat cold gray, so it reads as designed rather than a raw inversion and stays on-brand with the gallery's warm photographic identity.
  - **Themed every surface.** Several backgrounds were hardcoded dark (`#050505`, `#0b0b0b`, `#0f0f0f`, `#080808`, `rgba(16,16,16,…)`, `rgba(24,24,24,…)`) and stayed black in light mode; they now use theme tokens (`--image-bg`, `--panel-light`, `--overlay`, `--photo-scrim`, `--scrim-rgb`). The hero/album-card scrim gradients are now token-driven, which also fixes the mobile hero scrim staying dark in light mode.
  - **New design tokens** in `:root`: `--accent-rgb` / `--accent-strong` / `--accent-contrast`, `--danger-strong` / `--danger-contrast`, `--overlay`, `--photo-scrim`, `--scrim-rgb`, `--ring`, and layered `--shadow-sm/md/lg` (warm, softer shadows in light mode). All scattered `rgba(214,167,86,…)` accent literals now resolve through `--accent-rgb` so they follow the active theme.
  - **Modernized components:** buttons get a subtle gradient sheen, hover lift and focus ring; inputs use a soft focus ring instead of a hard outline; larger, consistent corner radii; pill-shaped tags/status badges; rounded image containers, panels, stat cards, lightbox controls and the share/error/empty-state panels; the theme-toggle is now a circular icon button; and a smooth color transition plays when switching themes.
  - Fixed the malformed moon-icon SVG arc (`A9 9 9 0 1` → `A9 9 0 0 1`) in `app/includes/header.php` so the dark-mode icon renders as a clean crescent.

## v6.4.18

- Bugfix: the light/dark theme toggle now works on every page. The toggle's click handler was registered at the very end of the single `DOMContentLoaded` callback in `public/assets/js/main.js`, *after* the lightbox setup's early `return` (`if (lightboxLinks.length === 0) return;`). On any page without lightbox photo links — admin pages, the login page, album lists, an empty gallery — the callback bailed out before reaching the toggle code, so the button was never wired up and clicking it did nothing. The toggle setup now runs *before* that early return, so it is always attached. (The theme variables, the `header.php` restore script and the toggle logic itself were already correct.)
- Bugfix: the theme toggle's icon-swap CSS now applies. The three icon-visibility rules (`.theme-icon-light` / `html[data-theme="light"] .theme-icon-dark` / `html[data-theme="light"] .theme-icon-light`) had been pasted *inside* the `input, select, textarea { … }` rule block in `public/assets/css/style.css`, so they were parsed as descendant selectors (`input .theme-icon-…`) or dropped in browsers without CSS-nesting support — both sun and moon icons rendered at once and never changed on click. The rules are now top-level.

## v6.4.17

- Hardening: fixed five low-severity audit findings (L1–L5).
  - **L1** (`public/admin/health.php`): the health page now reports the status of every required PHP extension. The `foreach (required_php_extensions() ...)` loop body was empty, so only WebP/AVIF support was shown; it now fills `$extensionRows[]` via `extension_loaded()` per extension.
  - **L2** (`public/admin/share.php`): the share audit log no longer writes full share tokens in cleartext. A new `share_token_fingerprint()` helper logs only a short prefix plus a truncated SHA-256, so anyone reading `storage/logs/share_audit.log` can no longer reconstruct working share links.
  - **L3** (`public/404.php`): the error page now sends the computed `$errorStatusCode` (`>= 400 ? $errorStatusCode : 404`) instead of a hardcoded `404`, matching `500.php`.
  - **L4** (`public/admin/edit.php`): on a failed POST re-render, the hidden optimistic-lock `updated_at` field is repopulated from `$_POST['updated_at']` instead of the current DB value, so the lock is no longer silently weakened.
  - **L5** (`public/admin/download.php`): the download `Content-Type` is now derived from `$photo['mime_type']` (falling back to the file extension) instead of a hardcoded `image/jpeg`, so `.webp`/`.avif` originals are served with the correct type.

## v6.4.16

- Hardening: the per-IP rate limit in `public/share.php` is now updated under an exclusive `flock` and validates the decoded JSON shape (audit finding M8). The previous unlocked read-modify-write lost increments under concurrency (so the counter undercounted and the limit could be bypassed), and a corrupt counter file made `json_decode` return `null`, causing a warning/`TypeError` on `$limitArr['time']` under `strict_types`. The counter is now read, incremented and written while holding `LOCK_EX`, and a non-array or missing `count`/`time` simply resets the bucket — mirroring the `flock` pattern already used in `download_album.php`.

## v6.4.15

- Hardening: the album ZIP download cooldown is no longer keyed on the client User-Agent (audit finding M7). `album_download_lock_key()` previously hashed `IP | User-Agent | scope`, so an anonymous client could get a fresh 90-second bucket on every request just by varying its UA header, defeating the rate limit that guards expensive ZIP generation (up to 200 photos / 500 MB). The key is now `IP | scope` only.

## v6.4.14

- Hardening: album privacy in the gallery query no longer depends on a `global $isSharedView` (audit finding M6). `build_gallery_where_clause()` previously read that global to decide whether to drop the `albums.is_private` filter, so any future code path that set the global before a count/fetch could silently expose private albums. The privacy decision is now an explicit `bool $includePrivate` argument threaded through `count_photos()` and `fetch_photos()`; `public/gallery.php` passes its local `$isSharedView` flag in directly. No behavior change — shared views still see only the explicitly shared album (the `album_id` filter still applies).

## v6.4.13

- Bugfix: `delete_album_with_validation()` in `app/includes/photo_service.php` no longer re-throws the caught exception with `throw clone $exception` (audit finding M5). Cloning a `Throwable` discarded the original stack trace/identity and would fatal on non-cloneable exceptions, masking the real cause of a failed album delete. It now re-throws the original with `throw $exception;`.

## v6.4.12

- Reliability: `schema_migrations` is now included in database backups (audit finding M4). `tools/backup.php` previously dumped a hardcoded table allowlist that omitted the migration registry, so after a restore the registry was empty and `tools/migrate.php` re-ran every migration against already-migrated data. The table is now part of the export list, so a restored database keeps its true migration state and migrations are not replayed. Added a regression assertion in `tests/unit/backup_restore_test.php`.

## v6.4.11

- Reliability: made database restore and migrations transactional/idempotent (audit finding M3). `tools/restore.php` now applies the SQL dump inside a single transaction (`beginTransaction`/`commit`/`rollBack`), so a failure partway through fully rolls back and leaves the database in its previous consistent state — and media files are still wiped only after the database restore succeeds. To make this possible, `tools/backup.php` no longer emits `LOCK TABLES`/`UNLOCK TABLES` (which cause an implicit commit in MySQL and would defeat the transaction); legacy backups that still contain them fall back to the previous non-atomic apply with a warning. Removed the dead `$tmpSql` temp-file code in `restore.php`. `tools/migrate.php` now wraps each migration apply with explicit error handling that documents and enforces the idempotency contract (DDL auto-commits in MySQL, so a half-applied migration is simply not recorded and a re-run safely catches up); the existing migrations already guard every statement with `IF NOT EXISTS`/`IF EXISTS`/`information_schema` checks.

## v6.4.10

- Reliability: hardened WebP/AVIF derivative handling during upload (audit finding M2). `create_webp_copy`/`create_avif_copy` now check the `imagewebp`/`imageavif` return value and `unlink` any partial/zero-byte output so broken derivatives can no longer pass an "exists" check and leak into `srcset`. The upload failure cleanup block now also removes the `.webp`/`.avif` siblings of the `large` and `thumbnail` images (via a new `derivative_path()` helper), so a failure after derivative creation no longer orphans them on disk.

## v6.4.9

- Security: removed raw SQL interpolation in `public/admin/bulk_edit.php` (audit finding M1). Both `albums.cover_photo_id = NULL` updates built their `IN (...)` clause via `implode(',', $photoIds)` instead of placeholders. They now use `?` placeholders (`array_fill(0, count($photoIds), '?')`) with `$photoIds` passed as bound params, matching the adjacent `photos` updates and the project's prepared-statements-only policy. The duplicated `// Remove from album` comment was removed.

## v6.4.8

- Security: fixed private original leak in album ZIP download (`public/download_album.php`). Public (`?album_id=`) and share-token downloads previously received byte-for-byte private originals from `storage/originals`. Originals are now served only to logged-in admins; non-admin and token downloads get the optimized `uploads/large` copy. The cache key now includes the variant (`orig`/`opt`) so an admin-generated archive containing originals can never be served to a non-admin from cache.

## v6.4.7

- Security: fixed admin login brute-force bypass (`public/admin/login.php`). The lockout was only gated by a trivial, self-revealing arithmetic CAPTCHA whose correct answer reset the time lock, allowing unlimited automated password guessing. The lockout is now strictly time-based (`login_attempts.locked_until`): while a bucket is locked, login attempts are rejected outright with a `Retry-After` header, and no CAPTCHA can bypass it. Removed the CAPTCHA field and related session state.

## v6.4.6

- Made album privacy, album sort order and dominant color migrations idempotent so they can be rerun safely.
- Fixed `public/admin/bulk_edit.php` rollback handling for validation errors inside transactions.
- Added cooldown/rate limiting for `public/download_album.php` ZIP generation to reduce server load.
- Strengthened `tools/restore.php` by validating backup manifest, paths, allowed entries and media streams before changing the database.
- Removed the duplicate typo production-audit prompt document and synchronized current docs with v6.4.6.
- Added direct `auth.php` includes for public pages that call `is_admin_logged_in()` before loading the shared header.

## v6.4.5

- Fixed Nested Delete Form bug in Admin Index: relocated individual photo deletion forms outside the parent bulk-edit form wrapper using HTML5 form attributes on submit buttons. This prevents browsers from mistakenly submitting the parent bulk-edit form to `bulk_edit.php` instead of the deletion request to `delete.php`.

## v6.4.4

- Implemented Strengthened Path Validation in Restore Tool: added robust path normalization (`restore_normalize_path`) and directory containment checks (`restore_path_is_inside`) coupled with strict filename formatting checks (`valid_photo_filename`) in `tools/restore.php` to prevent any Path Traversal or Zip Slip directory escape scenarios.

## v6.4.3

- Implemented Share Link Target Existence Validation: added database lookup verification inside `public/admin/share.php` to prevent generating and saving share links for non-existent photos or albums, protecting links integrity.

## v6.4.2

- Implemented Improved CSRF Failure UX: introduced a centralized, styled helper `require_csrf()` and `csrf_error()` in `csrf.php`. All administrator-accessible POST endpoints (upload, edit, delete, albums, tags, bulk actions, trash recovery) now stream a descriptive 400 Bad Request error page using the standard site layout when CSRF validations fail, rather than redirecting silently or raising generic raw text errors.

## v6.4.1

- Implemented Friendly Error Pages for Shared Links: replaced raw plain text error responses in `public/share.php` with styled, user-friendly HTML error pages utilizing a dynamic `public/404.php` template. This covers various errors including missing links (404), expired links (410), missing photos/albums (404), and malformed requests (400) gracefully.

## v6.4.0

- Implemented Download Album as ZIP: added an action button to the gallery page when viewing a specific album, allowing users to download all photo files of that album in a single ZIP archive. The ZIP file is generated dynamically, names files after their original upload filenames, includes collision resolving, and implements size/count security boundaries. It respects album privacy rules, allowing access only to public albums, admins, or via secure expiring share links.

## v6.3.0

- Implemented Next-Gen Images support: automatically generates WebP and AVIF optimized copies of thumbnail and large images alongside original JPEGs. Wraps public and admin image elements in `<picture>` elements. Integrates next-gen delivery in the Lightbox viewer, coordinates file cleanup/restore in the trash system, and updates the image regeneration CLI tool to allow batch backfilling next-gen formats for existing photos.

## v6.2.1

- Implemented Dominant Color Placeholder: automatically extracts the average/dominant color of uploaded images using GD resample interpolation, storing it in the database. Employs a CSP-compliant JavaScript routine to apply these placeholders as image background colors prior to full loading. Added backfilling capability for all existing photos via the image regeneration tool.

## v6.2.0

- Implemented Hidden/Private Albums allowing administrators to hide specific albums and their photos from the public gallery, public albums page, search, and navigation, while keeping them accessible in the admin panel or via secure private share links.

## v6.1.2

- Implemented Custom Album Ordering allowing administrators to specify and adjust the visual sequence of albums in public views using Up/Down buttons in the admin panel.

## v6.1.1

- Implemented Trash Bin UI (/admin/trash.php) with visual listing, restoration, individual purging, and empty trash functionality.
- Implemented drag-and-drop file upload zone in the admin photo upload view.
- Implemented keyboard arrow keys (Left/Right) and chevron buttons navigation inside the Lightbox viewer.
- Implemented one-click "Copy to Clipboard" button for private photo and album share links.
- Implemented collapsible search and filter drawers (<details>) to optimize mobile layout screen space.
- Corrected pagination layout alignment issue for active items (styled strong tag to match page buttons).
- Upgraded typography to Inter font family and added global border-radius and soft transitions.

## v6.1.0

- Implemented Tag Management Dashboard (`/admin/tags.php`) with rename, merge, delete, and prune functionality.
- Implemented Bulk Edit feature allowing mass updates of albums and tags for multiple selected photos.
- Implemented mass upload feature allowing multiple JPEG files to be uploaded simultaneously via the admin interface.
- Implemented Album Covers: administrators can now select a cover photo for each album.
- Created Public Albums Page (`/albums.php`) for easy navigation across albums, displaying covers and photo counts.
- Blocked `tools/backup.php --output` paths inside `public/` so private backup ZIP files cannot be accidentally exposed by the web server.
- Added trusted reverse proxy HTTPS detection through `TRUSTED_PROXIES` and `X-Forwarded-Proto: https`.
- Updated admin health-check and README production notes for reverse proxy HTTPS.
- Aligned health/self-check runtime validation with the documented PHP 8.2+ requirement.
- Restored current maintenance docs and synchronized README/AGENTS documentation references.
- Updated release ZIP documentation to use `dist/mygallery_<VERSION>_release.zip`.
- Excluded empty legacy/non-runtime directories from clean release ZIP builds.
- Expanded `ROADMAP.md` with prioritized future feature candidates, complexity notes and verification steps.
- Added default expiry controls for private photo and album share links.
- Removed the leftover temporary migration/debug script from the repository root.
- Moved remaining inline styles and inline confirm handlers from share/edit/bulk/gallery views into shared CSS/JS patterns.
- Synchronized README, AGENTS and audit documentation with the current docs paths, PHP version and image limits.
- Fixed `public/photo.php` previous/next navigation for PDO native prepared statements by avoiding reused named placeholders.
- Cleaned a minor duplicated `return true` in file cleanup helper.
- Rebuilt full clean V6 release ZIP from the stable V6 codebase.

## v6.0.2

- Patched a Zip Slip (Path Traversal) vulnerability in `tools/restore.php`.
- Added the missing `share_links` table to `tools/backup.php`, `admin/health.php`, and `tools/self_check.php` to prevent data loss.
- Fixed a `500 Internal Server Error` in `admin/index.php` and `gallery.php` caused by an incorrect column name `photos.filesize` (corrected to `photos.file_size`).

## v6.0.0 - Tags, Stats and Error Pages

### Added

- Added photo tags with new `tags` and `photo_tags` tables.
- Added comma-separated tag input on photo upload and edit pages.
- Added public and admin gallery filtering by tag.
- Added tag pills on gallery cards, admin cards and the photo detail page.
- Added `/admin/stats.php` with content, EXIF, storage, camera, lens, tag, monthly and latest-upload statistics.
- Added standalone `public/500.php` error page.
- Improved `public/404.php` as a standalone styled error page.
- Added Apache `ErrorDocument 500 /500.php` rule.
- Added `database/migrations/2026_06_13_add_tags.sql`.

### Changed

- Updated `database/schema.sql` for clean installs with tag support.
- Updated admin navigation with a Statistics link.
- Updated admin health-check DB table checks for `tags` and `photo_tags`.
- Updated backup export to include `tags` and `photo_tags`.
- Updated documentation to describe v6.0.0 functionality.

### Notes

- Existing installations must apply the new tag migration after the previous migrations.
- Tags are intentionally simple: one photo can have many tags, and tags are automatically pruned when they are no longer used.

## v5.0.0 - Maintenance and Production Base

### Added

- Added clean release builder.
- Added admin health-check page.
- Added private original download for administrators.
- Added image regeneration tool.
- Added backup tool and backup/restore documentation.

### Hardened

- Strengthened production checks, admin sessions, login limiter and GD error handling.
- Cleaned release ZIP generation to avoid shipping private files.

## v6.1.0-docs-cleanup

- Synchronized Markdown documentation with the actual V6.1.0 codebase.
- Added missing documentation files referenced by README/AGENTS.
- Filled BUGS.md with current known limitations and operational notes.
- Removed duplicated AUDIT.md in favor of AUDIT_PROMPT.md.
- Confirmed clean release workflow via tools/build_release.php.
