<?php

declare(strict_types=1);

use DOM\ORM\Repository\EntityRepository;
use DOM\ORM\Storage\QueryCache;
use DOM\ORM\Traits\EntityManagerTrait;
use Tests\Fixtures\Tag;

final class CacheRepoManager
{
    use EntityManagerTrait;
}

function cacheRepoLocation(): string
{
    $location = \getcwd() . '/storage/cache-repo-' . \bin2hex(\random_bytes(6));
    if (!\is_dir($location)) {
        \mkdir($location, 0755, true);
    }

    \file_put_contents($location . '/repo-data.xml', '<data />');

    return $location;
}

function isolateCacheRepoEnv(string $location): array
{
    $saved = [];
    foreach (['DOM_ORM_FLYSYSTEM_LOCATION', 'DOM_ORM_FILENAME', 'DOM_ORM_CACHE_PATH', 'DOM_ORM_CACHE_STRATEGY'] as $name) {
        $saved[$name] = \getenv($name);
    }

    \putenv('DOM_ORM_FLYSYSTEM_LOCATION=' . $location);
    \putenv('DOM_ORM_FILENAME=repo-data.xml');
    \putenv('DOM_ORM_CACHE_PATH=' . $location . '/cache.php');
    \putenv('DOM_ORM_CACHE_STRATEGY=on_persist');

    return $saved;
}

function restoreCacheRepoEnv(array $saved): void
{
    foreach ($saved as $name => $value) {
        if ($value === false || $value === '') {
            \putenv($name);
        } else {
            \putenv($name . '=' . $value);
        }
    }
}

function cleanupCacheRepoLocation(string $location): void
{
    if (!\is_dir($location)) {
        return;
    }

    $iterator = new \RecursiveIteratorIterator(
        new \RecursiveDirectoryIterator($location, \FilesystemIterator::SKIP_DOTS),
        \RecursiveIteratorIterator::CHILD_FIRST,
    );
    foreach ($iterator as $item) {
        $item->isDir() ? \rmdir($item->getPathname()) : \unlink($item->getPathname());
    }

    \rmdir($location);
}

function resetSharedRepoState(): void
{
    foreach ([\DOM\ORM\Repository\AbstractEntityRepository::class, CacheRepoManager::class] as $usingClass) {
        foreach (['sharedStorage', 'sharedSerializer'] as $propertyName) {
            try {
                $property = new \ReflectionProperty($usingClass, $propertyName);
                $property->setValue(null, null);
            } catch (\ReflectionException) {
                /** Property does not exist on this class — nothing to reset. */
            }
        }
    }
}

function seedTags(): void
{
    $manager = new CacheRepoManager();
    $manager->persist(new Tag('Alpha', 'tag-a'));
    $manager->persist(new Tag('Beta', 'tag-b'));
    $manager->persist(new Tag('Gamma', 'tag-c'));
    QueryCache::build();
}

it('findAll serves every entity from the chunked cache', function (): void {
    $location = cacheRepoLocation();
    $saved = isolateCacheRepoEnv($location);

    try {
        seedTags();
        $names = \array_map(static fn (Tag $tag) => $tag->getName(), (new EntityRepository(Tag::class))->findAll()->toArray());
        \sort($names);
        expect($names)->toBe(['Alpha', 'Beta', 'Gamma']);
    } finally {
        cleanupCacheRepoLocation($location);
        resetSharedRepoState();
        restoreCacheRepoEnv($saved);
    }
})->group('integration');

it('find resolves an entity from the cache and null for a missing id', function (): void {
    $location = cacheRepoLocation();
    $saved = isolateCacheRepoEnv($location);

    try {
        seedTags();
        $repo = new EntityRepository(Tag::class);
        expect($repo->find('tag-b')?->getName())->toBe('Beta');
        expect($repo->find('missing'))->toBeNull();
    } finally {
        cleanupCacheRepoLocation($location);
        resetSharedRepoState();
        restoreCacheRepoEnv($saved);
    }
})->group('integration');

it('findBy and findOneBy answer from the inverted index', function (): void {
    $location = cacheRepoLocation();
    $saved = isolateCacheRepoEnv($location);

    try {
        seedTags();
        $repo = new EntityRepository(Tag::class);

        expect($repo->findBy([
            'name' => 'Beta',
        ])->count())->toBe(1);
        expect($repo->findBy([
            'name' => 'Beta',
        ])->first()->getName())->toBe('Beta');
        expect($repo->findBy([
            'name' => 'Ghost',
        ]))->toBeNull();
        expect($repo->findOneBy([
            'name' => 'Gamma',
        ])?->getName())->toBe('Gamma');
        expect($repo->findOneBy([
            'name' => 'Ghost',
        ]))->toBeNull();
    } finally {
        cleanupCacheRepoLocation($location);
        resetSharedRepoState();
        restoreCacheRepoEnv($saved);
    }
})->group('integration');

it('findAllLazy and findByLazy return lazy collections backed by the cache', function (): void {
    $location = cacheRepoLocation();
    $saved = isolateCacheRepoEnv($location);

    try {
        seedTags();
        $repo = new EntityRepository(Tag::class);

        $all = $repo->findAllLazy();
        expect($all)->not->toBeNull();
        expect($all->count())->toBe(3);

        $lazy = $repo->findByLazy([
            'name' => 'Alpha',
        ]);
        expect($lazy)->not->toBeNull();
        expect($lazy->count())->toBe(1);
        expect($lazy->first()->getName())->toBe('Alpha');
    } finally {
        cleanupCacheRepoLocation($location);
        resetSharedRepoState();
        restoreCacheRepoEnv($saved);
    }
})->group('integration');

it('findAllLazy returns null when the cache has no ids for the type', function (): void {
    $location = cacheRepoLocation();
    $saved = isolateCacheRepoEnv($location);

    try {
        seedTags();
        /** A type with no stored items has an empty id list in the cache. */
        expect((new EntityRepository(Tag::class))->findAllLazy())->not->toBeNull();
        expect((new EntityRepository(\Tests\Fixtures\RelCompany::class))->findAllLazy())->toBeNull();
    } finally {
        cleanupCacheRepoLocation($location);
        resetSharedRepoState();
        restoreCacheRepoEnv($saved);
    }
})->group('integration');
