<?php

declare(strict_types=1);

function fulltext_index_exists(string $indexName): bool
{
    static $cache = [];

    if (array_key_exists($indexName, $cache)) {
        return $cache[$indexName];
    }

    try {
        $stmt = db()->prepare(
            "SELECT COUNT(*)
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = 'photos'
              AND INDEX_NAME = :index_name
              AND INDEX_TYPE = 'FULLTEXT'"
        );
        $stmt->execute(['index_name' => $indexName]);
        $cache[$indexName] = (int) $stmt->fetchColumn() > 0;
    } catch (Throwable $exception) {
        app_log_exception($exception, 'Fulltext index check failed');
        $cache[$indexName] = false;
    }

    return $cache[$indexName];
}

function fulltext_boolean_query(string $search): string
{
    // MySQL boolean FULLTEXT treats operator characters specially, so only plain
    // words are accepted and the safe boolean prefix/wildcard syntax is added here.
    preg_match_all('/[\p{L}\p{N}_]+/u', $search, $matches);
    $terms = array_slice(array_values(array_unique(array_filter(
        $matches[0] ?? [],
        static fn (string $term): bool => mb_strlen($term, 'UTF-8') >= 2
    ))), 0, 8);

    if (empty($terms)) {
        return '';
    }

    return implode(' ', array_map(static fn (string $term): string => '+' . $term . '*', $terms));
}

function photo_search_condition(string $search, bool $includeOriginalName, array &$params): string
{
    if ($search === '') {
        return '';
    }

    $fulltextIndex = $includeOriginalName ? 'idx_photos_admin_search_fulltext' : 'idx_photos_public_search_fulltext';
    $fulltextQuery = fulltext_boolean_query($search);

    if ($fulltextQuery !== '' && fulltext_index_exists($fulltextIndex)) {
        $params['search_fulltext'] = $fulltextQuery;
        $params['search_title'] = '%' . $search . '%';
        $params['search_description'] = '%' . $search . '%';

        if ($includeOriginalName) {
            $params['search_original'] = '%' . $search . '%';

            return '(MATCH(photos.title, photos.description, photos.original_name) AGAINST (:search_fulltext IN BOOLEAN MODE)
                OR photos.title LIKE :search_title
                OR photos.description LIKE :search_description
                OR photos.original_name LIKE :search_original)';
        }

        return '(MATCH(photos.title, photos.description) AGAINST (:search_fulltext IN BOOLEAN MODE)
            OR photos.title LIKE :search_title
            OR photos.description LIKE :search_description)';
    }

    $params['search_title'] = '%' . $search . '%';
    $params['search_description'] = '%' . $search . '%';

    if ($includeOriginalName) {
        $params['search_original'] = '%' . $search . '%';

        return '(photos.title LIKE :search_title OR photos.description LIKE :search_description OR photos.original_name LIKE :search_original)';
    }

    return '(photos.title LIKE :search_title OR photos.description LIKE :search_description)';
}
