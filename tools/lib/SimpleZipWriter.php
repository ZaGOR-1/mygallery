<?php

declare(strict_types=1);

final class SimpleZipWriter
{
    /** @var resource */
    private $handle;

    /** @var array<int, array<string, int|string>> */
    private array $entries = [];

    public function __construct(private readonly string $zipPath)
    {
        $dir = dirname($zipPath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
            throw new RuntimeException('Не вдалося створити папку для ZIP: ' . $dir);
        }

        $handle = fopen($zipPath, 'wb');
        if ($handle === false) {
            throw new RuntimeException('Не вдалося створити ZIP-файл: ' . $zipPath);
        }

        $this->handle = $handle;
    }

    public function addDirectory(string $entryName): void
    {
        $entryName = $this->normalizeEntryName($entryName);
        if ($entryName === '') {
            return;
        }

        if (!str_ends_with($entryName, '/')) {
            $entryName .= '/';
        }

        $this->addRawEntry($entryName, '', true, time());
    }

    public function addFile(string $filePath, string $entryName): void
    {
        if (!is_file($filePath) || !is_readable($filePath)) {
            throw new RuntimeException('Файл недоступний для читання: ' . $filePath);
        }

        $entryName = $this->normalizeEntryName($entryName);
        if ($entryName === '' || str_ends_with($entryName, '/')) {
            throw new RuntimeException('Некоректна назва ZIP entry: ' . $entryName);
        }

        $data = file_get_contents($filePath);
        if ($data === false) {
            throw new RuntimeException('Не вдалося прочитати файл: ' . $filePath);
        }

        $mtime = filemtime($filePath);
        $this->addRawEntry($entryName, $data, false, $mtime === false ? time() : $mtime);
    }

    public function finish(): void
    {
        $centralDirOffset = ftell($this->handle);
        if ($centralDirOffset === false) {
            throw new RuntimeException('Не вдалося визначити позицію ZIP-файла.');
        }

        foreach ($this->entries as $entry) {
            fwrite($this->handle, pack(
                'VvvvvvvVVVvvvvvVV',
                0x02014b50,
                0x031e,
                20,
                0,
                0,
                (int) $entry['time'],
                (int) $entry['date'],
                (int) $entry['crc'],
                (int) $entry['size'],
                (int) $entry['size'],
                strlen((string) $entry['name']),
                0,
                0,
                0,
                0,
                (int) $entry['external_attr'],
                (int) $entry['offset']
            ));
            fwrite($this->handle, (string) $entry['name']);
        }

        $centralDirEnd = ftell($this->handle);
        if ($centralDirEnd === false) {
            throw new RuntimeException('Не вдалося визначити кінець central directory.');
        }

        $centralDirSize = $centralDirEnd - $centralDirOffset;
        $count = count($this->entries);

        fwrite($this->handle, pack(
            'VvvvvVVv',
            0x06054b50,
            0,
            0,
            $count,
            $count,
            $centralDirSize,
            $centralDirOffset,
            0
        ));

        fclose($this->handle);
    }

    private function addRawEntry(string $entryName, string $data, bool $directory, int $mtime): void
    {
        foreach ($this->entries as $entry) {
            if ($entry['name'] === $entryName) {
                return;
            }
        }

        $offset = ftell($this->handle);
        if ($offset === false) {
            throw new RuntimeException('Не вдалося визначити offset ZIP entry.');
        }

        [$dosTime, $dosDate] = $this->dosDateTime($mtime);
        $size = strlen($data);
        $crc = $directory ? 0 : (int) hexdec(hash('crc32b', $data));

        fwrite($this->handle, pack(
            'VvvvvvVVVvv',
            0x04034b50,
            20,
            0,
            0,
            $dosTime,
            $dosDate,
            $crc,
            $size,
            $size,
            strlen($entryName),
            0
        ));
        fwrite($this->handle, $entryName);
        if ($data !== '') {
            fwrite($this->handle, $data);
        }

        $this->entries[] = [
            'name' => $entryName,
            'crc' => $crc,
            'size' => $size,
            'time' => $dosTime,
            'date' => $dosDate,
            'external_attr' => $directory ? 0x10 : 0,
            'offset' => $offset,
        ];
    }

    /** @return array{0:int,1:int} */
    private function dosDateTime(int $timestamp): array
    {
        $parts = getdate($timestamp);
        $year = max(1980, (int) $parts['year']);
        $dosDate = (($year - 1980) << 9) | ((int) $parts['mon'] << 5) | (int) $parts['mday'];
        $dosTime = ((int) $parts['hours'] << 11) | ((int) $parts['minutes'] << 5) | ((int) floor((int) $parts['seconds'] / 2));

        return [$dosTime, $dosDate];
    }

    private function normalizeEntryName(string $entryName): string
    {
        $entryName = str_replace('\\', '/', $entryName);
        $entryName = preg_replace('#/+#', '/', $entryName) ?? $entryName;
        $entryName = ltrim($entryName, '/');

        if ($entryName === '' || str_contains($entryName, '../')) {
            throw new RuntimeException('Небезпечна назва ZIP entry: ' . $entryName);
        }

        return $entryName;
    }
}
