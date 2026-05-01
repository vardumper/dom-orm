<?php
declare(strict_types=1);

namespace DOM\ORM\Storage;

use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToCreateDirectory;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\Visibility;

/**
 * Process-local in-memory Flysystem adapter.
 *
 * This adapter keeps files in PHP memory only and can be selected via
 * `dom-orm.flysystem.adapter` / `DOM_ORM_FLYSYSTEM_ADAPTER`.
 *
 * @SuppressWarnings("PHPMD.TooManyPublicMethods")
 */
final class InMemoryFilesystemAdapter implements FilesystemAdapter
{
    private const FILE_DOES_NOT_EXIST = 'File does not exist.';

    /**
     * @var array<string, array{files: array<string, array{contents: string, visibility: string, lastModified: int}>, directories: array<string, array{visibility: string, lastModified: int}>}>
     */
    private static array $buckets = [];

    private string $bucketKey;

    public function __construct(?string $location = null)
    {
        // The optional location value becomes an in-memory namespace.
        $this->bucketKey = $location ?? '__default__';

        if (!isset(self::$buckets[$this->bucketKey])) {
            self::$buckets[$this->bucketKey] = [
                'files' => [],
                'directories' => [
                    '' => [
                        'visibility' => Visibility::PRIVATE,
                        'lastModified' => \time(),
                    ],
                ],
            ];
        }
    }

    /**
     * Clear one or all in-memory buckets.
     *
     * Call after the XML has been flushed to the database column to release
     * the memory held by that bucket.  Omit the argument to clear every
     * namespace, or pass a specific location key to clear only that one.
     */
    public static function reset(?string $bucketKey = null): void
    {
        if ($bucketKey === null) {
            self::$buckets = [];
        } else {
            unset(self::$buckets[$bucketKey]);
        }
    }

    public function fileExists(string $path): bool
    {
        $path = InMemoryPathHelper::normalizePath($path);

        return isset($this->bucket()['files'][$path]);
    }

    public function directoryExists(string $path): bool
    {
        $path = InMemoryPathHelper::normalizePath($path);

        return isset($this->bucket()['directories'][$path]);
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $path = InMemoryPathHelper::normalizePath($path);
        if ($path === '') {
            throw UnableToWriteFile::atLocation($path, 'Path must not be empty.');
        }

        $this->ensureParentDirectories($path);
        $visibility = (string)$config->get('visibility', Visibility::PRIVATE);
        InMemoryPathHelper::assertVisibility($visibility);

        $bucket = &$this->bucket();
        $bucket['files'][$path] = [
            'contents' => $contents,
            'visibility' => $visibility,
            'lastModified' => \time(),
        ];
    }

    /**
     * @param resource $contents
     */
    public function writeStream(string $path, $contents, Config $config): void
    {
        if (!\is_resource($contents)) {
            throw UnableToWriteFile::atLocation($path, 'The provided stream is not a valid resource.');
        }

        $data = \stream_get_contents($contents);
        if (!\is_string($data)) {
            throw UnableToWriteFile::atLocation($path, 'Failed to read stream contents.');
        }

        $this->write($path, $data, $config);
    }

    public function read(string $path): string
    {
        $path = InMemoryPathHelper::normalizePath($path);
        $file = $this->bucket()['files'][$path] ?? null;
        if ($file === null) {
            throw UnableToReadFile::fromLocation($path, self::FILE_DOES_NOT_EXIST);
        }

        return $file['contents'];
    }

    /**
     * @return resource
     */
    public function readStream(string $path)
    {
        $contents = $this->read($path);

        $stream = \fopen('php://temp', 'r+');
        if ($stream === false) {
            throw UnableToReadFile::fromLocation($path, 'Unable to create temporary stream.');
        }

        \fwrite($stream, $contents);
        \rewind($stream);

        return $stream;
    }

    public function delete(string $path): void
    {
        $path = InMemoryPathHelper::normalizePath($path);
        $bucket = &$this->bucket();
        if (!isset($bucket['files'][$path])) {
            throw UnableToDeleteFile::atLocation($path, self::FILE_DOES_NOT_EXIST);
        }

        unset($bucket['files'][$path]);
    }

    public function deleteDirectory(string $path): void
    {
        $path = InMemoryPathHelper::normalizePath($path);
        if ($path === '') {
            throw UnableToDeleteDirectory::atLocation($path, 'The root directory cannot be deleted.');
        }

        $bucket = &$this->bucket();
        if (!isset($bucket['directories'][$path])) {
            throw UnableToDeleteDirectory::atLocation($path, 'Directory does not exist.');
        }

        $prefix = $path . '/';
        foreach (\array_keys($bucket['files']) as $filePath) {
            if ($filePath === $path || \str_starts_with($filePath, $prefix)) {
                unset($bucket['files'][$filePath]);
            }
        }

        foreach (\array_keys($bucket['directories']) as $directoryPath) {
            if ($directoryPath === $path || \str_starts_with($directoryPath, $prefix)) {
                unset($bucket['directories'][$directoryPath]);
            }
        }
    }

    public function createDirectory(string $path, Config $config): void
    {
        $path = InMemoryPathHelper::normalizePath($path);
        if ($path === '') {
            return;
        }

        $visibility = (string)$config->get('visibility', Visibility::PRIVATE);
        InMemoryPathHelper::assertVisibility($visibility);

        $segments = \explode('/', $path);
        $current = '';

        $bucket = &$this->bucket();
        foreach ($segments as $segment) {
            if ($segment === '') {
                continue;
            }
            $current = $current === '' ? $segment : $current . '/' . $segment;

            $bucket['directories'][$current] = [
                'visibility' => $visibility,
                'lastModified' => \time(),
            ];
        }
    }

    public function setVisibility(string $path, string $visibility): void
    {
        InMemoryPathHelper::assertVisibility($visibility);

        $path = InMemoryPathHelper::normalizePath($path);
        $bucket = &$this->bucket();

        if (isset($bucket['files'][$path])) {
            $bucket['files'][$path]['visibility'] = $visibility;

            return;
        }

        if (isset($bucket['directories'][$path])) {
            $bucket['directories'][$path]['visibility'] = $visibility;

            return;
        }

        throw UnableToRetrieveMetadata::visibility($path, 'Path does not exist.');
    }

    public function visibility(string $path): FileAttributes
    {
        $path = InMemoryPathHelper::normalizePath($path);
        $file = $this->bucket()['files'][$path] ?? null;
        if ($file === null) {
            throw UnableToRetrieveMetadata::visibility($path, self::FILE_DOES_NOT_EXIST);
        }

        return new FileAttributes($path, null, $file['visibility'], $file['lastModified']);
    }

    public function mimeType(string $path): FileAttributes
    {
        $path = InMemoryPathHelper::normalizePath($path);
        $file = $this->bucket()['files'][$path] ?? null;
        if ($file === null) {
            throw UnableToRetrieveMetadata::mimeType($path, self::FILE_DOES_NOT_EXIST);
        }

        $mimeType = \str_ends_with($path, '.xml') ? 'application/xml' : 'application/octet-stream';

        return new FileAttributes($path, null, $file['visibility'], $file['lastModified'], $mimeType);
    }

    public function lastModified(string $path): FileAttributes
    {
        $path = InMemoryPathHelper::normalizePath($path);
        $file = $this->bucket()['files'][$path] ?? null;
        if ($file === null) {
            throw UnableToRetrieveMetadata::lastModified($path, self::FILE_DOES_NOT_EXIST);
        }

        return new FileAttributes($path, null, $file['visibility'], $file['lastModified']);
    }

    public function fileSize(string $path): FileAttributes
    {
        $path = InMemoryPathHelper::normalizePath($path);
        $file = $this->bucket()['files'][$path] ?? null;
        if ($file === null) {
            throw UnableToRetrieveMetadata::fileSize($path, self::FILE_DOES_NOT_EXIST);
        }

        return new FileAttributes($path, \strlen($file['contents']), $file['visibility'], $file['lastModified']);
    }

    /**
     * @return iterable<StorageAttributes>
     */
    public function listContents(string $path, bool $deep): iterable
    {
        return InMemoryPathHelper::listContentsFromBucket($this->bucket(), $path, $deep);
    }

    public function move(string $source, string $destination, Config $config): void
    {
        $source = InMemoryPathHelper::normalizePath($source);
        $destination = InMemoryPathHelper::normalizePath($destination);

        if ($source === $destination) {
            throw UnableToMoveFile::sourceAndDestinationAreTheSame($source, $destination);
        }

        $bucket = &$this->bucket();
        $sourceFile = $bucket['files'][$source] ?? null;
        if ($sourceFile === null) {
            throw UnableToMoveFile::fromLocationTo($source, $destination);
        }

        $this->ensureParentDirectories($destination);
        $bucket['files'][$destination] = $sourceFile;
        $bucket['files'][$destination]['lastModified'] = \time();
        unset($bucket['files'][$source]);
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        $source = InMemoryPathHelper::normalizePath($source);
        $destination = InMemoryPathHelper::normalizePath($destination);

        if ($source === $destination) {
            throw UnableToCopyFile::sourceAndDestinationAreTheSame($source, $destination);
        }

        $bucket = &$this->bucket();
        $sourceFile = $bucket['files'][$source] ?? null;
        if ($sourceFile === null) {
            throw UnableToCopyFile::fromLocationTo($source, $destination);
        }

        $this->ensureParentDirectories($destination);
        $bucket['files'][$destination] = $sourceFile;
        $bucket['files'][$destination]['lastModified'] = \time();
    }

    private function ensureParentDirectories(string $path): void
    {
        $path = InMemoryPathHelper::normalizePath($path);
        $parent = \dirname($path);

        if ($parent === '.' || $parent === '') {
            return;
        }

        try {
            $this->createDirectory($parent, new Config());
        } catch (UnableToCreateDirectory $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    /**
     * @return array{files: array<string, array{contents: string, visibility: string, lastModified: int}>, directories: array<string, array{visibility: string, lastModified: int}>}
     */
    private function &bucket(): array
    {
        return self::$buckets[$this->bucketKey];
    }
}
