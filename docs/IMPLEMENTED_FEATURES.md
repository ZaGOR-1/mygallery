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
- Album management
- Tags management
- Lightbox UI
- CLI tools (setup, self-check with DB validation, cleanup, migrate, backup, verify, restore, recover, runtime cleanup)
- Internal refactoring (Stage 1): Extracted duplicate SQL query building into reusable helper functions
- Internal refactoring (Stage 2): Separated business logic from HTML into `photo_service.php` (upload, edit, delete, albums)
- Internal refactoring (Stage 3): Added lightweight CLI tests for core functions and exclusions
- Internal refactoring (Stage 4): Unified admin form actions, ID reading, and redirect behavior
- Duplicate Detection: Added `original_sha256` column to `photos` table to prevent uploading the same exact image file twice
- Shareable Private Links: Generate secure expiring tokens to share a specific photo or entire album via `share.php` without granting admin access
- CSP-friendly UI cleanup: share/edit/bulk/gallery views use shared CSS classes and `data-confirm` handlers instead of inline styles or inline JavaScript
