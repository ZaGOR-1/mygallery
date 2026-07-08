# Implemented Features

This file tracks the implemented features for the MyGallery project.

## Core
- JPEG upload validation
- EXIF parsing and orientation fix
- Private originals in `storage/originals`
- Large and thumbnail versions
- CSRF protection
- Login rate limiter
- Admin session management and invalidation after password change
- PDO prepared statements
- Fulltext and LIKE search fallback
- Public gallery search by title/description only; original filename search is limited to admin and token-based share views
- Album management
- Tags management
- Lightbox UI
- CLI tools (setup, self-check with DB validation, cleanup, migrate, backup, verify, restore, recover, runtime cleanup)
- Internal refactoring (Stage 1): Extracted duplicate SQL query building into reusable helper functions
- Internal refactoring (Stage 2): Separated business logic from HTML into `photo_service.php` (upload, edit, delete, albums)
- Internal refactoring (Stage 3): Added lightweight CLI tests for core functions and exclusions
- Internal refactoring (Stage 4): Unified admin form actions, ID reading, and redirect behavior
- Internal refactoring (Stage 5): Moved gallery/search/share-token and album ZIP fingerprint helpers into `app/includes/gallery_functions.php` to keep the main shared helper file smaller.
- Duplicate Detection: Added `original_sha256` column to `photos` table to prevent uploading the same exact image file twice
- Shareable Private Links: Generate secure expiring tokens to share a specific photo or entire album via `share.php` without granting admin access
- CSP-friendly UI cleanup: share/edit/bulk/gallery views use shared CSS classes and `data-confirm` handlers instead of inline styles or inline JavaScript
- UI/UX modernizations and Trash Bin UI (v6.1.1): Added Inter font-family, border-radius, shadows, transitions, drag-and-drop file uploads, clipboard copying for share links, details drawer for gallery/admin filter panels, Arrow key navigation inside the Lightbox, and a visual Trash Bin UI dashboard (/admin/trash.php) with restore and purge actions.
- Custom Album Ordering (v6.1.2): Added sort_order column to the albums database table. Implemented custom sequencing via up/down buttons on the admin albums control panel with automatic gap normalization, ordering the public album covers page and select dropdowns by this field.
- Hidden/Private Albums (v6.2.0): Added is_private column to the albums database table. Implemented option to hide specific albums and all of their photos from the public gallery feed, public albums page, search queries, and public next/prev photo navigation. Enabled admin management, filtering by private albums in the admin dashboard, and full support for sharing hidden albums using secure expiring share links.
- Dominant Color Placeholder (v6.2.1): Added dominant_color column to the photos database table. Automatically extracts average image colors via 1x1 pixel GD resample interpolation during upload and trash recovery. Implemented background placeholders for loading images in public views using a CSP-compliant JS DOM-property injection routine. Updated image regeneration tool to allow backfilling dominant colors for existing photos.
- Next-Gen Images support (v6.3.0): Automatically generates WebP and AVIF optimized copies of thumbnail and large images alongside original JPEGs. Wraps all frontend and admin image elements inside `<picture>` elements for native browser-level negotiation. Fully supports next-gen delivery within the Lightbox viewer using custom data attributes, automatically coordinates cleanup and restoration in the trash system, and updates the image regeneration CLI tool to allow batch backfilling next-gen formats for existing photos.
- Download Album as ZIP (v6.4.0): Added a "Download Album (ZIP)" action button on the gallery view when filtering by a specific album. Uses PHP's ZipArchive to pack large-resolution versions of the album photos on the fly, naming them according to their original filenames (with automatic duplication resolution) and streaming them securely. Ensures security with rate-limit and size guards (up to 200 photos or 500 MB) and honors album privacy restrictions (only accessible by administrators, for public albums, or via secure expiring album share links).
- Album ZIP generation hardening: archive generation is locked per cache key to prevent duplicate concurrent builds, while cached ZIP streaming remains reusable after the lock is released.
- Production share-link rate-limit hardening: public share pages fail closed with a temporary-unavailable error when rate-limit storage cannot be written in production.
- Friendly error pages for shared links (v6.4.1): Replaced plain text error messages in `public/share.php` with beautifully styled, informative error pages utilizing the dynamic `public/404.php` template layout. Handles specific error codes (like 410 Gone for expired links, 400 Bad Request for malformed URLs, and 404 for missing photos/albums) gracefully.
- Improved CSRF Failure UX (v6.4.2): Introduced a centralized, styled helper `require_csrf()` and `csrf_error()` in `csrf.php`. All administrator-accessible POST endpoints (upload, edit, delete, albums, tags, bulk actions, trash recovery) now stream a descriptive 400 Bad Request error page using the standard site layout when CSRF validations fail, rather than redirecting silently or raising generic raw text errors.
- Share Link Target Existence Validation (v6.4.3): Added strict database check validations in `public/admin/share.php` to verify if a photo or album exists prior to generating a share link token and storing it, preventing orphaned or broken links.
- Strengthened Path Validation in Restore Tool (v6.4.4): Integrated robust path containment checks (`restore_path_is_inside`) and filename validation (`valid_photo_filename`) in `tools/restore.php` to fully secure the ZIP archive extraction process against Path Traversal and Zip Slip vulnerabilities.
