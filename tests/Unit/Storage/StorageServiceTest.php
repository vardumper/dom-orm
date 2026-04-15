<?php

declare(strict_types=1);

use DOM\ORM\Storage\StorageService;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToReadFile;

// Each test gets its own isolated temp directory
beforeEach(function (): void {
    $this->tmpDir = \sys_get_temp_dir() . '/dom-orm-test-' . \uniqid('', true);
    \mkdir($this->tmpDir, 0755, true);
    $this->storage = new StorageService(
        new Filesystem(new LocalFilesystemAdapter($this->tmpDir)),
        'test.xml'
    );
});

afterEach(function (): void {
    \array_map('unlink', (array)\glob($this->tmpDir . '/*'));
    if (\is_dir($this->tmpDir)) {
        \rmdir($this->tmpDir);
    }
});

it('write creates a file that read returns', function (): void {
    $this->storage->write('<data />');
    expect($this->storage->read())->toBe('<data />');
});

it('write overwrites existing content', function (): void {
    $this->storage->write('<data>first</data>');
    $this->storage->write('<data>second</data>');
    expect($this->storage->read())->toBe('<data>second</data>');
});

it('read throws when file does not exist', function (): void {
    expect(fn () => $this->storage->read())->toThrow(UnableToReadFile::class);
});

it('fromConfig returns a StorageService instance', function (): void {
    // fromConfig uses getcwd()/storage – just verify the return type
    expect(StorageService::fromConfig())->toBeInstanceOf(StorageService::class);
});
