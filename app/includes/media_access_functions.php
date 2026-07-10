<?php

declare(strict_types=1);

function media_not_found(): never
{
    http_response_code(404);
    send_security_headers();
    exit;
}

function media_share_token_allows_photo(string $token, array $photo): bool
{
    if (!valid_share_token($token)) {
        return false;
    }

    try {
        $share = find_share_link_by_token($token);
    } catch (Throwable $exception) {
        app_log_exception($exception, 'Media share token lookup failed');

        return false;
    }

    if ($share === null || share_link_is_expired($share)) {
        return false;
    }

    $photoId = (int) ($photo['id'] ?? 0);
    $albumId = (int) ($photo['album_id'] ?? 0);

    if (!empty($share['photo_id']) && (int) $share['photo_id'] === $photoId) {
        return true;
    }

    return !empty($share['album_id']) && $albumId > 0 && (int) $share['album_id'] === $albumId;
}

function media_file_for_photo(array $photo, string $variant, string $format): ?string
{
    $folder = $variant === 'thumbnail' ? 'thumbnails' : 'large';
    $filename = $variant === 'thumbnail'
        ? (string) ($photo['thumbnail_filename'] ?? '')
        : (string) ($photo['filename'] ?? '');

    if ($filename === '') {
        return null;
    }

    if ($format !== 'jpg') {
        $filename = (string) preg_replace('/\.jpe?g$/i', '.' . $format, $filename);
    }

    return safe_existing_upload_file_path($folder, $filename);
}
