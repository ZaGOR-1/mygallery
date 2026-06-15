<?php

declare(strict_types=1);

define('TESTING_RELEASE_EXCLUSIONS', true);
require_once dirname(__DIR__, 2) . '/tools/build_release.php';

// release_should_exclude

assert_true(release_should_exclude('.git/config', false), '.git should be excluded');
assert_true(release_should_exclude('config/database.php', false), 'database.php should be excluded');
assert_true(release_should_exclude('.env', false), '.env should be excluded');
assert_true(release_should_exclude('temp_migrate.php', false), 'temp_migrate.php should be excluded');
assert_true(release_should_exclude('temp_anything.php', false), 'temp_*.php should be excluded');

// Sessions
assert_true(release_should_exclude('storage/sessions/sess_123abc', false), 'session files should be excluded');
assert_true(release_should_exclude('sess_123', false), 'root session files should be excluded');

// Logs and archives
assert_true(release_should_exclude('storage/logs/error.log', false), 'logs should be excluded');
assert_true(release_should_exclude('mygallery_backup.zip', false), 'zip files should be excluded');

// Uploads and originals
assert_true(release_should_exclude('storage/originals/photo.jpg', false), 'original photos should be excluded');
assert_true(release_should_exclude('public/uploads/large/photo.jpg', false), 'large photos should be excluded');
assert_true(release_should_exclude('public/uploads/thumbnails/photo.jpg', false), 'thumbnail photos should be excluded');

// Allowed files
assert_false(release_should_exclude('config/database.example.php', false), 'database.example.php should NOT be excluded');
assert_false(release_should_exclude('public/index.php', false), 'index.php should NOT be excluded');
assert_false(release_should_exclude('public/assets/css/style.css', false), 'css files should NOT be excluded');
assert_false(release_should_exclude('storage/originals/.gitkeep', false), '.gitkeep in originals should NOT be excluded');
assert_false(release_should_exclude('public/uploads/originals/.htaccess', false), '.htaccess in originals should NOT be excluded');

// release_forbidden_reason (secondary safety check)
assert_true(release_forbidden_reason('mygallery/config/database.php') !== null, 'database.php forbidden');
assert_true(release_forbidden_reason('mygallery/.env') !== null, '.env forbidden');
assert_true(release_forbidden_reason('mygallery/temp_migrate.php') !== null, 'temp_migrate.php forbidden');
assert_true(release_forbidden_reason('mygallery/public/uploads/large/test.jpg') !== null, 'large/test.jpg forbidden');
assert_true(release_forbidden_reason('mygallery/storage/originals/test.jpg') !== null, 'storage/originals/test.jpg forbidden');

assert_true(release_forbidden_reason('mygallery/public/index.php') === null, 'index.php allowed');
assert_true(release_forbidden_reason('mygallery/config/database.example.php') === null, 'database.example.php allowed');
