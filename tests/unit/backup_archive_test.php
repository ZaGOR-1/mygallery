<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/tools/lib/BackupArchiveValidator.php';

assert_true(class_exists('ZipArchive'), 'ZipArchive is required for backup integrity tests');

/**
 * @param null|Closure(array<string, mixed>): array<string, mixed> $mutateManifest
 * @param array<string, string> $extraEntries
 * @param list<string> $omitEntries
 */
function test_backup_create_zip(
    string $directory,
    string $name,
    ?Closure $mutateManifest = null,
    array $extraEntries = [],
    array $omitEntries = [],
    ?string $sqlOverride = null,
    bool $emptyMedia = false
): string {
    $sql = $sqlOverride ?? "-- MyGallery backup format 2\n"
        . "SET FOREIGN_KEY_CHECKS=0;\n"
        . "DELETE FROM `schema_migrations`;\n"
        . "SET FOREIGN_KEY_CHECKS=1;\n";
    $media = [
        'storage_originals' => [str_repeat('a', 32) . '.jpg' => 'ORIGINAL-CONTENT'],
        'public_large' => [
            str_repeat('a', 32) . '.jpg' => 'LARGE-CONTENT',
            str_repeat('a', 32) . '.avif' => 'AVIF-CONTENT',
        ],
        'public_thumbnails' => [
            str_repeat('c', 32) . '.jpg' => 'THUMB-JPEG-CONTENT',
            str_repeat('c', 32) . '.webp' => 'THUMB-CONTENT',
        ],
    ];
    if ($emptyMedia) {
        $media = [
            'storage_originals' => [],
            'public_large' => [],
            'public_thumbnails' => [],
        ];
    }
    $prefixes = backup_media_prefixes();
    $manifestFiles = [];
    $zipEntries = [MYGALLERY_BACKUP_DATABASE_ENTRY => $sql];

    foreach ($media as $group => $files) {
        $manifestFiles[$group] = [];
        foreach ($files as $filename => $content) {
            $entry = $prefixes[$group] . $filename;
            $manifestFiles[$group][] = [
                'entry' => $entry,
                'size' => strlen($content),
                'sha256' => hash('sha256', $content),
            ];
            $zipEntries[$entry] = $content;
        }
    }

    $manifest = [
        'format_version' => MYGALLERY_BACKUP_FORMAT_VERSION,
        'created_at' => '2026-07-10T12:00:00+03:00',
        'include_config' => false,
        'database' => [
            'entry' => MYGALLERY_BACKUP_DATABASE_ENTRY,
            'size' => strlen($sql),
            'sha256' => hash('sha256', $sql),
        ],
        'files' => $manifestFiles,
        'photo_inventory' => $emptyMedia ? [] : [[
            'id' => 1,
            'filename' => str_repeat('a', 32) . '.jpg',
            'thumbnail_filename' => str_repeat('c', 32) . '.jpg',
            'original_sha256' => hash('sha256', 'ORIGINAL-CONTENT'),
        ]],
        'config' => null,
    ];
    if ($mutateManifest !== null) {
        $manifest = $mutateManifest($manifest);
    }
    $zipEntries[MYGALLERY_BACKUP_MANIFEST_ENTRY] = json_encode(
        $manifest,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    );
    $zipEntries = array_merge($zipEntries, $extraEntries);

    $path = $directory . DIRECTORY_SEPARATOR . $name;
    $zip = new ZipArchive();
    assert_true($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 'test ZIP should open');
    foreach ($zipEntries as $entry => $content) {
        if (in_array($entry, $omitEntries, true)) {
            continue;
        }
        assert_true($zip->addFromString($entry, $content), 'test ZIP entry should be added: ' . $entry);
        $zip->setCompressionName($entry, ZipArchive::CM_STORE);
    }
    assert_true($zip->close(), 'test ZIP should close');

    return $path;
}

function test_backup_validate_path(
    string $path,
    int $maximumTotalUncompressedBytes = MYGALLERY_BACKUP_MAX_TOTAL_UNCOMPRESSED_BYTES,
    int $maximumCompressionRatio = MYGALLERY_BACKUP_MAX_COMPRESSION_RATIO
): array
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Test archive cannot be opened.');
    }
    try {
        return backup_validate_archive($zip, $maximumTotalUncompressedBytes, $maximumCompressionRatio);
    } finally {
        $zip->close();
    }
}

$testDirectory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'mygallery_backup_tests_' . bin2hex(random_bytes(6));
assert_true(mkdir($testDirectory, 0700), 'temporary backup test directory should be created');

try {
    $validPath = test_backup_create_zip($testDirectory, 'valid.zip');
    $validated = test_backup_validate_path($validPath);
    assert_equals(5, array_sum(array_map('count', $validated['media_entries'])), 'valid JPEG/WebP/AVIF archive media count');
    assert_true($validated['total_uncompressed_bytes'] > 0, 'backup validator must report total uncompressed bytes');
    assert_equals(strlen('ORIGINAL-CONTENT'), $validated['media_uncompressed_bytes']['storage_originals'], 'backup validator must report per-target staging bytes');

    assert_throws(
        static fn (): array => test_backup_validate_path($validPath, 32, MYGALLERY_BACKUP_MAX_COMPRESSION_RATIO),
        RuntimeException::class,
        'backup validator must enforce the cumulative uncompressed-byte limit'
    );

    $ratioPath = $testDirectory . DIRECTORY_SEPARATOR . 'ratio-bomb.zip';
    $ratioZip = new ZipArchive();
    assert_true($ratioZip->open($ratioPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true, 'ratio ZIP should open');
    assert_true($ratioZip->addFromString('first.bin', str_repeat('A', 2 * 1024 * 1024)), 'ratio payload should be added');
    assert_true($ratioZip->addFromString('second.bin', 'x'), 'ratio ZIP needs a second entry');
    assert_true($ratioZip->close(), 'ratio ZIP should close');
    assert_throws(
        static fn (): array => test_backup_validate_path($ratioPath, 10 * 1024 * 1024, 10),
        RuntimeException::class,
        'backup validator must reject suspicious compression ratios before extraction'
    );

    $emptyMediaPath = test_backup_create_zip($testDirectory, 'empty-media.zip', null, [], [], null, true);
    $emptyMediaValidated = test_backup_validate_path($emptyMediaPath);
    assert_equals(0, array_sum(array_map('count', $emptyMediaValidated['media_entries'])), 'empty media directories are valid');

    $emptySqlPath = test_backup_create_zip($testDirectory, 'empty-sql.zip', null, [], [], '');
    assert_throws(
        static fn (): array => test_backup_validate_path($emptySqlPath),
        RuntimeException::class,
        'empty database.sql must be rejected'
    );

    $badHashPath = test_backup_create_zip(
        $testDirectory,
        'bad-hash.zip',
        static function (array $manifest): array {
            $manifest['files']['public_large'][0]['sha256'] = str_repeat('0', 64);
            return $manifest;
        }
    );
    assert_throws(
        static fn (): array => test_backup_validate_path($badHashPath),
        RuntimeException::class,
        'media SHA-256 mismatch must be rejected'
    );

    $missingEntry = backup_media_prefixes()['public_large'] . str_repeat('a', 32) . '.jpg';
    $missingPath = test_backup_create_zip($testDirectory, 'missing-entry.zip', null, [], [$missingEntry]);
    assert_throws(
        static fn (): array => test_backup_validate_path($missingPath),
        RuntimeException::class,
        'manifest/ZIP count mismatch must be rejected'
    );

    $inventoryMismatchPath = test_backup_create_zip(
        $testDirectory,
        'inventory-mismatch.zip',
        static function (array $manifest): array {
            $manifest['photo_inventory'] = [];
            return $manifest;
        }
    );
    assert_throws(
        static fn (): array => test_backup_validate_path($inventoryMismatchPath),
        RuntimeException::class,
        'media not represented by the DB photo inventory must be rejected'
    );

    $controlFilePath = test_backup_create_zip(
        $testDirectory,
        'unexpected-control.zip',
        null,
        [backup_media_prefixes()['public_large'] . '.htaccess' => 'Require all denied']
    );
    assert_throws(
        static fn (): array => test_backup_validate_path($controlFilePath),
        RuntimeException::class,
        'backup must reject .htaccess as media payload'
    );

    $corruptPath = test_backup_create_zip($testDirectory, 'corrupt-stream.zip');
    $archiveBytes = file_get_contents($corruptPath);
    assert_true(is_string($archiveBytes), 'corrupt test ZIP should be readable');
    $needle = 'ORIGINAL-CONTENT';
    $position = strpos($archiveBytes, $needle);
    assert_true($position !== false, 'stored ZIP payload should be locatable for corruption test');
    $archiveBytes[$position] = $archiveBytes[$position] === 'X' ? 'Y' : 'X';
    assert_true(file_put_contents($corruptPath, $archiveBytes) !== false, 'corrupt test ZIP should be written');
    assert_throws(
        static fn (): array => test_backup_validate_path($corruptPath),
        RuntimeException::class,
        'CRC/read corruption must be rejected'
    );
} finally {
    $files = glob($testDirectory . DIRECTORY_SEPARATOR . '*') ?: [];
    foreach ($files as $file) {
        unlink($file);
    }
    rmdir($testDirectory);
}
