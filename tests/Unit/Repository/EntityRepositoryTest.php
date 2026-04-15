<?php

declare(strict_types=1);

use DOM\ORM\Repository\EntityRepository;
use Tests\Fixtures\Tag;

// These are integration tests: EntityRepository calls init() → StorageService::fromConfig()
// which reads/writes getcwd()/storage/data.xml. We back up any existing file and restore
// it after each test so real data is never destroyed.

beforeEach(function (): void {
    $this->storageFile = \getcwd() . '/storage/data.xml';
    $this->storageBackup = $this->storageFile . '.bak';

    if (!\is_dir(\dirname($this->storageFile))) {
        \mkdir(\dirname($this->storageFile), 0755, true);
    }

    if (\file_exists($this->storageFile)) {
        \rename($this->storageFile, $this->storageBackup);
    }

    \file_put_contents($this->storageFile, '<data />');
});

afterEach(function (): void {
    if (\file_exists($this->storageFile)) {
        \unlink($this->storageFile);
    }
    if (\file_exists($this->storageBackup)) {
        \rename($this->storageBackup, $this->storageFile);
    }
});

it('constructs with an entity class string and resolves entity type', function (): void {
    $repo = new EntityRepository(Tag::class);
    expect($repo->getEntityType())->toBe('tag');
});

it('throws when constructed with a class that has no Item attribute', function (): void {
    expect(fn () => new EntityRepository(\stdClass::class))->toThrow(\Exception::class);
});

it('findAll returns null when the store is empty', function (): void {
    $repo = new EntityRepository(Tag::class);
    expect($repo->findAll())->toBeNull();
});

it('find returns null when the entity does not exist', function (): void {
    $repo = new EntityRepository(Tag::class);
    expect($repo->find('nonexistent-id'))->toBeNull();
});

it('findOneBy returns null when no entity matches', function (): void {
    $repo = new EntityRepository(Tag::class);
    expect($repo->findOneBy([
        'name' => 'Ghost',
    ]))->toBeNull();
});
