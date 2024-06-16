<?php
declare(strict_types=1);

namespace DOM\ORM\Storage;

use League\Flysystem\Filesystem;
use function DOM\ORM\getConfig;

class StorageService
{
    protected readonly Filesystem $filesystem;

    protected readonly string $location;

    public function __construct()
    {
        $config = getConfig();
        $adapterClass = $config->get('dom-orm.flysystem.adapter');
        $options = $config->get('dom-orm.flysystem.config');
        $adapter = new $adapterClass(extract($options));
        $this->location = $config->get('dom-orm.location');
        $this->filesystem = new Filesystem($adapter);
    }

    public function read(): string
    {
        return $this->filesystem->read($this->location);
    }

    public function write($contents): void
    {
        $this->filesystem->write($this->location, $contents);
    }
}
