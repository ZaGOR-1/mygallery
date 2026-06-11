document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var message = form.getAttribute('data-confirm') || 'Підтвердити дію?';

            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    var lightboxLinks = document.querySelectorAll('a[data-lightbox-src]');

    if (lightboxLinks.length === 0) {
        return;
    }

    var lightbox = document.createElement('div');
    lightbox.className = 'lightbox';
    lightbox.setAttribute('role', 'dialog');
    lightbox.setAttribute('aria-modal', 'true');
    lightbox.setAttribute('aria-label', 'Перегляд фотографії');
    lightbox.hidden = true;

    var lightboxBackdrop = document.createElement('button');
    lightboxBackdrop.className = 'lightbox-backdrop';
    lightboxBackdrop.type = 'button';
    lightboxBackdrop.setAttribute('aria-label', 'Закрити');

    var lightboxContent = document.createElement('figure');
    lightboxContent.className = 'lightbox-content';

    var lightboxToolbar = document.createElement('div');
    lightboxToolbar.className = 'lightbox-toolbar';

    var lightboxZoomOut = document.createElement('button');
    lightboxZoomOut.className = 'lightbox-zoom-button';
    lightboxZoomOut.type = 'button';
    lightboxZoomOut.setAttribute('aria-label', 'Зменшити масштаб');
    lightboxZoomOut.textContent = '-';

    var lightboxZoomReset = document.createElement('button');
    lightboxZoomReset.className = 'lightbox-zoom-button lightbox-zoom-reset';
    lightboxZoomReset.type = 'button';
    lightboxZoomReset.setAttribute('aria-label', 'Скинути масштаб');
    lightboxZoomReset.textContent = '100%';

    var lightboxZoomIn = document.createElement('button');
    lightboxZoomIn.className = 'lightbox-zoom-button';
    lightboxZoomIn.type = 'button';
    lightboxZoomIn.setAttribute('aria-label', 'Збільшити масштаб');
    lightboxZoomIn.textContent = '+';

    var lightboxClose = document.createElement('button');
    lightboxClose.className = 'lightbox-close';
    lightboxClose.type = 'button';
    lightboxClose.setAttribute('aria-label', 'Закрити');
    lightboxClose.textContent = '×';

    var lightboxImageWrap = document.createElement('div');
    lightboxImageWrap.className = 'lightbox-image-wrap';

    var lightboxImage = document.createElement('img');
    lightboxImage.alt = '';

    var lightboxCaption = document.createElement('figcaption');

    var lightboxZoom = 1;
    var lightboxMinZoom = 1;
    var lightboxMaxZoom = 4;
    var lightboxZoomStep = 0.25;
    var lightboxPanX = 0;
    var lightboxPanY = 0;
    var lightboxIsDragging = false;
    var lightboxDragStartX = 0;
    var lightboxDragStartY = 0;
    var lightboxDragPanStartX = 0;
    var lightboxDragPanStartY = 0;

    lightboxToolbar.appendChild(lightboxZoomOut);
    lightboxToolbar.appendChild(lightboxZoomReset);
    lightboxToolbar.appendChild(lightboxZoomIn);
    lightboxImageWrap.appendChild(lightboxImage);
    lightboxContent.appendChild(lightboxToolbar);
    lightboxContent.appendChild(lightboxClose);
    lightboxContent.appendChild(lightboxImageWrap);
    lightboxContent.appendChild(lightboxCaption);
    lightbox.appendChild(lightboxBackdrop);
    lightbox.appendChild(lightboxContent);
    document.body.appendChild(lightbox);

    function getLightboxFitSize() {
        var wrapWidth = lightboxImageWrap.clientWidth;
        var wrapHeight = lightboxImageWrap.clientHeight;
        var imageWidth = lightboxImage.naturalWidth;
        var imageHeight = lightboxImage.naturalHeight;

        if (wrapWidth <= 0 || wrapHeight <= 0 || imageWidth <= 0 || imageHeight <= 0) {
            return {
                width: 0,
                height: 0,
            };
        }

        var fitRatio = Math.min(wrapWidth / imageWidth, wrapHeight / imageHeight);

        return {
            width: Math.round(imageWidth * fitRatio),
            height: Math.round(imageHeight * fitRatio),
        };
    }

    function getLightboxPanBounds(fitSize) {
        var imageWidth = fitSize.width * lightboxZoom;
        var imageHeight = fitSize.height * lightboxZoom;
        var wrapWidth = lightboxImageWrap.clientWidth;
        var wrapHeight = lightboxImageWrap.clientHeight;

        return {
            x: Math.max(0, (imageWidth - wrapWidth) / 2),
            y: Math.max(0, (imageHeight - wrapHeight) / 2),
        };
    }

    function clampLightboxPan(fitSize) {
        var bounds = getLightboxPanBounds(fitSize);

        lightboxPanX = Math.min(bounds.x, Math.max(-bounds.x, lightboxPanX));
        lightboxPanY = Math.min(bounds.y, Math.max(-bounds.y, lightboxPanY));
    }

    function updateLightboxView() {
        var fitSize = getLightboxFitSize();

        if (fitSize.width > 0 && fitSize.height > 0) {
            lightboxImage.style.setProperty('--lightbox-fit-width', fitSize.width + 'px');
            lightboxImage.style.setProperty('--lightbox-fit-height', fitSize.height + 'px');
        }

        clampLightboxPan(fitSize);
        lightboxImage.style.setProperty('--lightbox-zoom', lightboxZoom.toFixed(2));
        lightboxImage.style.setProperty('--lightbox-pan-x', lightboxPanX.toFixed(0) + 'px');
        lightboxImage.style.setProperty('--lightbox-pan-y', lightboxPanY.toFixed(0) + 'px');
        lightboxZoomReset.textContent = Math.round(lightboxZoom * 100) + '%';
        lightboxZoomOut.disabled = lightboxZoom <= lightboxMinZoom;
        lightboxZoomIn.disabled = lightboxZoom >= lightboxMaxZoom;
        lightboxImageWrap.classList.toggle('is-zoomed', lightboxZoom > 1);
    }

    function setLightboxZoom(nextZoom) {
        var oldZoom = lightboxZoom;

        lightboxZoom = Math.min(lightboxMaxZoom, Math.max(lightboxMinZoom, nextZoom));

        if (lightboxZoom === 1) {
            lightboxPanX = 0;
            lightboxPanY = 0;
        } else if (oldZoom > 0 && oldZoom !== lightboxZoom) {
            lightboxPanX *= lightboxZoom / oldZoom;
            lightboxPanY *= lightboxZoom / oldZoom;
        }

        updateLightboxView();
    }

    function stopLightboxDrag() {
        lightboxIsDragging = false;
        lightboxImageWrap.classList.remove('is-dragging');
    }

    function closeLightbox() {
        lightbox.hidden = true;
        lightboxImage.removeAttribute('src');
        lightboxImage.alt = '';
        lightboxCaption.textContent = '';
        lightboxPanX = 0;
        lightboxPanY = 0;
        stopLightboxDrag();
        document.body.classList.remove('lightbox-open');
    }

    function openLightbox(src, title) {
        lightboxImage.src = src;
        lightboxImage.alt = title;
        lightboxCaption.textContent = title;
        lightboxPanX = 0;
        lightboxPanY = 0;
        lightboxZoom = 1;
        lightbox.hidden = false;
        document.body.classList.add('lightbox-open');
        window.requestAnimationFrame(updateLightboxView);
        lightboxClose.focus();
    }

    lightboxLinks.forEach(function (link) {
        link.addEventListener('click', function (event) {
            var src = link.getAttribute('data-lightbox-src') || '';
            var title = link.getAttribute('data-lightbox-title') || '';

            if (src === '') {
                return;
            }

            event.preventDefault();
            openLightbox(src, title);
        });
    });

    lightboxBackdrop.addEventListener('click', closeLightbox);
    lightboxClose.addEventListener('click', closeLightbox);
    lightboxZoomOut.addEventListener('click', function () {
        setLightboxZoom(lightboxZoom - lightboxZoomStep);
    });
    lightboxZoomReset.addEventListener('click', function () {
        setLightboxZoom(1);
    });
    lightboxZoomIn.addEventListener('click', function () {
        setLightboxZoom(lightboxZoom + lightboxZoomStep);
    });
    lightboxImageWrap.addEventListener('wheel', function (event) {
        if (lightbox.hidden) {
            return;
        }

        event.preventDefault();

        if (event.deltaY < 0) {
            setLightboxZoom(lightboxZoom + 0.1);
        } else if (event.deltaY > 0) {
            setLightboxZoom(lightboxZoom - 0.1);
        }
    });
    lightboxImageWrap.addEventListener('pointerdown', function (event) {
        if (lightboxZoom <= 1 || event.button !== 0) {
            return;
        }

        lightboxIsDragging = true;
        lightboxDragStartX = event.clientX;
        lightboxDragStartY = event.clientY;
        lightboxDragPanStartX = lightboxPanX;
        lightboxDragPanStartY = lightboxPanY;
        lightboxImageWrap.classList.add('is-dragging');
        lightboxImageWrap.setPointerCapture(event.pointerId);
        event.preventDefault();
    });
    lightboxImageWrap.addEventListener('pointermove', function (event) {
        if (!lightboxIsDragging) {
            return;
        }

        lightboxPanX = lightboxDragPanStartX + event.clientX - lightboxDragStartX;
        lightboxPanY = lightboxDragPanStartY + event.clientY - lightboxDragStartY;
        updateLightboxView();
    });
    lightboxImageWrap.addEventListener('pointerup', stopLightboxDrag);
    lightboxImageWrap.addEventListener('pointercancel', stopLightboxDrag);
    lightboxImage.addEventListener('dragstart', function (event) {
        event.preventDefault();
    });
    lightboxImage.addEventListener('load', updateLightboxView);
    window.addEventListener('resize', function () {
        if (!lightbox.hidden) {
            updateLightboxView();
        }
    });

    document.addEventListener('keydown', function (event) {
        if (lightbox.hidden) {
            return;
        }

        if (event.key === 'Escape') {
            closeLightbox();
        } else if (event.key === '+' || event.key === '=') {
            event.preventDefault();
            setLightboxZoom(lightboxZoom + lightboxZoomStep);
        } else if (event.key === '-' || event.key === '_') {
            event.preventDefault();
            setLightboxZoom(lightboxZoom - lightboxZoomStep);
        } else if (event.key === '0') {
            event.preventDefault();
            setLightboxZoom(1);
        }
    });
});
