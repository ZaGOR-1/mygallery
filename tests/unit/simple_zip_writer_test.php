<?php

declare(strict_types=1);

require_once dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'tools' . DIRECTORY_SEPARATOR . 'lib' . DIRECTORY_SEPARATOR . 'SimpleZipWriter.php';

final class TestShortWriteStream
{
    public mixed $context;
    private int $position = 0;
    private int $writes = 0;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return true;
    }

    public function stream_write(string $data): int
    {
        $this->writes++;
        if ($this->writes >= 2) {
            return 0;
        }
        $length = strlen($data);
        $this->position += $length;
        return $length;
    }

    public function stream_tell(): int
    {
        return $this->position;
    }

    public function stream_flush(): bool
    {
        return true;
    }

    public function stream_close(): void
    {
    }
}

$temporaryZip = tempnam(sys_get_temp_dir(), 'mygallery_writer_');
$sourceFile = tempnam(sys_get_temp_dir(), 'mygallery_writer_source_');
assert_true(is_string($temporaryZip) && is_string($sourceFile), 'ZIP writer fixtures should be created');
unlink($temporaryZip);
file_put_contents($sourceFile, 'writer-content');

try {
    $writer = new SimpleZipWriter($temporaryZip, 0600);
    $writer->addDirectory('root');
    $writer->addFile($sourceFile, 'root/file.txt');
    $writer->finish();
    $zip = new ZipArchive();
    assert_true($zip->open($temporaryZip) === true, 'SimpleZipWriter output must open');
    assert_equals('writer-content', $zip->getFromName('root/file.txt'), 'SimpleZipWriter payload must be readable');
    $zip->close();
    if (PHP_OS_FAMILY !== 'Windows') {
        clearstatcache(true, $temporaryZip);
        assert_equals(0600, fileperms($temporaryZip) & 0777, 'private ZIP mode must be 0600');
    }

    assert_true(stream_wrapper_register('testshortwrite', TestShortWriteStream::class), 'short-write wrapper should register');
    try {
        $failingWriter = new SimpleZipWriter('testshortwrite://archive');
        assert_throws(
            static fn () => $failingWriter->addDirectory('root'),
            RuntimeException::class,
            'short write must throw instead of reporting ZIP success'
        );
        unset($failingWriter);
    } finally {
        stream_wrapper_unregister('testshortwrite');
    }
} finally {
    if (is_file($temporaryZip)) {
        unlink($temporaryZip);
    }
    if (is_file($sourceFile)) {
        unlink($sourceFile);
    }
}
