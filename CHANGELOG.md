# Changelog

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
- Expanded `POST_MVP_ROADMAP.md` with prioritized future feature candidates, complexity notes and verification steps.

## v6.0.2

- Patched a Zip Slip (Path Traversal) vulnerability in `tools/restore.php`.
- Added the missing `share_links` table to `tools/backup.php`, `admin/health.php`, and `tools/self_check.php` to prevent data loss.
- Fixed a `500 Internal Server Error` in `admin/index.php` and `gallery.php` caused by an incorrect column name `photos.filesize` (corrected to `photos.file_size`).

## v6.0.1

- Fixed `public/photo.php` previous/next navigation for PDO native prepared statements by avoiding reused named placeholders.
- Cleaned a minor duplicated `return true` in file cleanup helper.
- Rebuilt full clean V6 release ZIP from the stable V6 codebase.

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

## v6.0.1-docs-cleanup

- Synchronized Markdown documentation with the actual V6.0.1 codebase.
- Added missing documentation files referenced by README/AGENTS.
- Filled BUGS.md with current known limitations and operational notes.
- Removed duplicated AUDIT.md in favor of AUDIT_PROMPT.md.
- Confirmed clean release workflow via tools/build_release.php.
