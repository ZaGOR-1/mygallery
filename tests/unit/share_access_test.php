<?php

declare(strict_types=1);

assert_true(valid_share_token(str_repeat('a', 32)), '32 lowercase hex characters must be a valid share token');
assert_false(valid_share_token(str_repeat('A', 32)), 'uppercase tokens must not be accepted');
assert_false(valid_share_token(str_repeat('a', 31)), 'short tokens must not be accepted');
assert_false(share_link_is_expired(['expires_at' => null], 1000), 'share links without expiry must remain active');
assert_false(share_link_is_expired(['expires_at' => '1970-01-01 00:20:00'], 1000), 'future share expiry must remain active');
assert_true(share_link_is_expired(['expires_at' => '1970-01-01 00:10:00'], 1000), 'past share expiry must be rejected');
assert_true(share_link_is_expired(['expires_at' => 'not-a-date'], 1000), 'invalid stored expiry must fail closed');
