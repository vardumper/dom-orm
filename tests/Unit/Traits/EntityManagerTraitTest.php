<?php

declare(strict_types=1);

use DOM\ORM\Traits\EntityManagerTrait;
use Tests\Fixtures\Tag;

// Minimal concrete class that exposes EntityManagerTrait for testing
class TestEntityManager
{
    use EntityManagerTrait;
}

$storageFile = null;
$storageBackup = null;
$lockFile = null;
$configFile = null;
$configBackup = null;
$manager = null;

function resetEntityManagerSharedState(): void
{
    $reflection = new \ReflectionClass(TestEntityManager::class);

    foreach (['sharedStorage', 'sharedSerializer'] as $propertyName) {
        $property = $reflection->getProperty($propertyName);
        $property->setAccessible(true);
        $property->setValue(null);
    }
}

// Integration tests – same storage isolation pattern as EntityRepositoryTest
beforeEach(function () use (&$storageFile, &$storageBackup, &$lockFile, &$configFile, &$configBackup, &$manager): void {
    $storageFile = \getcwd() . '/storage/data.xml';
    $storageBackup = $storageFile . '.bak';
    $lockFile = $storageFile . '.lock';
    $configFile = \getcwd() . '/dom-orm.php';
    $configBackup = $configFile . '.bak';

    if (!\is_dir(\dirname($storageFile))) {
        \mkdir(\dirname($storageFile), 0755, true);
    }

    if (\file_exists($configFile)) {
        \rename($configFile, $configBackup);
    }

    if (\file_exists($storageFile)) {
        \rename($storageFile, $storageBackup);
    }

    \file_put_contents($storageFile, '<data />');

    resetEntityManagerSharedState();
    $manager = new TestEntityManager();
});

afterEach(function () use (&$storageFile, &$storageBackup, &$lockFile, &$configFile, &$configBackup, &$manager): void {
    if (\is_string($storageFile) && \file_exists($storageFile)) {
        \unlink($storageFile);
    }
    if (\is_string($storageBackup) && \is_string($storageFile) && \file_exists($storageBackup)) {
        \rename($storageBackup, $storageFile);
    }
    foreach ([
        $lockFile,
        \getcwd() . '/storage/cache.php',
        \getcwd() . '/storage/export.json',
        \getcwd() . '/storage/export.yaml',
        \getcwd() . '/storage/export.php',
        \getcwd() . '/storage/export.xml',
    ] as $artifact) {
        if (\file_exists($artifact)) {
            \unlink($artifact);
        }
    }
    if (\is_string($configFile) && \file_exists($configFile)) {
        \unlink($configFile);
    }
    if (\is_string($configBackup) && \is_string($configFile) && \file_exists($configBackup)) {
        \rename($configBackup, $configFile);
    }
    resetEntityManagerSharedState();
    $manager = null;
});

it('persist writes a tag entity to the XML store', function () use (&$storageFile, &$manager): void {
    if (!\is_string($storageFile) || !$manager instanceof TestEntityManager) {
        throw new \RuntimeException('Entity manager test fixture was not initialized.');
    }

    $tag = new Tag('Hello', 'test-persist-id');
    $manager->persist($tag);

    $xml = \file_get_contents($storageFile);
    expect($xml)->toContain('test-persist-id');
    expect($xml)->toContain('Hello');
    expect($xml)->toContain('type="tag"');
});

it('persist writes multiple entities to the XML store', function () use (&$storageFile, &$manager): void {
    if (!\is_string($storageFile) || !$manager instanceof TestEntityManager) {
        throw new \RuntimeException('Entity manager test fixture was not initialized.');
    }

    $manager->persist(new Tag('First', 'id-first'));
    $manager->persist(new Tag('Second', 'id-second'));

    $xml = \file_get_contents($storageFile);
    expect($xml)->toContain('id-first');
    expect($xml)->toContain('id-second');
});

it('removeById removes the entity from the XML store', function () use (&$storageFile, &$manager): void {
    if (!\is_string($storageFile) || !$manager instanceof TestEntityManager) {
        throw new \RuntimeException('Entity manager test fixture was not initialized.');
    }

    $tag = new Tag('ToRemove', 'remove-me');
    $manager->persist($tag);

    $xmlBefore = \file_get_contents($storageFile);
    expect($xmlBefore)->toContain('remove-me');

    $manager->removeById('remove-me');

    $xmlAfter = \file_get_contents($storageFile);
    expect($xmlAfter)->not->toContain('remove-me');
});

it('save writes the current DOM state to storage', function () use (&$storageFile, &$manager): void {
    if (!\is_string($storageFile) || !$manager instanceof TestEntityManager) {
        throw new \RuntimeException('Entity manager test fixture was not initialized.');
    }

    $tag = new Tag('SaveTest', 'save-id');
    $manager->persist($tag);

    // Verify storage has the content written by save() inside persist()
    $xml = \file_get_contents($storageFile);
    expect($xml)->toContain('save-id');
});

it('persist creates the local lock file', function () use (&$lockFile, &$manager): void {
    if (!\is_string($lockFile) || !$manager instanceof TestEntityManager) {
        throw new \RuntimeException('Entity manager test fixture was not initialized.');
    }

    $manager->persist(new Tag('LockTest', 'lock-id'));

    expect($lockFile)->toBeFile();
});

it('save-time cache rebuilds can export configured formats', function () use (&$configFile, &$manager): void {
    if (!\is_string($configFile)) {
        throw new \RuntimeException('Entity manager config fixture was not initialized.');
    }

    \file_put_contents($configFile, '<?php return ' . \var_export([
        'dom-orm' => [
            'cache_path' => \getcwd() . '/storage/cache.php',
            'cache_strategy' => 'on_persist',
            'export_on_persist' => [
                'json' => true,
                'php' => true,
            ],
        ],
    ], true) . ';');

    resetEntityManagerSharedState();
    $manager = new TestEntityManager();
    $manager->persist(new Tag('Exported', 'export-id'));

    expect(\getcwd() . '/storage/cache.php')->toBeFile();
    expect(\getcwd() . '/storage/export.json')->toBeFile();
    expect(\getcwd() . '/storage/export.php')->toBeFile();
    expect((string)\file_get_contents(\getcwd() . '/storage/export.json'))->toContain('export-id');
});
