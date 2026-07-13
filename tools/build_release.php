<?php

declare(strict_types=1);

require_once dirname(__DIR__) . DIRECTORY_SEPARATOR . 'app' . DIRECTORY_SEPARATOR . 'includes' . DIRECTORY_SEPARATOR . 'functions.php';
require_once __DIR__ . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'SafeCliZipOutput.php';

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Цей файл запускається тільки з консолі.');
}

$root = dirname(__DIR__);
$projectName = 'mygallery';
$version = trim((string) (@file_get_contents($root . DIRECTORY_SEPARATOR . 'VERSION') ?: 'v5'));
$versionSlug = preg_replace('/[^a-zA-Z0-9_.-]+/', '_', strtolower($version)) ?: 'v5';
$distDir = $root . DIRECTORY_SEPARATOR . 'dist';
$output = $distDir . DIRECTORY_SEPARATOR . 'mygallery_' . $versionSlug . '_release.zip';

$options = getopt('', ['output:', 'allow-dirty', 'help']);
if (isset($options['help'])) {
    echo "Usage:\n";
    echo "  php tools/build_release.php\n";
    echo "  php tools/build_release.php --output=/path/to/mygallery_release.zip\n";
    echo "  php tools/build_release.php --allow-dirty   # local emergency build only\n";
    exit(0);
}

if (isset($options['output']) && is_string($options['output']) && $options['output'] !== '') {
    $output = $options['output'];
}


/** @param list<string> $arguments */
function release_git_output(string $root, array $arguments, bool $trim = true): ?string
{
    $process = @proc_open(
        array_merge(['git', '-C', $root], $arguments),
        [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ],
        $pipes,
        $root
    );
    if (!is_resource($process)) {
        return null;
    }

    fclose($pipes[0]);
    $stdout = stream_get_contents($pipes[1]);
    $stderr = stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $status = proc_close($process);

    if ($status !== 0 || !is_string($stdout)) {
        return null;
    }

    return $trim ? trim($stdout) : $stdout;
}

/** @return list<string> */
function release_parse_nul_paths(string $payload): array
{
    $paths = [];
    foreach (explode("\0", $payload) as $path) {
        if ($path !== '') {
            $paths[] = $path;
        }
    }

    return $paths;
}

/** @return list<string> */
function release_git_payload_paths(string $root, string $sourceCommit, bool $sourceDirty): array
{
    $arguments = $sourceDirty
        ? ['ls-files', '-z', '--cached', '--others', '--exclude-standard']
        : ['ls-tree', '-r', '--name-only', '-z', $sourceCommit];
    $payload = release_git_output($root, $arguments, false);
    if (!is_string($payload)) {
        throw new RuntimeException('Release build не зміг отримати Git-bound payload inventory.');
    }

    return release_parse_nul_paths($payload);
}

function release_path_is_inside_root(string $root, string $path): bool
{
    $realRoot = realpath($root);
    $realPath = realpath($path);
    if ($realRoot === false || $realPath === false) {
        return false;
    }
    $normalize = static fn (string $value): string => str_replace('\\', '/', rtrim($value, '/\\'));
    $realRoot = $normalize($realRoot);
    $realPath = $normalize($realPath);
    if (PHP_OS_FAMILY === 'Windows') {
        $realRoot = strtolower($realRoot);
        $realPath = strtolower($realPath);
    }

    return $realPath === $realRoot || str_starts_with($realPath, $realRoot . '/');
}

/** @return array{epoch:int,reproducible:bool} */
function release_build_epoch(?string $gitCommitEpoch): array
{
    $configured = getenv('SOURCE_DATE_EPOCH');
    $candidate = is_string($configured) && preg_match('/\A[0-9]+\z/', $configured) === 1
        ? $configured
        : $gitCommitEpoch;
    $reproducible = is_string($candidate) && preg_match('/\A[0-9]+\z/', $candidate) === 1;
    $epoch = $reproducible ? (int) $candidate : time();

    // Classic ZIP timestamps are bounded to 1980..2107 and have two-second precision.
    $epoch = max(315532800, min(4354819198, $epoch));
    $epoch -= $epoch % 2;

    return ['epoch' => $epoch, 'reproducible' => $reproducible];
}

function release_apply_zip_metadata(ZipArchive $zip, string $entry, int $epoch, bool $directory): void
{
    if (!$zip->setMtimeName($entry, $epoch)) {
        throw new RuntimeException('Не вдалося встановити canonical ZIP timestamp: ' . $entry);
    }

    $mode = $directory ? 040750 : 0100640;
    $attributes = ($mode << 16) | ($directory ? 0x10 : 0);
    if (!$zip->setExternalAttributesName($entry, ZipArchive::OPSYS_UNIX, $attributes)) {
        throw new RuntimeException('Не вдалося встановити canonical ZIP mode: ' . $entry);
    }
}

function release_write_sidecar(string $path, string $contents): void
{
    if (file_exists($path) || is_link($path)) {
        throw new RuntimeException('Release sidecar уже існує або є symlink: ' . $path);
    }
    $directory = realpath(dirname($path));
    if ($directory === false || !is_dir($directory)) {
        throw new RuntimeException('Release sidecar directory не існує.');
    }
    $temporary = tempnam($directory, '.mygallery_release_sidecar_');
    if ($temporary === false || is_link($temporary)) {
        throw new RuntimeException('Не вдалося створити temporary release sidecar.');
    }

    try {
        $written = file_put_contents($temporary, $contents, LOCK_EX);
        if ($written !== strlen($contents)) {
            throw new RuntimeException('Не вдалося повністю записати release sidecar.');
        }
        if (PHP_OS_FAMILY !== 'Windows' && !chmod($temporary, 0640)) {
            throw new RuntimeException('Не вдалося встановити права release sidecar.');
        }
        if (file_exists($path) || is_link($path) || !rename($temporary, $path)) {
            throw new RuntimeException('Не вдалося атомарно опублікувати release sidecar.');
        }
    } finally {
        if (is_file($temporary)) {
            @unlink($temporary);
        }
    }
}

function release_relative_path(string $root, string $path): string
{
    $relative = substr($path, strlen($root));
    $relative = str_replace('\\', '/', $relative);

    return trim($relative, '/');
}

function release_normalize_git_path(string $relative): ?string
{
    if ($relative === '' || str_contains($relative, "\0") || str_contains($relative, '\\')
        || str_starts_with($relative, '/') || preg_match('/\A[A-Za-z]:/', $relative) === 1) {
        return null;
    }

    $parts = explode('/', $relative);
    foreach ($parts as $part) {
        if ($part === '' || $part === '.' || $part === '..') {
            return null;
        }
    }

    return implode('/', $parts);
}

/**
 * @param list<string> $relativePaths
 * @return list<array{path:string,relative:string,directory:bool}>
 */
function release_source_entries_from_git_paths(string $root, array $relativePaths, bool $allowMissing = false): array
{
    $files = [];
    $directories = [];
    foreach ($relativePaths as $relativePath) {
        $relative = release_normalize_git_path($relativePath);
        if ($relative === null) {
            throw new RuntimeException('Release build blocked: Git повернув unsafe payload path.');
        }
        if (release_should_exclude($relative, false)) {
            continue;
        }

        $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (!is_file($path)) {
            if ($allowMissing && !file_exists($path) && !is_link($path)) {
                continue;
            }
            throw new RuntimeException('Release build blocked: Git payload file відсутній: ' . $relative);
        }
        if (is_link($path) || !release_path_is_inside_root($root, $path)) {
            throw new RuntimeException('Release build blocked: symlink/junction або path escape: ' . $relative);
        }
        $reason = release_forbidden_reason('mygallery/' . $relative);
        if ($reason !== null) {
            throw new RuntimeException('Release build blocked: ' . $reason);
        }
        $files[$relative] = ['path' => $path, 'relative' => $relative, 'directory' => false];

        $parent = str_contains($relative, '/') ? dirname($relative) : '';
        while ($parent !== '' && $parent !== '.') {
            $parent = str_replace('\\', '/', $parent);
            if (!release_should_exclude($parent, true)) {
                $directories[$parent] = true;
            }
            $parent = str_contains($parent, '/') ? dirname($parent) : '';
        }
    }

    $entries = [];
    foreach (array_keys($directories) as $relative) {
        $path = $root . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relative);
        if (!is_dir($path) || is_link($path) || !release_path_is_inside_root($root, $path)) {
            throw new RuntimeException('Release build blocked: unsafe Git payload directory: ' . $relative);
        }
        $entries[] = ['path' => $path, 'relative' => $relative, 'directory' => true];
    }
    foreach ($files as $entry) {
        $entries[] = $entry;
    }

    return $entries;
}

function release_internal_artifact_reason(string $relative): ?string
{
    $relative = trim(str_replace('\\', '/', $relative), '/');
    $rootProductionDocs = ['README.md', 'CHANGELOG.md', 'ROADMAP.md'];
    $docsProductionDocs = ['docs/BACKUP_RESTORE.md', 'docs/BUGS.md', 'docs/IMPLEMENTED_FEATURES.md'];

    if (!str_contains($relative, '/')
        && str_ends_with(strtolower($relative), '.md')
        && !in_array($relative, $rootProductionDocs, true)) {
        return 'root Markdown не входить до production-doc allowlist';
    }
    if (str_starts_with($relative, 'docs/')
        && str_ends_with(strtolower($relative), '.md')
        && !in_array($relative, $docsProductionDocs, true)) {
        return 'docs Markdown не входить до production-doc allowlist';
    }

    $checks = [
        '#(^|/)\.(agents|codex|cursor|gemini|github)(/|$)#i' => 'внутрішні AI/dev-конфігурації не можна додавати в production release ZIP',
        '#(^|/)(AGENTS|CLAUDE|GEMINI|audit|FULL_PROJECT_AUDIT|provirka)\.md$#i' => 'внутрішні agent/audit документи не можна додавати в production release ZIP',
        '#(^|/)docs/(AI_[^/]+|AUDIT_[^/]+|SECURITY_AUDIT|UI_UX_RECOMMENDATIONS)\.md$#i' => 'внутрішні audit/security prompt документи не можна додавати в production release ZIP',
    ];

    foreach ($checks as $pattern => $reason) {
        if (preg_match($pattern, $relative)) {
            return $reason;
        }
    }

    return null;
}

function release_should_exclude(string $relative, bool $isDir): bool
{
    $relative = trim(str_replace('\\', '/', $relative), '/');
    $name = basename($relative);

    if ($relative === '') {
        return false;
    }

    if (release_internal_artifact_reason($relative) !== null) {
        return true;
    }

    $excludedDirs = [
        '.git',
        '.github',
        'dist',
        'backups',
        'vendor',
        'node_modules',
        'alembic',
        'app/bot',
        'app/database',
        'app/services',
        'app/utils',
        'scripts',
        'tests',
        'storage/test_sessions',
    ];

    foreach ($excludedDirs as $dir) {
        if ($relative === $dir || str_starts_with($relative, $dir . '/')) {
            return true;
        }
    }

    if (in_array($relative, ['config/database.php', '.env', '.gitignore', 'BUILD_INFO.json'], true)) {
        return true;
    }

    if ($relative === 'storage/restore_journal.json'
        || $relative === 'storage/media_maintenance.lock'
        || preg_match('#(^|/)\.restore-(?:stage|old|discard)-[a-f0-9]{32}-#', $relative) === 1) {
        return true;
    }

    if (!$isDir && preg_match('/(^|\/)temp_[^\/]*\.php$/i', $relative)) {
        return true;
    }

    if (preg_match('/(^|\/)sess_[^\/]*$/', $relative)) {
        return true;
    }

    if (preg_match('/\.(zip|bak|backup|tmp|log)$/i', $name)) {
        return true;
    }

    if (str_starts_with($relative, 'storage/logs/') && $name !== '.gitkeep') {
        return true;
    }

    if (str_starts_with($relative, 'storage/sessions/') && $name !== '.gitkeep') {
        return true;
    }

    if (str_starts_with($relative, 'storage/share_ratelimit/') && $name !== '.gitkeep') {
        return true;
    }

    if (str_starts_with($relative, 'storage/download_locks/') && $name !== '.gitkeep') {
        return true;
    }

    if (str_starts_with($relative, 'storage/trash/') && $name !== '.gitkeep') {
        return true;
    }

    if (str_starts_with($relative, 'storage/originals/') && $name !== '.gitkeep') {
        return true;
    }

    if (str_starts_with($relative, 'public/uploads/large/') && !in_array($name, ['.gitkeep', '.htaccess'], true)) {
        return true;
    }

    if (str_starts_with($relative, 'public/uploads/thumbnails/') && !in_array($name, ['.gitkeep', '.htaccess'], true)) {
        return true;
    }

    if (str_starts_with($relative, 'public/uploads/originals/') && !in_array($name, ['.gitkeep', '.htaccess'], true)) {
        return true;
    }

    if (!$isDir && preg_match('/\.(jpg|jpeg|png|gif|webp|avif)$/i', $name)) {
        // Source code release should not contain uploaded user media.
        return true;
    }

    return false;
}

function release_forbidden_reason(string $entry): ?string
{
    $entry = trim(str_replace('\\', '/', $entry), '/');
    $withoutRoot = preg_replace('#^[^/]+/#', '', $entry) ?? $entry;
    $name = basename($withoutRoot);

    $internalReason = release_internal_artifact_reason($withoutRoot);
    if ($internalReason !== null) {
        return $internalReason . ': ' . $withoutRoot;
    }

    $checks = [
        '#(^|/)\.git(/|$)#' => '.git не можна додавати в release ZIP',
        '#(^|/)config/database\.php$#' => 'config/database.php містить локальні доступи',
        '#(^|/)\.env$#' => '.env містить секрети',
        '#(^|/)temp_[^/]*\.php$#i' => 'тимчасові PHP-міграції не можна додавати в release ZIP',
        '#(^|/)storage/restore_journal\.json$#' => 'restore journal не можна додавати в release ZIP',
        '#(^|/)storage/media_maintenance\.lock$#' => 'media maintenance lock не можна додавати в release ZIP',
        '#(^|/)\.restore-(stage|old|discard)-[a-f0-9]{32}-#' => 'restore staging/rollback media не можна додавати в release ZIP',
        '#(^|/)sess_[^/]*$#' => 'session-файли не можна додавати в ZIP',
        '#\.(log|bak|backup|tmp)$#i' => 'тимчасові/лог/backup-файли не можна додавати в ZIP',
        '#(^|/)storage/originals/.*\.(jpe?g|png|webp|avif)$#i' => 'оригінали фото не можна додавати в release ZIP',
        '#(^|/)public/uploads/(large|thumbnails|originals)/.*\.(jpe?g|png|webp|avif)$#i' => 'завантажені фото не можна додавати в release ZIP',
        '#(^|/)storage/(logs|sessions|trash|download_locks|share_ratelimit)/.+#' => 'runtime-файли storage не можна додавати в ZIP',
    ];

    foreach ($checks as $pattern => $reason) {
        if (preg_match($pattern, $withoutRoot)) {
            if ($name === '.gitkeep' || in_array($withoutRoot, [
                'public/uploads/originals/.htaccess',
                'public/uploads/large/.htaccess',
                'public/uploads/thumbnails/.htaccess',
            ], true)) {
                continue;
            }

            return $reason . ': ' . $withoutRoot;
        }
    }

    return null;
}

/** @param array<string, array{sha256:string,size:int}> $inventory */
function release_verify_zip_streams(string $path, int $expectedEntries, string $projectName, array $inventory): void
{
    $archive = new ZipArchive();
    if ($archive->open($path) !== true) {
        throw new RuntimeException('Створений release ZIP неможливо повторно відкрити.');
    }

    try {
        if ($archive->numFiles !== $expectedEntries) {
            throw new RuntimeException('Кількість entries у release ZIP не збігається з builder inventory.');
        }
        $buildInfoName = $projectName . '/BUILD_INFO.json';
        $buildInfoJson = $archive->getFromName($buildInfoName);
        $buildInfo = is_string($buildInfoJson) ? json_decode($buildInfoJson, true) : null;
        if (!is_array($buildInfo) || ($buildInfo['payload_sha256_inventory'] ?? null) !== $inventory) {
            throw new RuntimeException('Embedded BUILD_INFO inventory не збігається з builder inventory.');
        }

        $verified = [];
        for ($index = 0; $index < $archive->numFiles; $index++) {
            $name = $archive->getNameIndex($index, ZipArchive::FL_UNCHANGED);
            if (!is_string($name)) {
                throw new RuntimeException('Не вдалося прочитати назву release ZIP entry.');
            }
            if (str_ends_with($name, '/')) {
                continue;
            }
            $relative = str_starts_with($name, $projectName . '/')
                ? substr($name, strlen($projectName) + 1)
                : '';
            $stream = $archive->getStream($name);
            if ($stream === false) {
                throw new RuntimeException('Не вдалося відкрити release ZIP entry: ' . $name);
            }
            $hash = hash_init('sha256');
            $size = 0;
            try {
                while (!feof($stream)) {
                    $chunk = fread($stream, 1024 * 1024);
                    if ($chunk === false) {
                        throw new RuntimeException('Помилка читання release ZIP entry: ' . $name);
                    }
                    if ($chunk === '' && !feof($stream)) {
                        throw new RuntimeException('Передчасне завершення release ZIP entry: ' . $name);
                    }
                    $size += strlen($chunk);
                    hash_update($hash, $chunk);
                }
            } finally {
                fclose($stream);
            }
            if ($name === $buildInfoName) {
                continue;
            }
            if ($relative === '' || !isset($inventory[$relative])) {
                throw new RuntimeException('Release ZIP містить файл поза payload inventory: ' . $name);
            }
            if ($size !== $inventory[$relative]['size']
                || !hash_equals($inventory[$relative]['sha256'], hash_final($hash))) {
                throw new RuntimeException('Release ZIP payload не збігається з SHA-256 inventory: ' . $relative);
            }
            $verified[$relative] = true;
        }
        if (count($verified) !== count($inventory)) {
            throw new RuntimeException('Release ZIP не містить усі файли payload inventory.');
        }
    } finally {
        $archive->close();
    }
}

if (defined('TESTING_RELEASE_EXCLUSIONS')) {
    return;
}

$sourceCommit = release_git_output($root, ['rev-parse', 'HEAD']);
$gitStatus = release_git_output($root, ['status', '--porcelain', '--untracked-files=normal']);
$sourceCommitReachable = is_string($sourceCommit)
    && $sourceCommit !== ''
    && release_git_output($root, ['cat-file', '-e', $sourceCommit . '^{commit}']) !== null;
$gitCommitEpoch = $sourceCommitReachable
    ? release_git_output($root, ['show', '-s', '--format=%ct', $sourceCommit])
    : null;
$gitMetadataAvailable = $sourceCommitReachable
    && is_string($gitStatus)
    && is_string($gitCommitEpoch)
    && preg_match('/\A[0-9]+\z/', $gitCommitEpoch) === 1;
$sourceCommitReachable = $gitMetadataAvailable;
if (!$gitMetadataAvailable && !isset($options['allow-dirty'])) {
    fwrite(STDERR, "Release build blocked: Git metadata is unavailable. Use a real clean checkout or --allow-dirty for an explicitly unverified local build.\n");
    exit(1);
}
$sourceCommit = $gitMetadataAvailable ? $sourceCommit : 'unverified-source';
$sourceDirty = !$gitMetadataAvailable || $gitStatus !== '';
if ($sourceDirty && !isset($options['allow-dirty'])) {
    fwrite(STDERR, "Release build blocked: Git working tree is dirty. Commit/stash changes or use --allow-dirty for a deliberate local build.\n");
    exit(1);
}
$epochPolicy = release_build_epoch($gitMetadataAvailable ? $gitCommitEpoch : null);
$buildEpoch = $epochPolicy['epoch'];
$reproducibleMetadata = $epochPolicy['reproducible'];

if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "Для ZIP64/streaming release build потрібне PHP-розширення zip.\n");
    exit(1);
}

if (!is_dir($distDir) && !mkdir($distDir, 0755, true)) {
    fwrite(STDERR, "Не вдалося створити папку dist.\n");
    exit(1);
}

$safeOutput = null;
$zip = null;
$publishedOutput = null;
$createdSidecars = [];
$artifactHash = null;
try {
    $safeOutput = prepare_safe_cli_zip_output($output, $root, [$distDir], 0640);
    $output = $safeOutput['final'];
    $checksumPath = $output . '.sha256';
    $provenancePath = $output . '.provenance.json';
    if (file_exists($checksumPath) || is_link($checksumPath)
        || file_exists($provenancePath) || is_link($provenancePath)) {
        throw new RuntimeException('Release checksum/provenance sidecar уже існує.');
    }
    $zip = new ZipArchive();
    if ($zip->open($safeOutput['temporary'], ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException('Не вдалося відкрити temporary release ZIP.');
    }

    $entries = [];
    $inventory = [];
    $estimatedZipBytes = 0;
    if (!$zip->addEmptyDir($projectName)) {
        throw new RuntimeException('Не вдалося додати root directory до release ZIP.');
    }
    release_apply_zip_metadata($zip, $projectName . '/', $buildEpoch, true);
    $entries[] = $projectName . '/';

    if ($gitMetadataAvailable) {
        $gitPayloadPaths = release_git_payload_paths($root, $sourceCommit, $sourceDirty);
        $sourceEntries = release_source_entries_from_git_paths($root, $gitPayloadPaths, $sourceDirty);
    } else {
        // Explicit --allow-dirty without Git metadata is an unverified emergency build.
        // Keep the filesystem fallback, but provenance remains source_dirty=true.
        $sourceIterator = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);
        $filterIterator = new RecursiveCallbackFilterIterator(
            $sourceIterator,
            static function (SplFileInfo $item) use ($root): bool {
                $relative = release_relative_path($root, $item->getPathname());

                return !release_should_exclude($relative, $item->isDir());
            }
        );
        $iterator = new RecursiveIteratorIterator(
            $filterIterator,
            RecursiveIteratorIterator::SELF_FIRST
        );
        $sourceEntries = [];
        foreach ($iterator as $item) {
            /** @var SplFileInfo $item */
            $path = $item->getPathname();
            $relative = release_relative_path($root, $path);
            $isDir = $item->isDir();

            if ($item->isLink() || is_link($path) || !release_path_is_inside_root($root, $path)) {
                throw new RuntimeException('Release build blocked: symlink/junction або path escape: ' . $relative);
            }

            $entry = $projectName . '/' . $relative;
            $reason = release_forbidden_reason($entry);
            if ($reason !== null) {
                throw new RuntimeException('Release build blocked: ' . $reason);
            }

            $sourceEntries[] = ['path' => $path, 'relative' => $relative, 'directory' => $isDir];
        }
    }

    usort(
        $sourceEntries,
        static fn (array $left, array $right): int => strcmp($left['relative'], $right['relative'])
    );

    foreach ($sourceEntries as $sourceEntry) {
        $path = $sourceEntry['path'];
        $relative = $sourceEntry['relative'];
        $isDir = $sourceEntry['directory'];
        if (is_link($path) || !release_path_is_inside_root($root, $path)) {
            throw new RuntimeException('Release build blocked: path змінився під час inventory: ' . $relative);
        }
        $entry = $projectName . '/' . $relative;

        if ($isDir) {
            if (!$zip->addEmptyDir($entry)) {
                throw new RuntimeException('Не вдалося додати release directory: ' . $relative);
            }
            $entry = rtrim($entry, '/') . '/';
            release_apply_zip_metadata($zip, $entry, $buildEpoch, true);
            $entries[] = $entry;
            continue;
        }

        $hash = hash_file('sha256', $path);
        if (!is_string($hash)) {
            throw new RuntimeException('Не вдалося обчислити SHA-256 release file: ' . $relative);
        }
        if (!$zip->addFile($path, $entry)) {
            throw new RuntimeException('Не вдалося додати release file: ' . $relative);
        }
        release_apply_zip_metadata($zip, $entry, $buildEpoch, false);
        $size = filesize($path);
        if (!is_int($size)) {
            throw new RuntimeException('Не вдалося визначити розмір release file: ' . $relative);
        }
        $estimatedZipBytes += $size;
        $inventory[$relative] = ['sha256' => $hash, 'size' => $size];
        $entries[] = $entry;
    }

    ksort($inventory);
    $buildInfo = json_encode([
        'format_version' => 1,
        'project' => $projectName,
        'version' => $version,
        'source_commit' => $sourceCommit,
        'source_commit_reachable' => $sourceCommitReachable,
        'source_dirty' => $sourceDirty,
        'source_date_epoch' => $buildEpoch,
        'reproducible_metadata' => $reproducibleMetadata,
        'built_at_utc' => gmdate(DATE_ATOM, $buildEpoch),
        'payload_file_count' => count($inventory),
        'payload_sha256_inventory' => $inventory,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $buildInfoEntry = $projectName . '/BUILD_INFO.json';
    if (!$zip->addFromString($buildInfoEntry, $buildInfo . "\n")) {
        throw new RuntimeException('Не вдалося додати BUILD_INFO.json.');
    }
    release_apply_zip_metadata($zip, $buildInfoEntry, $buildEpoch, false);
    $entries[] = $buildInfoEntry;
    $estimatedZipBytes += strlen($buildInfo) + 1;

    foreach ($entries as $entry) {
        $reason = release_forbidden_reason($entry);
        if ($reason !== null) {
            throw new RuntimeException('Release verification failed: ' . $reason);
        }
    }

    require_safe_cli_zip_free_space($safeOutput, $estimatedZipBytes);
    if (!$zip->close()) {
        throw new RuntimeException('Не вдалося завершити temporary release ZIP.');
    }
    $zip = null;
    release_verify_zip_streams($safeOutput['temporary'], count($entries), $projectName, $inventory);
    publish_safe_cli_zip_output($safeOutput);
    $publishedOutput = $output;

    $artifactHash = hash_file('sha256', $output);
    $artifactSize = filesize($output);
    if (!is_string($artifactHash) || !is_int($artifactSize)) {
        throw new RuntimeException('Не вдалося обчислити checksum опублікованого release ZIP.');
    }
    release_write_sidecar($checksumPath, $artifactHash . '  ' . basename($output) . "\n");
    $createdSidecars[] = $checksumPath;
    $provenance = json_encode([
        'format_version' => 1,
        'artifact' => basename($output),
        'artifact_sha256' => $artifactHash,
        'artifact_size' => $artifactSize,
        'source_commit' => $sourceCommit,
        'source_commit_reachable' => $sourceCommitReachable,
        'source_dirty' => $sourceDirty,
        'source_date_epoch' => $buildEpoch,
        'reproducible_metadata' => $reproducibleMetadata,
        'payload_file_count' => count($inventory),
        'checksum_file' => basename($checksumPath),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    release_write_sidecar($provenancePath, $provenance . "\n");
    $createdSidecars[] = $provenancePath;
} catch (Throwable $exception) {
    if ($zip instanceof ZipArchive) {
        $zip->close();
    }
    cleanup_safe_cli_zip_output($safeOutput);
    foreach ($createdSidecars as $sidecar) {
        if (is_file($sidecar)) {
            @unlink($sidecar);
        }
    }
    if (is_string($publishedOutput) && is_file($publishedOutput)) {
        @unlink($publishedOutput);
    }
    fwrite(STDERR, "Release verification failed: " . $exception->getMessage() . "\n");
    exit(1);
}

$size = filesize($output);
echo "Release ZIP створено: {$output}\n";
echo "Entries: " . count($entries) . "\n";
echo "Size: " . ($size === false ? 'unknown' : $size . ' bytes') . "\n";
echo "Перевірка небезпечних файлів: OK\n";
echo "Перевірка ZIP streams: OK\n";
echo "SHA-256: {$artifactHash}\n";
echo "Checksum: {$output}.sha256\n";
echo "Provenance: {$output}.provenance.json\n";
