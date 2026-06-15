# Changelog

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
