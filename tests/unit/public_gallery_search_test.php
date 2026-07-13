<?php

declare(strict_types=1);

$galleryController = (string) file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'gallery.php');
$galleryFunctions = (string) file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'gallery_functions.php');

assert_true(
    str_contains($galleryController, '$includeOriginalName = $isSharedView;'),
    'Public gallery must enable original-name search only for token-based shared views'
);

assert_true(
    str_contains($galleryController, 'count_photos(db(), $filters, $includeOriginalName, $isSharedView)'),
    'Public gallery count query must use the explicit original-name search flag'
);

assert_true(
    str_contains($galleryController, 'fetch_photos(db(), $filters, $perPage, $offset, $includeOriginalName, $isSharedView)'),
    'Public gallery fetch query must use the explicit original-name search flag'
);

assert_true(
    str_contains($galleryFunctions, 'MATCH(photos.title, photos.description, photos.original_name)')
        && str_contains($galleryFunctions, "if (\$fulltextQuery !== '' && fulltext_index_exists(\$fulltextIndex))"),
    'Original-name search must use the dedicated FULLTEXT index when it exists'
);
assert_false(
    str_contains($galleryFunctions, 'AGAINST (:search_fulltext IN BOOLEAN MODE)\n                OR photos.title LIKE'),
    'FULLTEXT search must not be combined with a leading-wildcard LIKE scan'
);
assert_true(
    str_contains($galleryFunctions, 'photos.original_name LIKE :search_original'),
    'Original-name search must retain LIKE only as the no-index/unsupported-query fallback'
);
