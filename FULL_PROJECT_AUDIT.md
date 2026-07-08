# MyGallery 6.4.20 — after-fix audit summary

Дата: 2026-07-08
Гілка: `beta`
Робоча папка: `D:\work\mygallery`

## Executive Summary

Після повторного аудиту High, Medium, Low та Info findings із попереднього `FULL_PROJECT_AUDIT.md` закриті у робочій копії.

Основні виправлення:

- звичайна публічна галерея більше не шукає за `photos.original_name`; filename search лишається для адмінки та token-based share view;
- optimistic locking для нових фото більше не пропускає конфлікти, коли `updated_at` був `NULL`;
- public share links у production fail-closed, якщо `storage/share_ratelimit` недоступна для запису;
- album ZIP generation отримав per-cache-key lock, щоб паралельні запити не будували один і той самий архів одночасно;
- production release ZIP більше не включає internal AI/agent/audit artifacts;
- зовнішній `D:\work\test\mygallery.zip` замінено clean release ZIP.

Production verdict: **Ready after environment setup**. Перед production все ще треба створити реальний `config/database.php`, застосувати міграції, перевірити Apache/Nginx правила, права на runtime-директорії та пройти browser smoke.

## Fixed Findings

| Severity | Finding | Status |
| --- | --- | --- |
| High | Dirty external `D:\work\test\mygallery.zip` містив workspace/runtime/dev artifacts. | Закрито: замінено clean release ZIP; scan external ZIP показав forbidden=0, internal=0. |
| Medium | Public gallery search включав `photos.original_name`. | Закрито: `public/gallery.php` вмикає original-name search тільки для shared view. |
| Medium | Optimistic locking міг не спрацювати для нових фото з `updated_at = NULL`. | Закрито: upload ініціалізує `updated_at`, edit порівнює `COALESCE(updated_at, created_at)` і відхиляє порожній token. |
| Low | Share rate-limit fail-open при недоступному storage. | Закрито: production повертає 503, dev/test лишають log-first поведінку. |
| Low | Album ZIP generation не мав lock на cache-key. | Закрито: додано generation lock і повторну перевірку cache після lock. |
| Info | Release ZIP включав internal AI/agent/audit docs. | Закрито: release builder виключає ці artifacts; release scan показав internal=0. |
| Info | `audit.md` мав застаріле твердження про недоступний PHP CLI. | Закрито: перевірки виконані через Wamp PHP path. |

## Verification

Фактично запускалось:

- PHP lint по всіх `*.php`: OK, 68 files.
- `node --check public/assets/js/main.js`: OK.
- `php tests/run.php`: 17 passed, 0 failed; DB-dependent tests skipped через відсутній `config/database.php`.
- `php tools/self_check.php`: expected fail, `config/database.php missing`.
- `php tools/build_release.php`: OK, 111 entries.
- `ZipArchive` scan `dist/mygallery_6.4.20_release.zip`: forbidden=0, internal=0.
- `ZipArchive` scan `D:\work\test\mygallery.zip`: forbidden=0, internal=0.
- `git diff --check`: only line-ending warning for local `provirka.md`.

## Remaining Environment Checks

- Реальна MySQL/MariaDB не підключалась у цьому середовищі.
- Browser smoke не виконувався: login/logout, upload JPEG, private album media, share revoke/expire, admin download original.
- Nginx/Apache правила треба перевірити на фактичному production сервері.
