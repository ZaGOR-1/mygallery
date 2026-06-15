# Рекомендації з покращення UI/UX інтерфейсу MyGallery v6.4.6

Цей документ містить результати детального аудиту інтерфейсу користувача (UI) та користувацького досвіду (UX) фотогалереї **MyGallery**. Усі пропозиції розроблені з дотриманням філософії проєкту: **чистий HTML5, CSS3 та Vanilla JavaScript**, без важких сторонніх фреймворків (Bootstrap, Tailwind, React тощо).

---

## 🔍 Загальний аналіз поточного стану

### 🟢 Сильні сторони поточного інтерфейсу:
1. **Тема оформлення:** Темна палітра (`#101010` та `#181818`) є класичним та найкращим рішенням для фотогалерей. Вона не відволікає від перегляду фото і фокусує погляд на зображеннях.
2. **Швидкість:** Завдяки відсутності фреймворків інтерфейс завантажується миттєво.
3. **Адаптивність:** Використання CSS Grid (`repeat(auto-fill, minmax(230px, 1fr))`) та `srcset` забезпечує правильне масштабування карток на більшості пристроїв.
4. **Лайтбокс:** Наявність вбудованого переглядача з підтримкою Zoom & Pan (масштабування та перетягування) підвищує інтерактивність.

### 🔴 Слабкі сторони та зони для росту:
1. **Брак візуальної м'якості:** Відсутність округлення кутів (`border-radius`), тіней та плавності переходу станів робить інтерфейс дещо "сухим" та навмисно суворим.
2. **Типографіка:** Шрифт Arial є системним та виглядає надто просто для сучасного веб-портфоліо.
3. **Функціональні "тертя" (UX Papercuts):**
   - **Лайтбокс:** Неможливо переходити на наступне/попереднє фото всередині лайтбоксу без його закриття.
   - **Пагінація:** Поточна активна сторінка відображається через тег `<strong>` без оформлення рамки, через що елементи зсуваються та виглядають розірваними.
   - **Копіювання посилань:** Створення приватних посилань вимагає від адміна виділяти довгі URL вручну.
   - **Фільтри:** Блок пошуку та фільтрації на мобільних пристроях займає занадто багато місця, зміщуючи саму галерею далеко вниз.

---

## 🛠 Конкретні рекомендації з покращення

Рекомендації розділено за пріоритетом реалізації:
- **P1: Функціональний UX** (Критичні покращення взаємодії)
- **P2: Візуальний дизайн** (Естетика та мікро-анімації)
- **P3: Розширена взаємодія** (Комфорт під час адміністрування)

---

### 🚀 P1: Функціональний UX (Критичні покращення)

#### 1. Кнопка "Копіювати в буфер" для приватних посилань
* **Проблема:** Зараз адміністратор має виділяти посилання мишкою або тапом на телефоні, щоб скопіювати його. Це незручно, особливо на мобільних пристроях.
* **Рішення:** Додати кнопку "Копіювати" біля кожного згенерованого посилання в `edit.php` та `albums.php`, яка використовує API `navigator.clipboard`.
* **Реалізація:**

Додати кнопку у верстку списку посилань:
```html
<div class="share-link-row">
    <a class="share-url" href="<?= h($linkUrl) ?>" target="_blank"><?= h($linkUrl) ?></a>
    <button class="button secondary btn-copy" data-copy-text="<?= h($linkUrl) ?>" type="button">Копіювати</button>
</div>
```

Додати обробник у `public/assets/js/main.js`:
```javascript
document.querySelectorAll('.btn-copy').forEach(function (button) {
    button.addEventListener('click', function (event) {
        event.preventDefault();
        var text = button.getAttribute('data-copy-text') || '';
        if (text === '') return;

        navigator.clipboard.writeText(text).then(function () {
            var originalText = button.textContent;
            button.textContent = 'Скопійовано! ✔';
            button.style.borderColor = 'var(--success)';
            button.style.color = 'var(--success)';
            
            setTimeout(function () {
                button.textContent = originalText;
                button.style.borderColor = '';
                button.style.color = '';
            }, 2000);
        }).catch(function (err) {
            console.error('Не вдалося скопіювати:', err);
        });
    });
});
```

#### 2. Навігація стрілками та клавіатурою у Лайтбоксі
* **Проблема:** Зараз для перегляду наступного фото користувач має закрити лайтбокс, клікнути на іншу картку і відкрити її.
* **Рішення:** Зчитати всі елементи з `data-lightbox-src` на поточній сторінці та дозволити перемикання фотографій кнопками "Вліво" / "Вправо" (як на екрані, так і на клавіатурі).
* **Реалізація в `public/assets/js/main.js`:**

Оновити логіку ініціалізації лайтбоксу:
```javascript
var currentLightboxIndex = -1;

// Додати кнопки навігації у структуру лайтбоксу
var lightboxPrev = document.createElement('button');
lightboxPrev.className = 'lightbox-nav-button prev';
lightboxPrev.type = 'button';
lightboxPrev.setAttribute('aria-label', 'Попереднє фото');
lightboxPrev.innerHTML = '&#10094;';

var lightboxNext = document.createElement('button');
lightboxNext.className = 'lightbox-nav-button next';
lightboxNext.type = 'button';
lightboxNext.setAttribute('aria-label', 'Наступне фото');
lightboxNext.innerHTML = '&#10095;';

// Додати кнопки в інтерфейс
lightboxContent.appendChild(lightboxPrev);
lightboxContent.appendChild(lightboxNext);

function navigateLightbox(direction) {
    var nextIndex = currentLightboxIndex + direction;
    if (nextIndex >= 0 && nextIndex < lightboxLinks.length) {
        currentLightboxIndex = nextIndex;
        var link = lightboxLinks[nextIndex];
        var src = link.getAttribute('data-lightbox-src') || '';
        var title = link.getAttribute('data-lightbox-title') || '';
        openLightbox(src, title);
    }
}

// Обробники кліків
lightboxPrev.addEventListener('click', function(e) { e.stopPropagation(); navigateLightbox(-1); });
lightboxNext.addEventListener('click', function(e) { e.stopPropagation(); navigateLightbox(1); });

// Оновлення активного індексу при кліку на фото
lightboxLinks.forEach(function (link, index) {
    link.addEventListener('click', function (event) {
        event.preventDefault();
        currentLightboxIndex = index;
        openLightbox(link.getAttribute('data-lightbox-src'), link.getAttribute('data-lightbox-title'));
    });
});

// Керування клавіатурою (додати в існуючий listen keydown)
document.addEventListener('keydown', function (event) {
    if (lightbox.hidden) return;
    if (event.key === 'ArrowLeft') {
        navigateLightbox(-1);
    } else if (event.key === 'ArrowRight') {
        navigateLightbox(1);
    }
});
```

Додати стилі у `public/assets/css/style.css`:
```css
.lightbox-nav-button {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    z-index: 5;
    width: 50px;
    height: 50px;
    border: 1px solid var(--line);
    background: rgba(24, 24, 24, 0.85);
    color: var(--text);
    font-size: 24px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background-color 0.2s, border-color 0.2s;
}
.lightbox-nav-button:hover {
    background: var(--panel-light);
    border-color: var(--accent);
}
.lightbox-nav-button.prev { left: 16px; }
.lightbox-nav-button.next { right: 16px; }

@media (max-width: 760px) {
    .lightbox-nav-button {
        width: 40px;
        height: 40px;
        font-size: 18px;
    }
}
```

#### 3. Виправлення візуального багу пагінації
* **Проблема:** Тег `<strong>`, який використовується для позначення поточної сторінки, не має внутрішніх відступів (`padding`) та рамок (`border`), які мають посилання `<a>`. Через це пагінація виглядає несиметричною та зміщується при кліку.
* **Рішення:** Зробити так, щоб `strong` мав ті самі розміри та відступи, але відрізнявся кольором фону та рамки.
* **Стилі для `public/assets/css/style.css`:**

```css
/* Уніфікований стиль для елементів пагінації */
.pagination a,
.pagination strong,
.pagination-gap {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 42px;
    height: 42px;
    padding: 0 12px;
    border: 1px solid var(--line);
    text-align: center;
    color: var(--muted);
    font-weight: 500;
}

.pagination strong {
    border-color: var(--accent);
    color: var(--text);
    background: rgba(214, 167, 86, 0.15);
    cursor: default;
}
```

---

### 🎨 P2: Візуальний дизайн (Естетика та мікро-анімації)

#### 4. Сучасна типографіка
* **Проблема:** Стандартний шрифт Arial робить сайт схожим на текстовий документ. Фотопортфоліо виграє від використання геометричного гротеску.
* **Рішення:** Імпортувати та застосувати шрифт **Inter** або **Plus Jakarta Sans** (за замовчуванням використовуючи системні шрифти як fallback).
* **Стилі для `public/assets/css/style.css`:**

```css
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap');

body {
    font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
    letter-spacing: -0.01em;
}

h1, h2, h3, .logo {
    font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
    font-weight: 700;
    letter-spacing: -0.02em;
}
```

#### 5. Округлення кутів (Border Radius) та м'які тіні
* **Проблема:** Абсолютно гострі кути виглядають брутально, що не завжди підходить для художньої галереї. М'які закруглення додають преміальності.
* **Рішення:** Додати змінні радіусу округлення та тіней до дизайн-системи.
* **Стилі для `public/assets/css/style.css`:**

```css
:root {
    /* Додати до існуючих змінних */
    --radius-sm: 4px;
    --radius-md: 8px;
    --radius-lg: 12px;
    --shadow-sm: 0 2px 8px rgba(0, 0, 0, 0.3);
    --shadow-md: 0 8px 24px rgba(0, 0, 0, 0.5);
}

/* Застосування до карток фото */
.photo-card {
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-sm);
    transition: transform 0.2s ease, border-color 0.2s ease, box-shadow 0.2s ease;
}

.photo-card img {
    border-radius: calc(var(--radius-md) - 1px) calc(var(--radius-md) - 1px) 0 0;
}

/* Застосування до кнопок */
.button, button.button {
    border-radius: var(--radius-sm);
    transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease;
}

/* Застосування до форм та панелей */
.form-panel, .exif-panel, .filter-panel, .alert {
    border-radius: var(--radius-lg);
}

input, select, textarea {
    border-radius: var(--radius-sm);
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}
```

#### 6. Плавні мікро-взаємодії (Transitions)
* **Проблема:** При наведенні курсору на посилання, кнопки або фільтри колір змінюється миттєво, створюючи ефект "смикання".
* **Рішення:** Додати плавні переходи (`transition`) для всіх інтерактивних елементів.
* **Стилі для `public/assets/css/style.css`:**

```css
/* Плавний ховер для посилань у меню */
.main-nav a,
.nav-button,
.section-heading a {
    transition: color 0.2s ease, border-color 0.2s ease;
}

/* Покращений ховер для карток галереї */
.photo-card:hover {
    transform: translateY(-4px);
    border-color: var(--accent);
    box-shadow: 0 12px 28px rgba(0, 0, 0, 0.6), 0 0 0 1px rgba(214, 167, 86, 0.2);
}
```

---

### 🌟 P3: Розширена взаємодія (Комфорт використання)

#### 7. Згортання блоку фільтрів (Collapsible Filter Drawer)
* **Проблема:** На сторінці `gallery.php` блок фільтрів містить 7 полів. На мобільних телефонах цей блок повністю перекриває екран, і користувач бачить лише фільтри, а не фото.
* **Рішення:** Сховати фільтри у згорнуту панель за допомогою HTML5-тегу `<details>` та `<summary>`. Це працює без жодного рядка JS і підтримує стан відкритості, якщо фільтри застосовані.
* **Реалізація в `public/gallery.php`:**

Замінити `<form class="filter-panel" ...>` на:
```html
<details class="filter-drawer" <?= $hasFilters ? 'open' : '' ?>>
    <summary class="filter-drawer-summary">
        <span>Налаштування пошуку та фільтрів</span>
        <span class="filter-badge"><?= $hasFilters ? '· Активні' : '' ?></span>
    </summary>
    <form class="filter-panel-inner" method="get" action="<?= h(url('gallery.php')) ?>">
        <!-- Всі поля input/select, які були раніше -->
        <div class="filter-actions">
            <button class="button" type="submit">Застосувати</button>
            <?php if ($hasFilters): ?>
                <a class="button secondary" href="<?= h(url('gallery.php')) ?>">Скинути</a>
            <?php endif; ?>
        </div>
    </form>
</details>
```

Стилі у `public/assets/css/style.css`:
```css
.filter-drawer {
    border: 1px solid var(--line);
    background: var(--panel);
    border-radius: var(--radius-lg);
    margin-bottom: 28px;
    overflow: hidden;
}

.filter-drawer-summary {
    padding: 16px 20px;
    font-weight: 600;
    cursor: pointer;
    user-select: none;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: var(--text);
    transition: background-color 0.2s;
}

.filter-drawer-summary:hover {
    background-color: var(--panel-light);
}

.filter-badge {
    color: var(--accent);
    font-size: 13px;
}

.filter-panel-inner {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 14px;
    padding: 0 20px 20px;
    align-items: end;
}
```

#### 8. Завантаження фото перетягуванням (Drag and Drop Area)
* **Проблема:** Традиційний вибір файлів через кнопку `Browse...` є застарілим для роботи з пакетами фото.
* **Рішення:** Перетворити поле завантаження в інтерактивну зону для перетягування файлів.
* **Реалізація в `public/admin/upload.php`:**

Замінити стандартне поле файлу на:
```html
<label class="file-upload-label">
    JPEG-файли для завантаження
    <div class="drop-zone" id="upload-drop-zone">
        <span class="drop-zone-text">Перетягніть фотографії сюди або натисніть для вибору</span>
        <input type="file" name="photo[]" id="photo-input" accept="image/jpeg" multiple required>
    </div>
</label>
```

Додати стилі в CSS:
```css
.file-upload-label input[type="file"] {
    display: none;
}

.drop-zone {
    border: 2px dashed var(--line);
    border-radius: var(--radius-md);
    padding: 40px 20px;
    text-align: center;
    background: #0b0b0b;
    cursor: pointer;
    margin-top: 8px;
    transition: border-color 0.2s, background-color 0.2s;
}

.drop-zone:hover,
.drop-zone.drag-over {
    border-color: var(--accent);
    background: rgba(214, 167, 86, 0.03);
}

.drop-zone-text {
    font-size: 14px;
    color: var(--muted);
}
```

Додати JS обробник в `public/assets/js/main.js`:
```javascript
var dropZone = document.getElementById('upload-drop-zone');
var fileInput = document.getElementById('photo-input');

if (dropZone && fileInput) {
    dropZone.addEventListener('click', function () {
        fileInput.click();
    });

    dropZone.addEventListener('dragover', function (e) {
        e.preventDefault();
        dropZone.classList.add('drag-over');
    });

    ['dragleave', 'dragend'].forEach(function (type) {
        dropZone.addEventListener(type, function () {
            dropZone.classList.remove('drag-over');
        });
    });

    dropZone.addEventListener('drop', function (e) {
        e.preventDefault();
        dropZone.classList.remove('drag-over');
        
        if (e.dataTransfer.files.length) {
            fileInput.files = e.dataTransfer.files;
            updateDropZoneText();
        }
    });

    fileInput.addEventListener('change', updateDropZoneText);

    function updateDropZoneText() {
        var count = fileInput.files.length;
        var textNode = dropZone.querySelector('.drop-zone-text');
        if (count > 0) {
            textNode.textContent = 'Обрано файлів для завантаження: ' + count;
            textNode.style.color = 'var(--success)';
        } else {
            textNode.textContent = 'Перетягніть фотографії сюди або натисніть для вибору';
            textNode.style.color = '';
        }
    }
}
```

#### 9. Ефектні плейсхолдери до завантаження (Dominant Color Background)
* **Проблема:** Якщо оригінали важать багато, фото завантажуються згори-вниз з затримкою, залишаючи порожні сірі квадрати, що створює "смикання" контенту.
* **Рішення:** Зчитувати середній (домінантний) колір пікселя під час завантаження через PHP GD та записувати в базу. У верстці виводити цей колір у тег `style="background: #color"` для зображення `<img>`. Це створює плавний ефект "матеріалізації" фотографій.
* **Складність:** Середня. Цю функцію додано до `ROADMAP.md` під P2 і вона значно покращує загальний UX сприйняття галереї.

---

## 📈 План реалізації рекомендацій

Для безпечного та послідовного оновлення галереї рекомендується такий порядок дій:

1. **Етап 1: Швидкі виправлення (Quick Wins - P1)**
   - Додати кнопку швидкого копіювання посилань у буфер.
   - Виправити CSS-стилі пагінації (встановити рамки та кольори для `strong`).
   - Синхронізувати стилізацію пагінації в адмінці та публічній частині.

2. **Етап 2: Покращення Лайтбоксу (UX - P1)**
   - Модернізувати JS-скрипт лайтбоксу для підтримки стрілок навігації та клавіш клавіатури.

3. **Етап 3: Редизайн візуального стилю (UI - P2)**
   - Змінити шрифт на Plus Jakarta Sans / Inter.
   - Застосувати скруглення (`border-radius`) та мікро-тіні для карток і панелей.
   - Налаштувати плавні CSS-переходи (`transition`).

4. **Етап 4: Інтерактивність (UX - P3)**
   - Сховати фільтри у компактний згортаємий `<details>`.
   - Впровадити Drag-and-drop зону для завантаження в адмінці.
