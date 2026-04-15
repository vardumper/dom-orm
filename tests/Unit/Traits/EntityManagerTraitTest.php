<?php

declare(strict_types=1);

use DOM\ORM\Traits\EntityManagerTrait;
use Tests\Fixtures\Tag;

// Minimal concrete class that exposes EntityManagerTrait for testing
class TestEntityManager
{
    use EntityManagerTrait;
}

// Integration tests – same storage isolation pattern as EntityRepositoryTest
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

    $this->manager = new TestEntityManager();
});

afterEach(function (): void {
    if (\file_exists($this->storageFile)) {
        \unlink($this->storageFile);
    }
    if (\file_exists($this->storageBackup)) {
        \rename($this->storageBackup, $this->storageFile);
    }
});

it('persist writes a tag entity to the XML store', function (): void {
    $tag = new Tag('Hello', 'test-persist-id');
    $this->manager->persist($tag);

    $xml = \file_get_contents($this->storageFile);
    expect($xml)->toContain('test-persist-id');
    expect($xml)->toContain('Hello');
    expect($xml)->toContain('type="tag"');
});

it('persist writes multiple entities to the XML store', function (): void {
    $this->manager->persist(new Tag('First', 'id-first'));
    $this->manager->persist(new Tag('Second', 'id-second'));

    $xml = \file_get_contents($this->storageFile);
    expect($xml)->toContain('id-first');
    expect($xml)->toContain('id-second');
});

it('removeById removes the entity from the XML store', function (): void {
    $tag = new Tag('ToRemove', 'remove-me');
    $this->manager->persist($tag);

    $xmlBefore = \file_get_contents($this->storageFile);
    expect($xmlBefore)->toContain('remove-me');

    $this->manager->removeById('remove-me');

    $xmlAfter = \file_get_contents($this->storageFile);
    expect($xmlAfter)->not->toContain('remove-me');
});

it('save writes the current DOM state to storage', function (): void {
    $tag = new Tag('SaveTest', 'save-id');
    $this->manager->persist($tag);

    // Verify storage has the content written by save() inside persist()
    $xml = \file_get_contents($this->storageFile);
    expect($xml)->toContain('save-id');
});
