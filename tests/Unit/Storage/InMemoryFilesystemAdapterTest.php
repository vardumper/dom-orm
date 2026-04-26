<?php

declare(strict_types=1);

use DOM\ORM\Storage\InMemoryFilesystemAdapter;
use League\Flysystem\Config;
use League\Flysystem\FileAttributes;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToReadFile;

it('writes and reads xml in memory', function (): void {
    $adapter = new InMemoryFilesystemAdapter('pest-in-memory-a');
    $adapter->write('data.xml', '<data />', new Config());

    expect($adapter->read('data.xml'))->toBe('<data />');
});

it('supports move and copy operations', function (): void {
    $adapter = new InMemoryFilesystemAdapter('pest-in-memory-b');
    $adapter->write('data.xml', '<data><item /></data>', new Config());

    $adapter->copy('data.xml', 'copy.xml', new Config());
    $adapter->move('copy.xml', 'moved.xml', new Config());

    expect($adapter->read('data.xml'))->toBe('<data><item /></data>')
        ->and($adapter->read('moved.xml'))->toBe('<data><item /></data>')
        ->and(fn () => $adapter->read('copy.xml'))->toThrow(UnableToReadFile::class);
});

it('lists directory contents', function (): void {
    $adapter = new InMemoryFilesystemAdapter('pest-in-memory-c');
    $adapter->write('pages/home.xml', '<data />', new Config());
    $adapter->write('pages/about.xml', '<data />', new Config());

    $items = \iterator_to_array($adapter->listContents('pages', false));

    expect($items)->toHaveCount(2)
        ->and($items[0] instanceof FileAttributes || $items[1] instanceof FileAttributes)->toBeTrue();

    $paths = \array_map(static fn (StorageAttributes $a): string => $a->path(), $items);
    \sort($paths);

    expect($paths)->toBe(['pages/about.xml', 'pages/home.xml']);
});
