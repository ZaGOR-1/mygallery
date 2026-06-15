# Gemini Instructions for MyGallery

Read `AGENTS.md` first. It is the canonical instruction file for this repository.

## Project

MyGallery is a plain PHP personal photo gallery. It must remain simple, understandable and portable between WampServer on Windows and LAMP on Linux.

## Do not introduce

- Laravel
- Symfony
- Composer packages
- React
- Vue
- Bootstrap
- jQuery
- ORM
- Node.js runtime dependencies

Use Node.js only for development checks like:

```bash
node --check public/assets/js/main.js
```

## Important invariants

- `DocumentRoot` must point to `public`.
- `storage/`, `config/`, `database/`, `tools/` and `backups/` must not be web-accessible.
- Uploaded originals must stay in `storage/originals`.
- Public images belong in `public/uploads/large` and `public/uploads/thumbnails`.
- Admin-only pages must use `require_admin()`.
- POST actions must use CSRF.
- SQL must use PDO prepared statements.
- Release ZIP must be created through `tools/build_release.php`.

## Security focus

Be careful with:

- uploads;
- EXIF/GD image processing;
- private albums;
- share links;
- original download;
- album ZIP download;
- backup/restore;
- cleanup/recover tools;
- path traversal;
- XSS;
- SQL injection;
- CSRF;
- sessions.

## Before making changes

1. Read `AGENTS.md`.
2. Check `README.md`, `CHANGELOG.md`, `ROADMAP.md`, `docs/IMPLEMENTED_FEATURES.md` and `docs/BUGS.md`.
3. Verify whether the feature or fix already exists.
4. Make the smallest safe change.
5. Update documentation if behavior, setup or release process changed.

## Checks

Run what is relevant:

```bash
php -l path/to/file.php
php tests/run.php
php tools/self_check.php
php tools/build_release.php
node --check public/assets/js/main.js
```

Do not claim checks passed unless they were actually executed.

## Release hygiene

Never allow the release ZIP to contain:

- `.git/`
- `.env`
- `config/database.php`
- `*.log`
- `sess_*`
- real uploaded photos or originals
- backups
- `dist/`
- `temp_*.php`
- `*.zip`, `*.bak`, `*.tmp`
