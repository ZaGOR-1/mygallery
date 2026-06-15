<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/includes/functions.php';

$token = (string) ($_GET['token'] ?? '');

if ($token === '') {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

$stmt = db()->prepare('SELECT * FROM share_links WHERE token = ?');
$stmt->execute([$token]);
$share = $stmt->fetch();

if (!$share) {
    http_response_code(404);
    require __DIR__ . '/404.php';
    exit;
}

if (!empty($share['expires_at']) && strtotime($share['expires_at']) < time()) {
    http_response_code(404);
    echo "Посилання застаріло.";
    exit;
}

function render_shared_photo(array $photo, string $token, ?int $albumId = null) {
    $pageTitle = $photo['title'];
    $exifRows = normalized_exif_for_display($photo['exif_json'], $photo);
    $photoImageUrl = photo_display_url($photo);
    $photoSrcset = photo_responsive_srcset($photo);

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
            <a href="<?= h($photoImageUrl) ?>" target="_blank">
                <img
                    src="<?= h($photoImageUrl) ?>"
                    <?php if ($photoSrcset !== ''): ?>
                        srcset="<?= h($photoSrcset) ?>"
                        sizes="100vw"
                    <?php endif; ?>
                    alt="<?= h($photo['title']) ?>"
                    class="shared-photo-image"
                >
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
        http_response_code(404);
        echo "Фотографію не знайдено.";
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
            http_response_code(404);
            echo "Фотографію не знайдено в цьому альбомі.";
            exit;
        }
    }

    $stmt = db()->prepare('SELECT * FROM albums WHERE id = ?');
    $stmt->execute([$albumId]);
    $album = $stmt->fetch();

    if (!$album) {
        http_response_code(404);
        echo "Альбом не знайдено.";
        exit;
    }

    $_GET['album_id'] = (string) $albumId;
    $isSharedView = true;
    require __DIR__ . '/gallery.php';
    exit;
}

http_response_code(404);
echo "Некоректне посилання.";
