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
- Backup/restore integrity format v2 (v6.4.21): exact archive allowlist, per-file size/SHA-256, mandatory post-write streaming verification, shared fail-closed verifier, staged media extraction, transactional directory swap with compensating rollback, and journal/DB-marker recovery after interruption.
- Audit hardening v6.4.22: consistent DB/media backup snapshots with inventory and maintenance locking; symlink-safe cleanup/restore; sensitive ZIP no-store policy and safe cross-platform entries; request-scoped CSRF with login rotation; precise integer optimistic revisions; accurate test skips/required-DB CI; restrictive backup permissions; checked ZIP writes and production-doc allowlisting.
- Audit informational closure v6.4.23: focused maintenance/share/media/album-ZIP modules; exact-one-target CHECK for share links with health/self-check enforcement; PHP 8.2/8.4 CI matrix; backup→restore round-trip and HTTP security smoke checks.
- Independent audit High/Medium hardening v6.4.24: isolated fail-closed TEST_DB configuration and warning-fail runner; fail-closed session regeneration; private/no-store HTML/media policy; exact verified album ZIP with global non-blocking quota controls; streaming ZIP64 backup/release with free-disk preflight and safe atomic output; durable partial trash journals; tag revision locking and strict album create; trusted-proxy chain parsing; non-empty MySQL/MariaDB backup fixtures and Apache/Nginx CI.
- Audit remediation v6.4.25: hashed-at-rest share links with one-time URL display; sidecar-based album ZIP cache validation, stream/range controls and global cooldown; manifest-aware exclusive runtime cleanup; private runtime permissions and log rotation; bounded sharded share limiter; scalar request validation; media ETag/Range; clean commit-gated releases with BUILD_INFO inventory; pinned CI actions and EXIF orientation pixel regression tests.
- Full-audit follow-up v6.4.25: deadline-enforced streaming album ZIP creation; complete canonical trash manifests with row-locked delete; exclusive destructive maintenance; fail-closed cross-platform Git/release verification with safe iterator pruning, link containment and post-ZIP SHA checks; absolute share URLs; bounded/capacity-checked restore with private modes; FULLTEXT-only indexed search and synchronized CI regression coverage.
- Full-audit Low/Informational closure v6.4.25: transactional photo/album/cover locking, explicit 0640 derivative and 0600 migrated-original modes, synchronized log rotation, canonical IPv6 limiter keys, skip/live/reduced-motion/table/upload-progress accessibility, strict Nginx route parity, checksum/provenance release sidecars and reproducible ZIP metadata/order.
- Independent-audit High/Medium closure v6.4.26: hash-gated legacy-original cleanup; exact-commit Git release inventory that excludes ignored workspace payload; atomic validated WebP/AVIF publication with stale cleanup; resumable phase-journaled trash restore; UTF-8 album ZIP headers, complete Windows device-name handling and cache-version invalidation.
- Independent-audit Low/Informational closure v6.4.27: serialized album reorder; fail-closed checked ZIP cooldown persistence; Nginx asset-dotfile denial; truthful maintenance exit codes; checked dominant-color resampling; private atomic restore journal; bounded checked share-audit logging; canonical audit link/commit/SHA-256 traceability.
- Internal refactoring (Stage 1): Extracted duplicate SQL query building into reusable helper functions
- Internal refactoring (Stage 2): Separated business logic from HTML into `photo_service.php` (upload, edit, delete, albums)
- Internal refactoring (Stage 3): Added lightweight CLI tests for core functions and exclusions
- Internal refactoring (Stage 4): Unified admin form actions, ID reading, and redirect behavior
- Internal refactoring (Stage 5): Moved gallery/search/share-token and album ZIP fingerprint helpers into `app/includes/gallery_functions.php` to keep the main shared helper file smaller.
- Internal refactoring (Stage 6): Moved maintenance locking/path containment, share-link lookup, protected-media access and album ZIP helpers into focused include files; endpoint controllers retain request orchestration only.
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
