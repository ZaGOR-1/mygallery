# Audit Report

Поточну версію дивіться у `VERSION`. Архівний повний аудит 2026-06-16 збережений у `docs/AUDIT_FINDINGS_2026-06-16.md`; робочий аудит 2026-07-08 збережений у кореневому `FULL_PROJECT_AUDIT.md`.

## Latest Audit Summary (2026-07-08, стан після High/Medium/Low/Info фіксів)

| Severity | Count | Стан |
|---|---:|---|
| Critical | 0 | Не знайдено поточних Critical runtime-вразливостей |
| High | 1 | Закрито: зовнішній dirty ZIP замінений clean release ZIP; clean builder тепер не пакує internal/audit artifacts |
| Medium | 2 | Закрито: public filename-search privacy oracle; optimistic lock для `updated_at = NULL` |
| Low | 2 | Закрито: production share rate-limit fail-closed; album ZIP generation lock per cache-key |
| Informational | 2 | Закрито/уточнено: release policy для AI/audit docs; stale `audit.md`/Nginx висновки |

Поточний runtime-код після цього проходу не має відкритих High/Medium/Low findings із `FULL_PROJECT_AUDIT.md`. Єдиний High був не в коді застосунку, а в зовнішньому ручному ZIP-архіві `D:\work\test\mygallery.zip`; він замінений clean release ZIP з `tools/build_release.php`.

## Що виправлено після повного аудиту

- Видалено тимчасовий `temp_migrate.php` з кореня репозиторію.
- Для приватних share links додано строк дії з дефолтом 30 днів.
- Залишкові inline `style` / `onclick` у `edit.php`, `bulk_edit.php`, `gallery.php` і `share.php` замінено на CSS-класи та `data-confirm`.
- README, AGENTS, CHANGELOG і audit docs синхронізовано з фактичною структурою, PHP-версією та лімітами з `config/config.php`.
- Public gallery більше не шукає за `photos.original_name`; original filename search лишився для адмінки та token-based share view.
- Upload ініціалізує `photos.updated_at`, а edit optimistic lock використовує `COALESCE(updated_at, created_at)`.
- Public share links у production fail-closed, якщо rate-limit storage недоступний.
- Album ZIP generation має окремий lock на cache-key.
- Release builder виключає `.agents/`, `.gemini/`, `.github/`, agent docs, root audit docs і AI/audit docs із production ZIP.

Деталі див. у `FULL_PROJECT_AUDIT.md` та `docs/SECURITY_AUDIT.md`.
