document.addEventListener('DOMContentLoaded', function () {
    var selectAllPhotos = document.getElementById('select-all-photos');

    if (selectAllPhotos) {
        selectAllPhotos.addEventListener('change', function () {
            document.querySelectorAll('.photo-checkbox').forEach(function (checkbox) {
                checkbox.checked = selectAllPhotos.checked;
            });
        });
    }

    document.querySelectorAll('form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            var submitter = event.submitter || null;
            var message = form.getAttribute('data-confirm') || (submitter ? submitter.getAttribute('data-confirm') : '') || '';

            if (message === '') {
                return;
            }

            if (!window.confirm(message)) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('img[data-hide-on-error]').forEach(function (img) {
        img.addEventListener('error', function () {
            img.style.opacity = '0';
        }, { once: true });
    });

    // Dominant Color Placeholder
    document.querySelectorAll('img[data-dominant-color]').forEach(function (img) {
        var color = img.getAttribute('data-dominant-color');
        if (color) {
            img.style.backgroundColor = color;
        }
    });

    // 1. Копіювання посилань в буфер обміну
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

    // 2. Drag-and-Drop для завантаження файлів
    var dropZone = document.getElementById('upload-drop-zone');
    var fileInput = document.getElementById('photo-input');

    if (dropZone && fileInput) {
        dropZone.addEventListener('click', function (e) {
            if (e.target === fileInput) {
                return;
            }
            e.preventDefault();
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

        var uploadForm = document.getElementById('upload-form');
        if (uploadForm) {
            uploadForm.addEventListener('submit', function (e) {
                var maxSingle = parseInt(uploadForm.getAttribute('data-max-single-file')) || 0;
                var maxTotal = parseInt(uploadForm.getAttribute('data-max-total-size')) || 0;
                var totalSize = 0;
                var files = fileInput.files;
                
                for (var i = 0; i < files.length; i++) {
                    var size = files[i].size;
                    totalSize += size;
                    if (maxSingle > 0 && size > maxSingle) {
                        e.preventDefault();
                        alert('Файл ' + files[i].name + ' перевищує дозволений розмір.');
                        return;
                    }
                }
                
                if (maxTotal > 0 && totalSize > maxTotal) {
                    e.preventDefault();
                    alert('Загальний розмір файлів перевищує дозволений розмір пакета.');
                    return;
                }
                
                var btn = uploadForm.querySelector('button[type="submit"]');
                if (btn) {
                    btn.dataset.originalText = btn.textContent;
                    btn.textContent = 'Завантаження ' + files.length + ' файлів... Зачекайте';
                    btn.disabled = true;
                    btn.style.opacity = '0.7';
                    btn.style.cursor = 'wait';
                }
                // allow form to submit
            });
        }
    }

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

    var lightboxImageWrap = document.createElement('div');
    lightboxImageWrap.className = 'lightbox-image-wrap';

    var lightboxPicture = document.createElement('picture');
    var lightboxSourceAvif = document.createElement('source');
    lightboxSourceAvif.type = 'image/avif';
    var lightboxSourceWebp = document.createElement('source');
    lightboxSourceWebp.type = 'image/webp';

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
    var currentLightboxIndex = -1;
    var previousLightboxFocus = null;

    lightboxToolbar.appendChild(lightboxZoomOut);
    lightboxToolbar.appendChild(lightboxZoomReset);
    lightboxToolbar.appendChild(lightboxZoomIn);
    lightboxPicture.appendChild(lightboxSourceAvif);
    lightboxPicture.appendChild(lightboxSourceWebp);
    lightboxPicture.appendChild(lightboxImage);
    lightboxImageWrap.appendChild(lightboxPicture);
    lightboxContent.appendChild(lightboxToolbar);
    lightboxContent.appendChild(lightboxClose);
    lightboxContent.appendChild(lightboxPrev);
    lightboxContent.appendChild(lightboxNext);
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
        lightboxSourceAvif.removeAttribute('srcset');
        lightboxSourceWebp.removeAttribute('srcset');
        lightboxImage.removeAttribute('src');
        lightboxImage.alt = '';
        lightboxCaption.textContent = '';
        lightboxPanX = 0;
        lightboxPanY = 0;
        stopLightboxDrag();
        document.body.classList.remove('lightbox-open');

        if (previousLightboxFocus && typeof previousLightboxFocus.focus === 'function') {
            previousLightboxFocus.focus();
        }

        previousLightboxFocus = null;
    }

    function updateLightboxNavButtons() {
        if (currentLightboxIndex <= 0) {
            lightboxPrev.style.display = 'none';
        } else {
            lightboxPrev.style.display = 'flex';
        }

        if (currentLightboxIndex >= lightboxLinks.length - 1) {
            lightboxNext.style.display = 'none';
        } else {
            lightboxNext.style.display = 'flex';
        }
    }

    function navigateLightbox(direction) {
        var nextIndex = currentLightboxIndex + direction;
        if (nextIndex >= 0 && nextIndex < lightboxLinks.length) {
            currentLightboxIndex = nextIndex;
            var link = lightboxLinks[nextIndex];
            var src = link.getAttribute('data-lightbox-src') || '';
            var title = link.getAttribute('data-lightbox-title') || '';
            var srcWebp = link.getAttribute('data-lightbox-src-webp') || '';
            var srcAvif = link.getAttribute('data-lightbox-src-avif') || '';
            openLightbox(src, title, srcWebp, srcAvif);
        }
    }

    function getLightboxFocusableElements() {
        return Array.prototype.slice.call(lightbox.querySelectorAll('a[href], button, input, select, textarea, [tabindex]:not([tabindex="-1"])')).filter(function (element) {
            return !element.disabled && element.getClientRects().length > 0;
        });
    }

    function trapLightboxFocus(event) {
        var focusable = getLightboxFocusableElements();

        if (focusable.length === 0) {
            event.preventDefault();
            lightboxClose.focus();
            return;
        }

        var first = focusable[0];
        var last = focusable[focusable.length - 1];
        var active = document.activeElement;

        if (event.shiftKey && (active === first || !lightbox.contains(active))) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && active === last) {
            event.preventDefault();
            first.focus();
        }
    }

    function openLightbox(src, title, srcWebp, srcAvif) {
        var wasHidden = lightbox.hidden;

        if (wasHidden && document.activeElement && document.activeElement instanceof HTMLElement) {
            previousLightboxFocus = document.activeElement;
        }

        if (srcAvif) {
            lightboxSourceAvif.setAttribute('srcset', srcAvif);
        } else {
            lightboxSourceAvif.removeAttribute('srcset');
        }

        if (srcWebp) {
            lightboxSourceWebp.setAttribute('srcset', srcWebp);
        } else {
            lightboxSourceWebp.removeAttribute('srcset');
        }

        lightboxImage.src = src;
        lightboxImage.alt = title;
        lightboxCaption.textContent = title;
        lightboxPanX = 0;
        lightboxPanY = 0;
        lightboxZoom = 1;
        lightbox.hidden = false;
        document.body.classList.add('lightbox-open');
        updateLightboxNavButtons();
        window.requestAnimationFrame(updateLightboxView);
        lightboxClose.focus();
    }

    lightboxLinks.forEach(function (link, index) {
        link.addEventListener('click', function (event) {
            var src = link.getAttribute('data-lightbox-src') || '';
            var title = link.getAttribute('data-lightbox-title') || '';
            var srcWebp = link.getAttribute('data-lightbox-src-webp') || '';
            var srcAvif = link.getAttribute('data-lightbox-src-avif') || '';

            if (src === '') {
                return;
            }

            event.preventDefault();
            currentLightboxIndex = index;
            openLightbox(src, title, srcWebp, srcAvif);
        });
    });

    lightboxBackdrop.addEventListener('click', closeLightbox);
    lightboxClose.addEventListener('click', closeLightbox);
    lightboxPrev.addEventListener('click', function (event) {
        event.stopPropagation();
        navigateLightbox(-1);
    });
    lightboxNext.addEventListener('click', function (event) {
        event.stopPropagation();
        navigateLightbox(1);
    });
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
        } else if (event.key === 'Tab') {
            trapLightboxFocus(event);
        } else if (event.key === '+' || event.key === '=') {
            event.preventDefault();
            setLightboxZoom(lightboxZoom + lightboxZoomStep);
        } else if (event.key === '-' || event.key === '_') {
            event.preventDefault();
            setLightboxZoom(lightboxZoom - lightboxZoomStep);
        } else if (event.key === '0') {
            event.preventDefault();
            setLightboxZoom(1);
        } else if (event.key === 'ArrowLeft') {
            event.preventDefault();
            navigateLightbox(-1);
        } else if (event.key === 'ArrowRight') {
            event.preventDefault();
            navigateLightbox(1);
        }
    });
});
