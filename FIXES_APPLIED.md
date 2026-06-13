# Fixes applied after full audit

Цей файл є історією вже внесених виправлень. Актуальні майбутні задачі дивіться в `POST_MVP_ROADMAP.md`, а поточні відомі обмеження — у `BUGS.md`.

## Security and release hygiene

- Removed `.git/`, `config/database.php`, session files, logs, uploaded JPEG files and test session artifacts from the release package.
- `config/config.php` now supports environment variables and uses `APP_DEBUG=false` by default.
- Production startup is blocked when `APP_DEBUG=true`, `APP_URL` is not HTTPS, or the database config uses `root`/empty password.
- `config/database.example.php` now defaults to a dedicated `gallery_user` placeholder instead of `root` with an empty password.

## Runtime hardening

- Added fail-fast checks for required PHP extensions: `pdo`, `pdo_mysql`, `gd`, `fileinfo`, `exif`, `mbstring`.
- `session_start()` is now checked. If the session folder cannot be created/written or the session cannot start, the app returns a controlled 500 instead of silently continuing.
- Added stricter session settings: `use_strict_mode`, `use_only_cookies`, `use_trans_sid=0`, secure cookie behavior for HTTPS/production.
- Admin sessions now have a one-hour idle timeout and regenerate CSRF after login.

## Albums, upload and edit

- Album creation now uses atomic MySQL upsert: `INSERT ... ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id)`.
- Upload now creates/resolves the album and inserts the photo in one database transaction after image processing succeeds.
- Edit now resolves/creates album and updates the photo inside one transaction.
- Invalid `album_id` values are no longer silently treated as “no album”.
- Album update/delete now checks whether the target album actually exists.

## Login protection

- Login limiter now tracks three buckets: exact `username + IP`, account-only, and IP-only.
- Added probabilistic cleanup of old login limiter rows.
- Added dummy password hash verification for unknown usernames to reduce timing differences.
- Added optional trusted proxy support via `TRUSTED_PROXIES`.

## Delete and media integrity

- Delete now writes a JSON manifest in `storage/trash` before moving files, so interrupted delete operations can be inspected or restored.
- Added `tools/recover_trash.php` for trash recovery/purge.
- File deletion checks parent directory writability instead of only file writability.
- `tools/cleanup_orphans.php` now treats `public/uploads/originals` as legacy/orphan storage, reports missing DB media files, and no longer considers public originals normal.
- Added `tools/migrate_legacy_originals.php` to move old public originals into `storage/originals`.

## Database and HTTP handling

- Added unique indexes for `photos.filename` and `photos.thumbnail_filename` in schema and migration `2026_06_13_hardening.sql`.
- Added extra indexes for login limiter cleanup and IP bucket lookups.
- DB failures in public pages now return controlled 500 responses and are logged instead of masquerading as empty pages or 404.
- `404.php` now explicitly sets HTTP 404 and Apache `.htaccess` has `ErrorDocument 404 /404.php`.

## UX and maintenance

- Added server-side description length limit and `maxlength` to forms.
- Upload/edit forms preserve entered title/description after validation errors.
- Date filters now validate real dates, not just `YYYY-MM-DD` shape.
- Pagination now renders a bounded window instead of all page numbers.
- README updated with `mbstring`, hardening migration, env variables, legacy original migration, trash recovery and clean ZIP rules.

## Documentation cleanup

- Added `IMPLEMENTED_FEATURES.md`, so implemented functionality is not mixed into the roadmap.
- Reworked `POST_MVP_ROADMAP.md` into a real future roadmap with priorities, file impact, risks and acceptance criteria.
- Expanded `BUGS.md` with current known limitations and operational risks.
- Updated `README.md`, `AGENTS.md` and `AUDIT_REPORT.md` to match the current hardened package.

## Verification performed for the hardened package

- `php -l` passed for all PHP files during the hardening pass.
- `node --check public/assets/js/main.js` passed during the hardening pass.
- `tools/self_check.php` is expected to fail clearly if the current machine lacks required PHP extensions such as `pdo_mysql`, `gd` or `mbstring`.
