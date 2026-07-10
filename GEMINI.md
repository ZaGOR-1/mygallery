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

## MyGallery AI audit workflow add-on

When the task is an audit, use the project subagents in `.gemini/agents/` instead of doing one broad pass.

Preferred audit agents:

- `mygallery-code-auditor` for PHP bugs, architecture and maintainability.
- `mygallery-security-auditor` for auth, CSRF, XSS, SQLi, uploads, share links and file access.
- `mygallery-media-storage-auditor` for uploads, originals, thumbnails, WebP/AVIF, trash, album ZIP download and regenerate tools.
- `mygallery-db-migration-auditor` for schema/migration consistency and MySQL/MariaDB compatibility.
- `mygallery-release-auditor` for clean release ZIP, `.gitignore`, backups, secrets and private media exclusions.
- `mygallery-docs-consistency-auditor` for README/CHANGELOG/ROADMAP/docs consistency.
- `mygallery-test-planner` for practical regression and manual test plans.
- `mygallery-fix-planner` for merging audit results into a prioritized implementation plan.

Rules:

- Do not edit application code during audit tasks.
- During read-only audit tasks, do not change application code. Write the audit report to the exact path requested by the canonical prompt (currently root `FULL_PROJECT_AUDIT.md` for the full-project audit); supporting audit docs may live under `docs/`.
- Check `git status --short` before any change. The workspace may contain many uncommitted changes.
- Do not treat the current working tree as clean unless Git confirms it.
- Never expose or copy real secrets from `config/database.php`, logs, sessions, backups or private media.
- Be especially careful with `download_album.php`, `share.php`, `public/admin/share.php`, upload processing, trash recovery, restore, backup and release builder.
- If fixing code later, handle only one severity group at a time and keep changes small.
