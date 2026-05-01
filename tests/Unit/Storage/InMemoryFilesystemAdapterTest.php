<?php

declare(strict_types=1);

use DOM\ORM\Storage\InMemoryFilesystemAdapter;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\InvalidVisibilityProvided;
use League\Flysystem\StorageAttributes;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToDeleteDirectory;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToWriteFile;
use League\Flysystem\Visibility;

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

it('resets only the targeted in-memory bucket', function (): void {
    $a = new InMemoryFilesystemAdapter('pest-in-memory-reset-a');
    $b = new InMemoryFilesystemAdapter('pest-in-memory-reset-b');

    $a->write('a.xml', '<a />', new Config());
    $b->write('b.xml', '<b />', new Config());

    InMemoryFilesystemAdapter::reset('pest-in-memory-reset-a');

    expect(fn () => $a->read('a.xml'))->toThrow(\TypeError::class)
        ->and($b->read('b.xml'))->toBe('<b />');
});

it('supports stream read and write operations', function (): void {
    $adapter = new InMemoryFilesystemAdapter('pest-in-memory-stream');

    $writeStream = \fopen('php://temp', 'r+');
    \fwrite($writeStream, '<data><from>stream</from></data>');
    \rewind($writeStream);

    $adapter->writeStream('stream.xml', $writeStream, new Config());
    \fclose($writeStream);

    $readStream = $adapter->readStream('stream.xml');
    $contents = \stream_get_contents($readStream);
    \fclose($readStream);

    expect($contents)->toBe('<data><from>stream</from></data>');
});

it('throws on invalid write stream input', function (): void {
    $adapter = new InMemoryFilesystemAdapter('pest-in-memory-invalid-stream');

    expect(fn () => $adapter->writeStream('broken.xml', 'not-a-resource', new Config()))
        ->toThrow(UnableToWriteFile::class);
});

it('throws when writing to an empty path', function (): void {
    $adapter = new InMemoryFilesystemAdapter('pest-in-memory-empty-write');

    expect(fn () => $adapter->write('/', '<data />', new Config()))
        ->toThrow(UnableToWriteFile::class);
});

it('throws when deleting missing file or directory', function (): void {
    $adapter = new InMemoryFilesystemAdapter('pest-in-memory-delete-missing');

    expect(fn () => $adapter->delete('missing.xml'))->toThrow(UnableToDeleteFile::class)
        ->and(fn () => $adapter->deleteDirectory('missing-dir'))->toThrow(UnableToDeleteDirectory::class);
});

it('deletes nested directories recursively and blocks root deletion', function (): void {
    $adapter = new InMemoryFilesystemAdapter('pest-in-memory-delete-dir');
    $adapter->write('pages/nested/one.xml', '<one />', new Config());
    $adapter->write('pages/nested/two.xml', '<two />', new Config());

    expect($adapter->directoryExists('pages/nested'))->toBeTrue();

    $adapter->deleteDirectory('pages');

    expect($adapter->directoryExists('pages'))->toBeFalse()
        ->and(fn () => $adapter->read('pages/nested/one.xml'))->toThrow(UnableToReadFile::class)
        ->and(fn () => $adapter->deleteDirectory(''))->toThrow(UnableToDeleteDirectory::class);
});

it('handles visibility updates for files and directories', function (): void {
    $adapter = new InMemoryFilesystemAdapter('pest-in-memory-visibility');
    $adapter->createDirectory('private-dir', new Config([
        'visibility' => Visibility::PRIVATE,
    ]));
    $adapter->write('private-dir/entry.xml', '<entry />', new Config([
        'visibility' => Visibility::PRIVATE,
    ]));

    $adapter->setVisibility('private-dir/entry.xml', Visibility::PUBLIC);

    expect($adapter->visibility('private-dir/entry.xml')->visibility())->toBe(Visibility::PUBLIC);

    $adapter->setVisibility('private-dir', Visibility::PUBLIC);

    $items = \iterator_to_array($adapter->listContents('', true));
    $dir = null;
    foreach ($items as $item) {
        if ($item instanceof DirectoryAttributes && $item->path() === 'private-dir') {
            $dir = $item;
            break;
        }
    }

    expect($dir)->toBeInstanceOf(DirectoryAttributes::class)
        ->and($dir?->visibility())->toBe(Visibility::PUBLIC)
        ->and(fn () => $adapter->setVisibility('missing', Visibility::PUBLIC))->toThrow(UnableToRetrieveMetadata::class)
        ->and(fn () => $adapter->setVisibility('private-dir/entry.xml', 'invalid'))->toThrow(InvalidVisibilityProvided::class);
});

it('returns file metadata and validates missing-path metadata calls', function (): void {
    $adapter = new InMemoryFilesystemAdapter('pest-in-memory-metadata');
    $adapter->write('meta.bin', 'abc', new Config());

    expect($adapter->fileSize('meta.bin')->fileSize())->toBe(3)
        ->and($adapter->mimeType('meta.bin')->mimeType())->toBe('application/octet-stream')
        ->and($adapter->lastModified('meta.bin')->lastModified())->toBeInt()
        ->and(fn () => $adapter->visibility('missing.bin'))->toThrow(UnableToRetrieveMetadata::class)
        ->and(fn () => $adapter->mimeType('missing.bin'))->toThrow(UnableToRetrieveMetadata::class)
        ->and(fn () => $adapter->lastModified('missing.bin'))->toThrow(UnableToRetrieveMetadata::class)
        ->and(fn () => $adapter->fileSize('missing.bin'))->toThrow(UnableToRetrieveMetadata::class);
});

it('prevents copy and move to the same path or from missing source', function (): void {
    $adapter = new InMemoryFilesystemAdapter('pest-in-memory-copy-move-errors');
    $adapter->write('exists.xml', '<data />', new Config());

    expect(fn () => $adapter->copy('exists.xml', 'exists.xml', new Config()))->toThrow(UnableToCopyFile::class)
        ->and(fn () => $adapter->move('exists.xml', 'exists.xml', new Config()))->toThrow(UnableToMoveFile::class)
        ->and(fn () => $adapter->copy('missing.xml', 'new.xml', new Config()))->toThrow(UnableToCopyFile::class)
        ->and(fn () => $adapter->move('missing.xml', 'new.xml', new Config()))->toThrow(UnableToMoveFile::class);
});

it('lists deep contents from nested directories', function (): void {
    $adapter = new InMemoryFilesystemAdapter('pest-in-memory-deep-list');
    $adapter->write('articles/2026/may.xml', '<may />', new Config());

    $shallow = \iterator_to_array($adapter->listContents('articles', false));
    $deep = \iterator_to_array($adapter->listContents('articles', true));

    $shallowPaths = \array_map(static fn (StorageAttributes $a): string => $a->path(), $shallow);
    $deepPaths = \array_map(static fn (StorageAttributes $a): string => $a->path(), $deep);
    \sort($shallowPaths);
    \sort($deepPaths);

    expect($shallowPaths)->toBe(['articles/2026'])
        ->and($deepPaths)->toBe(['articles/2026', 'articles/2026/may.xml']);
});
