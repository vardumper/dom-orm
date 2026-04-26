<?php
declare(strict_types=1);

namespace DOM\ORM\Storage;

use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\InvalidVisibilityProvided;
use League\Flysystem\StorageAttributes;
use League\Flysystem\Visibility;

final class InMemoryPathHelper
{
    public static function normalizePath(string $path): string
    {
        $path = \str_replace('\\', '/', \trim($path));

        return \trim($path, '/');
    }

    public static function assertVisibility(string $visibility): void
    {
        if ($visibility !== Visibility::PUBLIC && $visibility !== Visibility::PRIVATE) {
            InvalidVisibilityProvided::withVisibility($visibility, \sprintf('%s or %s', Visibility::PUBLIC, Visibility::PRIVATE));
        }
    }

    public static function isDirectChild(string $path, string $parent): bool
    {
        $parent = self::normalizePath($parent);
        if ($parent === '') {
            return !\str_contains($path, '/');
        }

        if (!\str_starts_with($path, $parent . '/')) {
            return false;
        }

        $relative = \substr($path, \strlen($parent) + 1);

        return $relative !== '' && !\str_contains($relative, '/');
    }

    /**
     * @param array{files: array<string, array{contents: string, visibility: string, lastModified: int}>, directories: array<string, array{visibility: string, lastModified: int}>} $bucket
     * @return array<int, StorageAttributes>
     */
    public static function listContentsFromBucket(array $bucket, string $path, bool $deep): array
    {
        $path = self::normalizePath($path);
        $prefix = $path === '' ? '' : $path . '/';

        return [
            ...self::directoryAttributes($bucket, $path, $prefix, $deep),
            ...self::fileAttributes($bucket, $path, $prefix, $deep),
        ];
    }

    /**
     * @param array{files: array<string, array{contents: string, visibility: string, lastModified: int}>, directories: array<string, array{visibility: string, lastModified: int}>} $bucket
     * @return array<int, DirectoryAttributes>
     */
    private static function directoryAttributes(array $bucket, string $path, string $prefix, bool $deep): array
    {
        $result = [];

        foreach ($bucket['directories'] as $directoryPath => $metadata) {
            if ($directoryPath === '' || $directoryPath === $path || !\str_starts_with($directoryPath, $prefix)) {
                continue;
            }

            if (!$deep && !self::isDirectChild($directoryPath, $path)) {
                continue;
            }

            $result[] = new DirectoryAttributes($directoryPath, $metadata['visibility'], $metadata['lastModified']);
        }

        return $result;
    }

    /**
     * @param array{files: array<string, array{contents: string, visibility: string, lastModified: int}>, directories: array<string, array{visibility: string, lastModified: int}>} $bucket
     * @return array<int, FileAttributes>
     */
    private static function fileAttributes(array $bucket, string $path, string $prefix, bool $deep): array
    {
        $result = [];

        foreach ($bucket['files'] as $filePath => $metadata) {
            if (!\str_starts_with($filePath, $prefix)) {
                continue;
            }

            if (!$deep && !self::isDirectChild($filePath, $path)) {
                continue;
            }

            $result[] = new FileAttributes(
                $filePath,
                \strlen($metadata['contents']),
                $metadata['visibility'],
                $metadata['lastModified'],
                \str_ends_with($filePath, '.xml') ? 'application/xml' : 'application/octet-stream',
            );
        }

        return $result;
    }
}
