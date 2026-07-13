<?php

declare(strict_types=1);

function safe_cli_absolute_path(string $path): string
{
    $path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, trim($path));
    if ($path === '') {
        throw new InvalidArgumentException('Порожній output path.');
    }
    if (preg_match('~\A[A-Za-z]:\\\\~', $path) !== 1 && !str_starts_with($path, DIRECTORY_SEPARATOR)) {
        $path = getcwd() . DIRECTORY_SEPARATOR . $path;
    }

    $prefix = '';
    if (preg_match('~\A[A-Za-z]:\\\\~', $path) === 1) {
        $prefix = substr($path, 0, 3);
        $path = substr($path, 3);
    } else {
        $prefix = DIRECTORY_SEPARATOR;
        $path = ltrim($path, DIRECTORY_SEPARATOR);
    }

    $parts = [];
    foreach (explode(DIRECTORY_SEPARATOR, $path) as $part) {
        if ($part === '' || $part === '.') {
            continue;
        }
        if ($part === '..') {
            if ($parts === []) {
                throw new InvalidArgumentException('Output path виходить вище filesystem root.');
            }
            array_pop($parts);
            continue;
        }
        $parts[] = $part;
    }

    return rtrim($prefix . implode(DIRECTORY_SEPARATOR, $parts), DIRECTORY_SEPARATOR);
}

function safe_cli_path_is_inside(string $path, string $directory): bool
{
    $path = safe_cli_absolute_path($path);
    $directory = rtrim(safe_cli_absolute_path($directory), DIRECTORY_SEPARATOR);
    if (PHP_OS_FAMILY === 'Windows') {
        $path = strtolower($path);
        $directory = strtolower($directory);
    }

    return $path === $directory || str_starts_with($path, $directory . DIRECTORY_SEPARATOR);
}

/**
 * @param list<string> $allowedProjectDirectories
 * @return array{final:string,temporary:string,mode:int}
 */
function prepare_safe_cli_zip_output(
    string $requestedPath,
    string $projectRoot,
    array $allowedProjectDirectories,
    int $mode = 0600
): array {
    $final = safe_cli_absolute_path($requestedPath);
    if (strtolower(pathinfo($final, PATHINFO_EXTENSION)) !== 'zip') {
        throw new InvalidArgumentException('Output має бути файлом із розширенням .zip.');
    }
    if (is_link($final) || file_exists($final)) {
        throw new RuntimeException('Output уже існує або є symlink; автоматичний overwrite заборонено: ' . $final);
    }

    $parentLexical = dirname($final);
    $parentReal = realpath($parentLexical);
    if ($parentReal === false || !is_dir($parentReal) || is_link($parentLexical)) {
        throw new RuntimeException('Батьківська output-директорія не існує або є symlink.');
    }
    if (!same_filesystem_path($parentReal, $parentLexical)) {
        throw new RuntimeException('Output parent проходить через symlink/junction.');
    }

    $rootReal = realpath($projectRoot);
    if ($rootReal === false) {
        throw new RuntimeException('Project root не знайдено.');
    }
    $realFinal = $parentReal . DIRECTORY_SEPARATOR . basename($final);
    $insideProjectLexically = safe_cli_path_is_inside($final, $projectRoot);
    $insideProjectReally = safe_cli_path_is_inside($realFinal, $rootReal);
    if ($insideProjectLexically || $insideProjectReally) {
        $allowed = false;
        foreach ($allowedProjectDirectories as $allowedDirectory) {
            $allowedReal = realpath($allowedDirectory);
            if ($allowedReal !== false
                && safe_cli_path_is_inside($final, $allowedDirectory)
                && safe_cli_path_is_inside($realFinal, $allowedReal)) {
                $allowed = true;
                break;
            }
        }
        if (!$allowed) {
            throw new RuntimeException('Output усередині project source/config/public/storage заборонено.');
        }
    }

    $temporary = tempnam($parentReal, '.mygallery_zip_');
    if ($temporary === false || is_link($temporary)) {
        throw new RuntimeException('Не вдалося зарезервувати sibling temporary ZIP.');
    }
    if (PHP_OS_FAMILY !== 'Windows' && !chmod($temporary, $mode)) {
        @unlink($temporary);
        throw new RuntimeException('Не вдалося встановити приватні права temporary ZIP.');
    }

    return ['final' => $realFinal, 'temporary' => $temporary, 'mode' => $mode];
}

/** @param array{final:string,temporary:string,mode:int} $output */
function publish_safe_cli_zip_output(array $output): void
{
    if (!is_file($output['temporary']) || is_link($output['temporary'])) {
        throw new RuntimeException('Temporary ZIP відсутній або небезпечний.');
    }
    if (file_exists($output['final']) || is_link($output['final'])) {
        throw new RuntimeException('Final ZIP з’явився під час збірки; overwrite скасовано.');
    }
    if (PHP_OS_FAMILY !== 'Windows' && !chmod($output['temporary'], $output['mode'])) {
        throw new RuntimeException('Не вдалося встановити права ZIP перед publish.');
    }
    if (!rename($output['temporary'], $output['final'])) {
        throw new RuntimeException('Не вдалося атомарно опублікувати ZIP.');
    }
}

/** @param array{final:string,temporary:string,mode:int} $output */
function require_safe_cli_zip_free_space(array $output, int $estimatedUncompressedBytes): void
{
    if ($estimatedUncompressedBytes < 0) {
        throw new InvalidArgumentException('Некоректна оцінка розміру ZIP.');
    }

    $freeBytes = disk_free_space(dirname($output['temporary']));
    if (!is_float($freeBytes)) {
        throw new RuntimeException('Не вдалося визначити вільне місце для ZIP output.');
    }

    $reserveBytes = max(16 * 1024 * 1024, (int) ceil($estimatedUncompressedBytes * 0.05));
    if ($estimatedUncompressedBytes > PHP_INT_MAX - $reserveBytes
        || $freeBytes < $estimatedUncompressedBytes + $reserveBytes) {
        throw new RuntimeException('Недостатньо вільного місця для ZIP та validation reserve.');
    }
}

/** @param array{final:string,temporary:string,mode:int}|null $output */
function cleanup_safe_cli_zip_output(?array $output): void
{
    if (is_array($output) && is_file($output['temporary'])) {
        @unlink($output['temporary']);
    }
}
