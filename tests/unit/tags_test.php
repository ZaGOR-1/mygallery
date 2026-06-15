<?php

declare(strict_types=1);

// parse_tags_input
assert_equals([], parse_tags_input(''), 'Empty string should return empty array');
assert_equals(['tag1'], parse_tags_input('tag1, tag1'), 'Duplicates should be removed');
assert_equals(['hello', 'world'], parse_tags_input('  hello  ,  world  '), 'Spaces should be trimmed');

$longTag = str_repeat('a', 100);
$parsed = parse_tags_input($longTag);
assert_equals(60, mb_strlen($parsed[0]), 'Very long tag should be truncated to 60 chars'); // tag_name_max_length() returns 60

assert_equals(['Київ', 'Україна'], parse_tags_input('Київ, Україна, київ'), 'Ukrainian tags should be handled, duplicates removed case-insensitively');
