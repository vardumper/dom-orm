<?php
declare(strict_types=1);

namespace DOM\ORM\Storage;

use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use function DOM\ORM\getConfig;

class StorageService
{
    /**
     * @var resource|null
     */
    private $lockHandle = null;

    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly string $filename,
        private readonly ?string $lockFile = null,
    ) {
    }

    public function __destruct()
    {
        $this->unlock();
    }

    public static function fromConfig(): self
    {
        $config = getConfig();
        $adapterClass = $config->get('dom-orm.flysystem.adapter');
        $options = $config->get('dom-orm.flysystem.config');
        $adapter = new $adapterClass(...$options);
        $filename = (string)$config->get('dom-orm.filename');
        $lockFile = self::resolveLockFile(
            (string)$adapterClass,
            \is_array($options) ? $options : [],
            $filename,
            $config->get('dom-orm.lock_file')
        );

        return new self(new Filesystem($adapter), $filename, $lockFile);
    }

    public function read(): string
    {
        return $this->filesystem->read($this->filename);
    }

    public function write(string $contents): void
    {
        $this->filesystem->write($this->filename, $contents);
    }

    public function lock(): void
    {
        if ($this->lockFile === null || $this->lockHandle !== null) {
            return;
        }

        $dir = \dirname($this->lockFile);
        if (!\is_dir($dir) && !\mkdir($dir, 0755, true) && !\is_dir($dir)) {
            throw new \RuntimeException(\sprintf('Failed to create lock directory: %s', $dir));
        }

        $handle = \fopen($this->lockFile, 'c');
        if ($handle === false) {
            throw new \RuntimeException(\sprintf('Failed to open lock file: %s', $this->lockFile));
        }

        if (!\flock($handle, LOCK_EX)) {
            \fclose($handle);

            throw new \RuntimeException(\sprintf('Failed to acquire lock: %s', $this->lockFile));
        }

        $this->lockHandle = $handle;
    }

    public function unlock(): void
    {
        if ($this->lockHandle === null) {
            return;
        }

        \flock($this->lockHandle, LOCK_UN);
        \fclose($this->lockHandle);
        $this->lockHandle = null;
    }

    public function isLocked(): bool
    {
        return $this->lockHandle !== null;
    }

    /**
     * @param array<int|string, mixed> $options
     */
    private static function resolveLockFile(string $adapterClass, array $options, string $filename, ?string $configuredLockFile): ?string
    {
        if ($configuredLockFile !== null) {
            return $configuredLockFile;
        }

        if ($adapterClass !== LocalFilesystemAdapter::class) {
            return null;
        }

        $location = self::resolveLocalLocation($options);
        if ($location === null) {
            return null;
        }

        return \rtrim($location, '/\\') . DIRECTORY_SEPARATOR . $filename . '.lock';
    }

    /**
     * @param array<int|string, mixed> $options
     */
    private static function resolveLocalLocation(array $options): ?string
    {
        $location = $options['location'] ?? $options[0] ?? null;

        return \is_string($location) && $location !== '' ? $location : null;
    }
}
