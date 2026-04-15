<?php
declare(strict_types=1);

namespace DOM\ORM\Storage;

use League\Flysystem\Filesystem;
use function DOM\ORM\getConfig;

class StorageService
{
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly string $filename
    ) {
    }

    public static function fromConfig(): self
    {
        $config = getConfig();
        $adapterClass = $config->get('dom-orm.flysystem.adapter');
        $options = $config->get('dom-orm.flysystem.config');
        $adapter = new $adapterClass(...$options);

        return new self(new Filesystem($adapter), $config->get('dom-orm.filename'));
    }

    public function read(): string
    {
        return $this->filesystem->read($this->filename);
    }

    public function write(string $contents): void
    {
        $this->filesystem->write($this->filename, $contents);
    }
}
