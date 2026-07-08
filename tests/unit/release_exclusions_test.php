<?php

declare(strict_types=1);

define('TESTING_RELEASE_EXCLUSIONS', true);
require_once dirname(__DIR__, 2) . '/tools/build_release.php';

// release_should_exclude

assert_true(release_should_exclude('.git/config', false), '.git should be excluded');
assert_true(release_should_exclude('.agents/config.json', false), '.agents should be excluded');
assert_true(release_should_exclude('.gemini/settings.example.json', false), '.gemini should be excluded');
assert_true(release_should_exclude('.github/workflows/build_release.yml', false), '.github should be excluded');
assert_true(release_should_exclude('config/database.php', false), 'database.php should be excluded');
assert_true(release_should_exclude('.env', false), '.env should be excluded');
assert_true(release_should_exclude('temp_migrate.php', false), 'temp_migrate.php should be excluded');
assert_true(release_should_exclude('temp_anything.php', false), 'temp_*.php should be excluded');
assert_true(release_should_exclude('AGENTS.md', false), 'AGENTS.md should be excluded');
assert_true(release_should_exclude('CLAUDE.md', false), 'CLAUDE.md should be excluded');
assert_true(release_should_exclude('GEMINI.md', false), 'GEMINI.md should be excluded');
assert_true(release_should_exclude('audit.md', false), 'audit.md should be excluded');
assert_true(release_should_exclude('FULL_PROJECT_AUDIT.md', false), 'FULL_PROJECT_AUDIT.md should be excluded');
assert_true(release_should_exclude('provirka.md', false), 'provirka.md should be excluded');
assert_true(release_should_exclude('docs/AI_SECURITY_AUDIT.md', false), 'AI audit docs should be excluded');
assert_true(release_should_exclude('docs/AUDIT_PROMPT.md', false), 'audit prompt docs should be excluded');
assert_true(release_should_exclude('docs/SECURITY_AUDIT.md', false), 'security audit docs should be excluded');

// Sessions
assert_true(release_should_exclude('storage/sessions/sess_123abc', false), 'session files should be excluded');
assert_true(release_should_exclude('sess_123', false), 'root session files should be excluded');
assert_true(release_should_exclude('storage/share_ratelimit/limit_abc.json', false), 'share rate-limit runtime files should be excluded');
assert_true(release_should_exclude('storage/download_locks/abc.lock', false), 'download lock runtime files should be excluded');

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
assert_false(release_should_exclude('docs/BACKUP_RESTORE.md', false), 'operational docs should NOT be excluded');
assert_false(release_should_exclude('docs/BUGS.md', false), 'known limitations docs should NOT be excluded');
assert_false(release_should_exclude('docs/IMPLEMENTED_FEATURES.md', false), 'implemented features docs should NOT be excluded');
assert_false(release_should_exclude('storage/originals/.gitkeep', false), '.gitkeep in originals should NOT be excluded');
assert_false(release_should_exclude('storage/share_ratelimit/.gitkeep', false), '.gitkeep in share_ratelimit should NOT be excluded');
assert_false(release_should_exclude('storage/download_locks/.gitkeep', false), '.gitkeep in download_locks should NOT be excluded');
assert_false(release_should_exclude('public/uploads/originals/.htaccess', false), '.htaccess in originals should NOT be excluded');
assert_false(release_should_exclude('public/uploads/large/.htaccess', false), '.htaccess in large should NOT be excluded');
assert_false(release_should_exclude('public/uploads/thumbnails/.htaccess', false), '.htaccess in thumbnails should NOT be excluded');

// release_forbidden_reason (secondary safety check)
assert_true(release_forbidden_reason('mygallery/config/database.php') !== null, 'database.php forbidden');
assert_true(release_forbidden_reason('mygallery/.agents/config.json') !== null, '.agents forbidden');
assert_true(release_forbidden_reason('mygallery/.gemini/settings.example.json') !== null, '.gemini forbidden');
assert_true(release_forbidden_reason('mygallery/.github/workflows/build_release.yml') !== null, '.github forbidden');
assert_true(release_forbidden_reason('mygallery/AGENTS.md') !== null, 'AGENTS.md forbidden');
assert_true(release_forbidden_reason('mygallery/audit.md') !== null, 'audit.md forbidden');
assert_true(release_forbidden_reason('mygallery/FULL_PROJECT_AUDIT.md') !== null, 'FULL_PROJECT_AUDIT.md forbidden');
assert_true(release_forbidden_reason('mygallery/provirka.md') !== null, 'provirka.md forbidden');
assert_true(release_forbidden_reason('mygallery/docs/AI_RELEASE_AUDIT.md') !== null, 'AI release audit doc forbidden');
assert_true(release_forbidden_reason('mygallery/docs/AUDIT_REPORT.md') !== null, 'audit report doc forbidden');
assert_true(release_forbidden_reason('mygallery/docs/SECURITY_AUDIT.md') !== null, 'security audit doc forbidden');
assert_true(release_forbidden_reason('mygallery/.env') !== null, '.env forbidden');
assert_true(release_forbidden_reason('mygallery/temp_migrate.php') !== null, 'temp_migrate.php forbidden');
assert_true(release_forbidden_reason('mygallery/public/uploads/large/test.jpg') !== null, 'large/test.jpg forbidden');
assert_true(release_forbidden_reason('mygallery/storage/originals/test.jpg') !== null, 'storage/originals/test.jpg forbidden');
assert_true(release_forbidden_reason('mygallery/storage/share_ratelimit/limit_abc.json') !== null, 'share_ratelimit files forbidden');
assert_true(release_forbidden_reason('mygallery/storage/download_locks/abc.lock') !== null, 'download_locks files forbidden');

assert_true(release_forbidden_reason('mygallery/public/index.php') === null, 'index.php allowed');
assert_true(release_forbidden_reason('mygallery/config/database.example.php') === null, 'database.example.php allowed');
assert_true(release_forbidden_reason('mygallery/docs/BACKUP_RESTORE.md') === null, 'BACKUP_RESTORE.md allowed');
assert_true(release_forbidden_reason('mygallery/docs/BUGS.md') === null, 'BUGS.md allowed');
assert_true(release_forbidden_reason('mygallery/docs/IMPLEMENTED_FEATURES.md') === null, 'IMPLEMENTED_FEATURES.md allowed');
assert_true(release_forbidden_reason('mygallery/storage/share_ratelimit/.gitkeep') === null, 'share_ratelimit/.gitkeep allowed');
assert_true(release_forbidden_reason('mygallery/storage/download_locks/.gitkeep') === null, 'download_locks/.gitkeep allowed');
assert_true(release_forbidden_reason('mygallery/public/uploads/large/.htaccess') === null, 'large/.htaccess allowed');
assert_true(release_forbidden_reason('mygallery/public/uploads/thumbnails/.htaccess') === null, 'thumbnails/.htaccess allowed');
