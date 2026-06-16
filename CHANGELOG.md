# Changelog

## v6.4.20

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
