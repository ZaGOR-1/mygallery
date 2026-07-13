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
assert_true(release_should_exclude('MYGALLERY_AUDIT.md', false), 'canonical internal audit source should be excluded');
assert_true(release_should_exclude('mygallery99_ai_audit_prompt.md', false), 'arbitrary root audit prompts should be excluded');
assert_true(release_should_exclude('audit-new-policy.md', false), 'arbitrary root audit docs should be excluded by allowlist');
assert_true(release_should_exclude('provirka.md', false), 'provirka.md should be excluded');
assert_true(release_should_exclude('docs/AI_SECURITY_AUDIT.md', false), 'AI audit docs should be excluded');
assert_true(release_should_exclude('docs/AUDIT_PROMPT.md', false), 'audit prompt docs should be excluded');
assert_true(release_should_exclude('docs/SECURITY_AUDIT.md', false), 'security audit docs should be excluded');
assert_true(release_should_exclude('docs/new_internal_notes.md', false), 'unknown docs Markdown should be excluded by production allowlist');

// Sessions
assert_true(release_should_exclude('storage/sessions/sess_123abc', false), 'session files should be excluded');
assert_true(release_should_exclude('sess_123', false), 'root session files should be excluded');
assert_true(release_should_exclude('storage/share_ratelimit/limit_abc.json', false), 'share rate-limit runtime files should be excluded');
assert_true(release_should_exclude('storage/download_locks/abc.lock', false), 'download lock runtime files should be excluded');
assert_true(release_should_exclude('storage/restore_journal.json', false), 'restore journal should be excluded');
assert_true(release_should_exclude('storage/media_maintenance.lock', false), 'media maintenance lock should be excluded');
assert_true(release_should_exclude('public/uploads/.restore-stage-' . str_repeat('a', 32) . '-public-large/photo.jpg', false), 'restore staging should be excluded');
assert_true(release_should_exclude('storage/.restore-old-' . str_repeat('b', 32) . '-storage-originals/photo.jpg', false), 'restore rollback directories should be excluded');

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
assert_true(release_forbidden_reason('mygallery/MYGALLERY_AUDIT.md') !== null, 'canonical internal audit source forbidden');
assert_true(release_forbidden_reason('mygallery/mygallery99_ai_audit_prompt.md') !== null, 'arbitrary root audit prompt forbidden');
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
assert_true(release_forbidden_reason('mygallery/storage/restore_journal.json') !== null, 'restore journal forbidden');
assert_true(release_forbidden_reason('mygallery/storage/media_maintenance.lock') !== null, 'media maintenance lock forbidden');
assert_true(release_forbidden_reason('mygallery/public/uploads/.restore-stage-' . str_repeat('a', 32) . '-public-large/photo.jpg') !== null, 'restore staging forbidden');

assert_true(release_forbidden_reason('mygallery/public/index.php') === null, 'index.php allowed');
assert_true(release_forbidden_reason('mygallery/config/database.example.php') === null, 'database.example.php allowed');
assert_true(release_forbidden_reason('mygallery/docs/BACKUP_RESTORE.md') === null, 'BACKUP_RESTORE.md allowed');
assert_true(release_forbidden_reason('mygallery/docs/BUGS.md') === null, 'BUGS.md allowed');
assert_true(release_forbidden_reason('mygallery/docs/IMPLEMENTED_FEATURES.md') === null, 'IMPLEMENTED_FEATURES.md allowed');
assert_true(release_forbidden_reason('mygallery/storage/share_ratelimit/.gitkeep') === null, 'share_ratelimit/.gitkeep allowed');
assert_true(release_forbidden_reason('mygallery/storage/download_locks/.gitkeep') === null, 'download_locks/.gitkeep allowed');
assert_true(release_forbidden_reason('mygallery/public/uploads/large/.htaccess') === null, 'large/.htaccess allowed');
assert_true(release_forbidden_reason('mygallery/public/uploads/thumbnails/.htaccess') === null, 'thumbnails/.htaccess allowed');

$releaseBuilderSource = (string) file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'build_release.php');
$gitignore = (string) file_get_contents(dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . '.gitignore');
assert_true(str_contains($releaseBuilderSource, "release_verify_zip_streams(\$safeOutput['temporary'], count(\$entries), \$projectName, \$inventory)"), 'release builder must reopen and hash every finished ZIP stream before publish');
assert_false(str_contains($releaseBuilderSource, '$iterator->next();'), 'release exclusion pruning must not skip the next allowed sibling');
assert_true(str_contains($releaseBuilderSource, 'new RecursiveCallbackFilterIterator'), 'release builder must prune excluded directory trees safely');
assert_true(str_contains($releaseBuilderSource, '$item->isLink() || is_link($path)'), 'release builder must reject symlink payloads');
assert_true(str_contains($releaseBuilderSource, 'proc_open('), 'release Git probing must avoid platform-specific shell redirection');
assert_true(str_contains($releaseBuilderSource, "['ls-tree', '-r', '--name-only', '-z', \$sourceCommit]"), 'clean release payload must come from the exact source commit tree');
assert_true(str_contains($releaseBuilderSource, "['ls-files', '-z', '--cached', '--others', '--exclude-standard']"), 'dirty emergency build must include only tracked/non-ignored Git paths');
assert_true(str_contains($releaseBuilderSource, 'SOURCE_DATE_EPOCH'), 'release builder must support reproducible source timestamps');
assert_true(str_contains($releaseBuilderSource, 'usort('), 'release payload order must be canonical');
assert_true(str_contains($releaseBuilderSource, 'release_apply_zip_metadata'), 'release entries must receive canonical timestamps and modes');
assert_true(str_contains($releaseBuilderSource, "'.sha256'"), 'release builder must publish a checksum sidecar');
assert_true(str_contains($releaseBuilderSource, "'.provenance.json'"), 'release builder must publish a provenance sidecar');
assert_true(str_contains($gitignore, 'dist/*.zip.sha256') && str_contains($gitignore, 'dist/*.zip.provenance.json'), 'generated release sidecars must stay outside source status');

function remove_release_test_tree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($iterator as $item) {
        /** @var SplFileInfo $item */
        if ($item->isDir() && !$item->isLink()) {
            @chmod($item->getPathname(), 0700);
            rmdir($item->getPathname());
        } else {
            @chmod($item->getPathname(), 0600);
            unlink($item->getPathname());
        }
    }
    @chmod($path, 0700);
    rmdir($path);
}

$gitPayloadDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mygallery_release_git_' . bin2hex(random_bytes(6));
assert_true(mkdir($gitPayloadDir, 0700), 'release Git inventory test directory should be created');
try {
    assert_true(release_git_output($gitPayloadDir, ['init']) !== null, 'temporary release repository should initialize');
    assert_true(release_git_output($gitPayloadDir, ['config', 'user.email', 'tests@example.invalid']) !== null, 'temporary repository should configure an email');
    assert_true(release_git_output($gitPayloadDir, ['config', 'user.name', 'MyGallery Tests']) !== null, 'temporary repository should configure a user');
    assert_true(file_put_contents($gitPayloadDir . DIRECTORY_SEPARATOR . '.gitignore', ".idea/\n.vscode/\n*.secret\n") !== false, 'release Git ignore fixture should be written');
    assert_true(mkdir($gitPayloadDir . DIRECTORY_SEPARATOR . 'public', 0700), 'tracked source directory should be created');
    assert_true(file_put_contents($gitPayloadDir . DIRECTORY_SEPARATOR . 'public' . DIRECTORY_SEPARATOR . 'index.php', "<?php echo 'ok';\n") !== false, 'tracked source fixture should be written');
    assert_true(release_git_output($gitPayloadDir, ['add', '--', '.']) !== null, 'temporary source should be staged');
    assert_true(release_git_output($gitPayloadDir, ['commit', '-m', 'fixture']) !== null, 'temporary source should be committed');
    $fixtureCommit = release_git_output($gitPayloadDir, ['rev-parse', 'HEAD']);
    assert_true(is_string($fixtureCommit) && $fixtureCommit !== '', 'temporary source commit should be available');

    assert_true(mkdir($gitPayloadDir . DIRECTORY_SEPARATOR . '.idea', 0700), 'ignored IDE directory should be created');
    assert_true(mkdir($gitPayloadDir . DIRECTORY_SEPARATOR . '.vscode', 0700), 'ignored editor directory should be created');
    assert_true(file_put_contents($gitPayloadDir . DIRECTORY_SEPARATOR . '.idea' . DIRECTORY_SEPARATOR . 'workspace.xml', '<secret/>') !== false, 'ignored IDE file should be written');
    assert_true(file_put_contents($gitPayloadDir . DIRECTORY_SEPARATOR . '.vscode' . DIRECTORY_SEPARATOR . 'settings.json', '{}') !== false, 'ignored editor file should be written');
    assert_true(file_put_contents($gitPayloadDir . DIRECTORY_SEPARATOR . 'local.secret', 'credential') !== false, 'custom ignored file should be written');
    assert_true(file_put_contents($gitPayloadDir . DIRECTORY_SEPARATOR . 'Thumbs.db', 'ignored by info exclude') !== false, 'info-exclude fixture should be written');
    assert_true(file_put_contents($gitPayloadDir . DIRECTORY_SEPARATOR . '.git' . DIRECTORY_SEPARATOR . 'info' . DIRECTORY_SEPARATOR . 'exclude', "Thumbs.db\n") !== false, 'Git info exclude fixture should be written');

    $cleanPayload = release_git_payload_paths($gitPayloadDir, (string) $fixtureCommit, false);
    sort($cleanPayload, SORT_STRING);
    assert_equals(['.gitignore', 'public/index.php'], $cleanPayload, 'clean payload must contain only files tracked by the exact source commit');
    $cleanEntries = release_source_entries_from_git_paths($gitPayloadDir, $cleanPayload);
    $cleanEntryPaths = array_values(array_map(
        static fn (array $entry): string => $entry['relative'],
        array_filter($cleanEntries, static fn (array $entry): bool => !$entry['directory'])
    ));
    sort($cleanEntryPaths, SORT_STRING);
    assert_equals(['public/index.php'], $cleanEntryPaths, 'ignored workspace payload must never reach clean release entries');
} finally {
    remove_release_test_tree($gitPayloadDir);
}

$releaseTestDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mygallery_release_verify_' . bin2hex(random_bytes(6));
assert_true(mkdir($releaseTestDir, 0700), 'release verifier test directory should be created');
$releaseZip = $releaseTestDir . DIRECTORY_SEPARATOR . 'release.zip';
$payload = 'verified-release-payload';
$inventory = ['file.txt' => ['sha256' => hash('sha256', $payload), 'size' => strlen($payload)]];
try {
    assert_true(release_path_is_inside_root($releaseTestDir, $releaseTestDir), 'release root must contain itself');
    assert_false(release_path_is_inside_root($releaseTestDir, dirname($releaseTestDir)), 'release root must reject parent paths');
    assert_equals(null, release_git_output($releaseTestDir, ['rev-parse', 'HEAD']), 'Git probe must fail closed outside a repository');
    assert_equals(
        ['public/index.php', '.idea/workspace.xml'],
        release_parse_nul_paths("public/index.php\0.idea/workspace.xml\0"),
        'NUL-delimited Git payload paths must be parsed without filesystem discovery'
    );
    assert_equals(null, release_normalize_git_path('../outside.php'), 'Git payload path traversal must be rejected');
    assert_equals(null, release_normalize_git_path('public\\index.php'), 'Git payload backslashes must be rejected');

    $previousEpoch = getenv('SOURCE_DATE_EPOCH');
    putenv('SOURCE_DATE_EPOCH=1700000001');
    $epochPolicy = release_build_epoch(null);
    assert_equals(1700000000, $epochPolicy['epoch'], 'SOURCE_DATE_EPOCH must be normalized to ZIP two-second precision');
    assert_true($epochPolicy['reproducible'], 'valid SOURCE_DATE_EPOCH must mark metadata reproducible');
    if ($previousEpoch === false) {
        putenv('SOURCE_DATE_EPOCH');
    } else {
        putenv('SOURCE_DATE_EPOCH=' . $previousEpoch);
    }

    $sidecar = $releaseTestDir . DIRECTORY_SEPARATOR . 'release.sha256';
    release_write_sidecar($sidecar, "checksum\n");
    assert_equals("checksum\n", file_get_contents($sidecar), 'release sidecar must be written atomically');
    assert_throws(
        static fn () => release_write_sidecar($sidecar, "overwrite\n"),
        RuntimeException::class,
        'release sidecars must not be overwritten'
    );

    $zip = new ZipArchive();
    assert_true($zip->open($releaseZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 'release verifier fixture should open');
    assert_true($zip->addEmptyDir('mygallery'), 'release verifier root should be added');
    assert_true($zip->addFromString('mygallery/file.txt', $payload), 'release verifier payload should be added');
    assert_true($zip->addFromString('mygallery/BUILD_INFO.json', json_encode([
        'payload_sha256_inventory' => $inventory,
    ], JSON_THROW_ON_ERROR)), 'release verifier BUILD_INFO should be added');
    assert_true($zip->close(), 'release verifier fixture should close');
    release_verify_zip_streams($releaseZip, 3, 'mygallery', $inventory);

    $wrongInventory = $inventory;
    $wrongInventory['file.txt']['sha256'] = str_repeat('0', 64);
    assert_throws(
        static fn () => release_verify_zip_streams($releaseZip, 3, 'mygallery', $wrongInventory),
        RuntimeException::class,
        'release verifier must reject inventory/payload disagreement'
    );
} finally {
    if (isset($sidecar) && is_file($sidecar)) {
        unlink($sidecar);
    }
    if (is_file($releaseZip)) {
        unlink($releaseZip);
    }
    rmdir($releaseTestDir);
}
