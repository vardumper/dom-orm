<?php
declare(strict_types=1);

namespace DOM\ORM\Storage;

use League\Flysystem\Filesystem;
use function DOM\ORM\getConfig;

class StorageService
{
    protected readonly Filesystem $filesystem;

    protected readonly string $filename;

    public function __construct()
    {
        $config = getConfig();
        $adapterClass = $config->get('dom-orm.flysystem.adapter');
        $options = $config->get('dom-orm.flysystem.config');
        $adapter = new $adapterClass(...$options);
        $this->filename = $config->get('dom-orm.filename');
        $this->filesystem = new Filesystem($adapter);
    }

    public function read(): string
    {
        return $this->filesystem->read($this->filename);
    }

    public function write($contents): void
    {
        $this->filesystem->write($this->filename, $contents);
    }
}
