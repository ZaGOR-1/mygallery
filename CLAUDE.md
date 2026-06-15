# Claude Instructions for MyGallery

Read `AGENTS.md` first. It is the canonical instruction file for this repository.

## Project summary

MyGallery is a plain PHP photo gallery project. Keep it simple, portable and secure. Do not convert it to Laravel/Symfony/Composer/React/Vue/Bootstrap unless the user explicitly asks.

## Core rules

- Follow `AGENTS.md` as the source of truth.
- Preserve plain PHP architecture.
- Do not add runtime Node.js dependencies.
- Do not store uploaded originals in `public/`.
- Keep private originals in `storage/originals`.
- Keep admin pages protected by `require_admin()`.
- Use CSRF for every POST action.
- Use PDO prepared statements for SQL.
- Escape HTML output with `htmlspecialchars(..., ENT_QUOTES)`.
- Keep release ZIP clean via `tools/build_release.php`.

## Before editing

1. Inspect the repository structure.
2. Read `README.md`, `CHANGELOG.md`, `ROADMAP.md`, `docs/IMPLEMENTED_FEATURES.md`, `docs/BUGS.md` and `AGENTS.md`.
3. Check whether the requested feature already exists.
4. Make small, focused changes.

## After editing

Run relevant checks when possible:

```bash
php -l path/to/changed-file.php
node --check public/assets/js/main.js
php tests/run.php
php tools/self_check.php
php tools/build_release.php
```

If a command cannot run because the environment lacks PHP extensions, MySQL, Node.js or unzip, say so explicitly.

## Never include in release ZIP

- `.git/`
- `.env`
- `config/database.php`
- logs
- sessions
- uploaded media
- backups
- `dist/`
- `temp_*.php`
- `*.bak`, `*.tmp`, nested ZIPs

## Documentation

After code changes, update the relevant docs:

- `README.md`
- `CHANGELOG.md`
- `ROADMAP.md`
- `docs/IMPLEMENTED_FEATURES.md`
- `docs/BUGS.md`
- `AGENTS.md` if agent rules changed
