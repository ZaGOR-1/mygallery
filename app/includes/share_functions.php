<?php

declare(strict_types=1);

function valid_share_token(string $token): bool
{
    return preg_match('/\A[a-f0-9]{32}\z/', $token) === 1;
}

function find_share_link_by_token(string $token): ?array
{
    $stmt = db()->prepare('SELECT * FROM share_links WHERE token = ? LIMIT 1');
    $stmt->execute([$token]);
    $share = $stmt->fetch();

    return is_array($share) ? $share : null;
}

function share_link_is_expired(array $share, ?int $now = null): bool
{
    if (empty($share['expires_at'])) {
        return false;
    }

    $expiresAt = strtotime((string) $share['expires_at']);

    return $expiresAt === false || $expiresAt < ($now ?? time());
}
