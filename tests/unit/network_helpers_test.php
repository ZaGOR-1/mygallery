<?php

declare(strict_types=1);

assert_equals('203.0.113.10', client_ip([
    'REMOTE_ADDR' => '10.0.0.1',
    'HTTP_X_FORWARDED_FOR' => '203.0.113.10, 198.51.100.20',
], ['10.0.0.1']), 'client_ip must use X-Forwarded-For only from trusted proxy');

assert_equals('198.51.100.5', client_ip([
    'REMOTE_ADDR' => '198.51.100.5',
    'HTTP_X_FORWARDED_FOR' => '203.0.113.10',
], ['10.0.0.1']), 'client_ip must ignore X-Forwarded-For from untrusted remote');

assert_true(valid_share_token(str_repeat('a', 32)), 'valid_share_token must accept 32 lowercase hex chars');
assert_false(valid_share_token(str_repeat('g', 32)), 'valid_share_token must reject non-hex chars');
assert_false(valid_share_token(str_repeat('a', 64)), 'valid_share_token must reject long tokens');
