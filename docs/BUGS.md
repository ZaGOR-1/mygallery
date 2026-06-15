# Known Issues and Limitations

Актуальний стан для MyGallery v6.0.1. Критичних відкритих багів у коді наразі не зафіксовано, але є обмеження й ризики, які треба враховувати.

## Open issues

### LOW-001: Manual ZIP робочої папки не є release

Якщо архівувати всю папку проєкту вручну, у ZIP можуть потрапити `.git`, `config/database.php`, логи, сесії й завантажені фото.

**Правильний спосіб:**

```bash
php tools/build_release.php
```

І використовувати ZIP із `dist/`.

### LOW-002: Documentation audit files можуть швидко застарівати

`AUDIT_REPORT.md` і `FULL_PROJECT_AUDIT.md` описують стан на момент конкретного аудиту. Після нових фіч їх треба оновлювати або явно позначати як історичні.

### LOW-003: MySQL FULLTEXT має природні обмеження

Короткі слова, українські відмінки й деякі символи можуть шукатися неідеально. Для майбутнього можна додати комбінований `FULLTEXT + LIKE` або покращити пошук через tags.

### LOW-004: Web server config залежить від Apache

Проєкт має `.htaccess` для Apache. На Nginx ці правила не працюють автоматично. Для Nginx треба окрема конфігурація, яка блокує доступ до приватних директорій і PHP у uploads.

### LOW-005: Старі production-сесії треба чистити вручну після великих оновлень

Після переходу між великими версіями краще видалити старі session-файли:

```powershell
Remove-Item storage\sessions\sess_* -ErrorAction SilentlyContinue
```

Після цього треба увійти в адмінку заново.

## Operational checklist

Після оновлення проєкту перевірити:

- `php tools/self_check.php`;
- міграції БД;
- login/logout;
- upload JPEG;
- edit tags;
- gallery tag filter;
- admin stats;
- admin health;
- private original download;
- 404/500 pages;
- `php tools/build_release.php`.
