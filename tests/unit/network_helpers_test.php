<?php

declare(strict_types=1);

assert_equals('203.0.113.10', client_ip([
    'REMOTE_ADDR' => '10.0.0.1',
    'HTTP_X_FORWARDED_FOR' => '203.0.113.10',
], ['10.0.0.1']), 'client_ip must use X-Forwarded-For only from trusted proxy');

assert_equals('198.51.100.20', client_ip([
    'REMOTE_ADDR' => '10.0.0.1',
    'HTTP_X_FORWARDED_FOR' => '203.0.113.99, 198.51.100.20',
], ['10.0.0.1']), 'client_ip must ignore a spoofed leftmost XFF value when the proxy appends');

assert_equals('203.0.113.10', client_ip([
    'REMOTE_ADDR' => '10.0.0.2',
    'HTTP_X_FORWARDED_FOR' => '203.0.113.10, 10.0.0.1',
], ['10.0.0.1', '10.0.0.2']), 'client_ip must discard multiple trusted proxy hops from right to left');

assert_equals('198.51.100.5', client_ip([
    'REMOTE_ADDR' => '198.51.100.5',
    'HTTP_X_FORWARDED_FOR' => '203.0.113.10',
], ['10.0.0.1']), 'client_ip must ignore X-Forwarded-For from untrusted remote');

assert_equals('2001:db8::1', canonical_ip_address('2001:0DB8:0000:0000:0000:0000:0000:0001'), 'IPv6 expanded/case variants must canonicalize');
assert_equals('2001:db8::1', client_ip([
    'REMOTE_ADDR' => '2001:0DB8:0:0:0:0:0:FF',
    'HTTP_X_FORWARDED_FOR' => '2001:0DB8:0000:0000:0000:0000:0000:0001',
], ['2001:db8::ff']), 'trusted proxy and effective client IPv6 aliases must share canonical forms');
assert_equals(null, canonical_ip_address('not-an-ip'), 'invalid addresses must not produce limiter identities');

assert_true(valid_share_token(str_repeat('a', 32)), 'valid_share_token must accept 32 lowercase hex chars');
assert_false(valid_share_token(str_repeat('g', 32)), 'valid_share_token must reject non-hex chars');
assert_false(valid_share_token(str_repeat('a', 64)), 'valid_share_token must reject long tokens');
