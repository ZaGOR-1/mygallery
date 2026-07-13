<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/includes/functions.php';

$GLOBALS['mygallery_referrer_policy'] = 'no-referrer';
send_private_cache_headers();

function send_share_noindex_headers(): void
{
    if (!headers_sent()) {
        header('X-Robots-Tag: noindex, noarchive', true);
    }
}

send_share_noindex_headers();
$robotsMeta = 'noindex, noarchive';

enforce_share_request_rate_limit('page');

$token = request_raw_string($_GET, 'token', '', 64);

if (!valid_share_token($token)) {
    $errorStatusCode = 404;
    $errorTitle = 'Посилання не знайдено';
    $errorMessage = 'Такого приватного посилання не існує або його було відкликано.';
    require __DIR__ . '/404.php';
    exit;
}

$share = find_share_link_by_token($token);

if (!$share) {
    $errorStatusCode = 404;
    $errorTitle = 'Посилання не знайдено';
    $errorMessage = 'Такого приватного посилання не існує або його було відкликано.';
    require __DIR__ . '/404.php';
    exit;
}

if (share_link_is_expired($share)) {
    $errorStatusCode = 410;
    $errorTitle = 'Посилання застаріло';
    $errorMessage = 'Термін дії цього приватного посилання закінчився.';
    require __DIR__ . '/404.php';
    exit;
}

function render_shared_photo(array $photo, string $token, ?int $albumId = null) {
    global $robotsMeta;

    $pageTitle = $photo['title'];
    $exifRows = normalized_exif_for_display($photo['exif_json'], $photo);
    $photoImageUrl = photo_display_url($photo, $token);
    $photoSrcset = photo_responsive_srcset($photo, $token);

    require __DIR__ . '/../app/includes/header.php';
    ?>
    <article class="photo-view shared-photo-view">
        <header class="photo-view-header shared-photo-header">
            <h1><?= h($photo['title']) ?></h1>
            <p class="shared-photo-description"><?= h($photo['description'] ?: 'Без опису') ?></p>
            <?php if ($albumId): ?>
                <p><a class="button secondary" href="<?= h(url('share.php?token=' . $token)) ?>">Повернутися до альбому</a></p>
            <?php endif; ?>
        </header>

        <figure class="large-photo shared-photo-figure">
            <a href="<?= h($photoImageUrl) ?>" target="_blank" rel="noopener noreferrer">
                <picture>
                    <?php
                    $avifSrcset = photo_responsive_srcset_next_gen($photo, 'avif', $token);
                    if ($avifSrcset !== ''): ?>
                        <source srcset="<?= h($avifSrcset) ?>" type="image/avif" sizes="100vw">
                    <?php endif; ?>
                    <?php
                    $webpSrcset = photo_responsive_srcset_next_gen($photo, 'webp', $token);
                    if ($webpSrcset !== ''): ?>
                        <source srcset="<?= h($webpSrcset) ?>" type="image/webp" sizes="100vw">
                    <?php endif; ?>
                    <img
                        data-dominant-color="<?= h((string) ($photo['dominant_color'] ?? '')) ?>"
                        src="<?= h($photoImageUrl) ?>"
                        <?php if ($photoSrcset !== ''): ?>
                            srcset="<?= h($photoSrcset) ?>"
                            sizes="100vw"
                        <?php endif; ?>
                        alt="<?= h($photo['title']) ?>"
                        class="shared-photo-image"
                        data-hide-on-error="true"
                    >
                </picture>
            </a>
        </figure>
    </article>
    <?php
    require __DIR__ . '/../app/includes/footer.php';
    exit;
}

if (!empty($share['photo_id'])) {
    $id = (int) $share['photo_id'];
    $photo = fetch_photo_by_id(db(), $id);
    if (!$photo) {
        $errorStatusCode = 404;
        $errorTitle = 'Фотографію не знайдено';
        $errorMessage = 'Можливо, фотографія була видалена або переміщена.';
        require __DIR__ . '/404.php';
        exit;
    }
    render_shared_photo($photo, $token);
}

if (!empty($share['album_id'])) {
    $albumId = (int) $share['album_id'];
    
    $viewPhotoId = get_int('view_photo');
    if ($viewPhotoId) {
        $photo = fetch_photo_by_id(db(), $viewPhotoId);
        if ($photo && (int)$photo['album_id'] === $albumId) {
            render_shared_photo($photo, $token, $albumId);
        } else {
            $errorStatusCode = 404;
            $errorTitle = 'Фотографію не знайдено';
            $errorMessage = 'Ця фотографія не належить до вибраного альбому або була видалена.';
            require __DIR__ . '/404.php';
            exit;
        }
    }

    $stmt = db()->prepare('SELECT * FROM albums WHERE id = ?');
    $stmt->execute([$albumId]);
    $album = $stmt->fetch();

    if (!$album) {
        $errorStatusCode = 404;
        $errorTitle = 'Альбом не знайдено';
        $errorMessage = 'Цей альбом більше не існує або доступ до нього обмежено.';
        require __DIR__ . '/404.php';
        exit;
    }

    $_GET['album_id'] = (string) $albumId;
    $isSharedView = true;
    require __DIR__ . '/gallery.php';
    exit;
}

$errorStatusCode = 400;
$errorTitle = 'Некоректне посилання';
$errorMessage = 'Вказане посилання не містить даних про фотографію або альбом.';
require __DIR__ . '/404.php';
exit;
