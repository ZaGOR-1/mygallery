# UI/UX Status and Recommendations

Актуально для поточної версії з `VERSION`. Це status-документ, а не план повторної реалізації вже готових можливостей.

## Реалізовано

- темний адаптивний інтерфейс на CSS Grid без UI-фреймворків;
- системний сучасний font stack, округлення, тіні, transition і видимі focus states;
- оформлена пагінація в public/admin views;
- responsive images, lazy loading і dominant-color placeholders;
- lightbox із zoom/pan, кнопками previous/next та клавішами `ArrowLeft`/`ArrowRight`/`Escape`;
- кнопки копіювання share links через Clipboard API з fallback;
- компактні `<details>`-панелі фільтрів;
- drag-and-drop зона для JPEG upload;
- skip-to-content link, live regions для flash/copy/upload, видимий clipboard failure і byte-level upload progress із no-JS fallback;
- `prefers-reduced-motion`, table captions і scoped column headers у статистиці/health;
- окремий Trash UI для restore/purge;
- user-friendly 404/500/share/CSRF error pages;
- dark-only theme: перемикач light/dark навмисно видалений.

Фактичний перелік можливостей підтримується в `docs/IMPLEMENTED_FEATURES.md`. Майбутні продукт-фічі зберігаються тільки в `ROADMAP.md`.

## Залишкові рекомендації

### Accessibility regression

- вручну перевіряти повну keyboard navigation у public gallery, lightbox і всіх admin forms;
- підтримувати помітний `:focus-visible` без залежності від кольору;
- перевіряти contrast тексту, badges, disabled/error states через WCAG tooling;
- regression-перевіряти `aria-live`, progress та focus target після змін shared layout/upload/copy behavior.

### Mobile and slow-network QA

- перевіряти галерею на 320/375/768 px без horizontal scroll;
- тестувати довгі українські album/tag/title values і великі filter sets;
- перевіряти dominant-color placeholders, image aspect ratio та layout shift на throttled network;
- контролювати, що основні GET filters, pagination, upload і delete лишаються зрозумілими без JavaScript.

### Maintenance rule

Перед додаванням рекомендації спочатку звіряти `public/assets/css/style.css`, `public/assets/js/main.js`, templates і `docs/IMPLEMENTED_FEATURES.md`. Реалізований пункт треба переносити до секції «Реалізовано», а не залишати як майбутню задачу.
