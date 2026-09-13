<?php

declare(strict_types=1);

use DOM\ORM\Repository\EntityRepository;
use DOM\ORM\Storage\QueryCache;
use DOM\ORM\Storage\StorageService;
use DOM\ORM\Traits\EntityManagerTrait;
use Tests\Fixtures\RelCompany;

final class StaleCacheEntityManager
{
    use EntityManagerTrait;
}

function staleLocation(): string
{
    $location = getcwd() . '/storage/cache-stale-' . bin2hex(random_bytes(6));
    if (!is_dir($location)) {
        mkdir($location, 0755, true);
    }
    // Seed an empty data file so build()/persist have something to read.
    file_put_contents($location . '/stale-data.xml', '<data />');

    return $location;
}

function staleDataFile(string $location): string
{
    return $location . '/stale-data.xml';
}

function isolateCacheEnv(string $location): array
{
    $saved = [];
    foreach (['DOM_ORM_FLYSYSTEM_LOCATION', 'DOM_ORM_FILENAME', 'DOM_ORM_CACHE_PATH', 'DOM_ORM_CACHE_STRATEGY'] as $name) {
        $saved[$name] = getenv($name);
    }

    putenv('DOM_ORM_FLYSYSTEM_LOCATION=' . $location);
    putenv('DOM_ORM_FILENAME=stale-data.xml');
    putenv('DOM_ORM_CACHE_PATH=' . $location . '/cache.php');
    putenv('DOM_ORM_CACHE_STRATEGY=on_persist');

    return $saved;
}

function restoreCacheEnv(array $saved): void
{
    foreach ($saved as $name => $value) {
        if ($value === false || $value === '') {
            putenv($name);
        } else {
            putenv($name . '=' . $value);
        }
    }
}

function cleanupLocation(string $location): void
{
    foreach (\glob($location . '/*') ?: [] as $file) {
        if (\is_file($file) || \is_link($file)) {
            \unlink($file);
        }
    }
    if (\is_dir($location)) {
        \rmdir($location);
    }
}

/**
 * EntityManagerTrait caches its StorageService (and serializer) in a static,
 * initialized once per process via `??=`. Because this test runs with an
 * isolated DOM_ORM_FLYSYSTEM_LOCATION, we must clear that shared state so later
 * tests re-initialize against the default location instead of our temp dir.
 *
 * The statics live on each *using* class (not on the trait itself): the
 * `EntityRepository` used below initializes `AbstractEntityRepository::$shared*`
 * against our temp location, and a lingering value there would make the next
 * repository in another test read from the temp dir. Resetting on the using
 * classes (verified: resetting via the trait's ReflectionClass is a no-op)
 * forces re-initialization.
 *
 * `setAccessible()` is unnecessary on PHP >= 8.1 (it is a no-op for private
 * members reflected from a using class).
 */
function resetSharedEntityManagerState(): void
{
    foreach ([
        \DOM\ORM\Repository\AbstractEntityRepository::class,
        StaleCacheEntityManager::class,
    ] as $usingClass) {
        foreach (['sharedStorage', 'sharedSerializer'] as $propertyName) {
            try {
                $property = new \ReflectionProperty($usingClass, $propertyName);
                $property->setValue(null, null);
            } catch (\ReflectionException) {
                // Property does not exist on this class — nothing to reset.
            }
        }
    }
}

function finishTest(string $location, array $saved): void
{
    cleanupLocation($location);
    resetSharedEntityManagerState();
    restoreCacheEnv($saved);
}

it('serves externally-modified data instead of a stale cache', function (): void {
    $location = staleLocation();
    $saved = isolateCacheEnv($location);

    try {
        // persist() builds the query cache from content A (on_persist strategy).
        (new StaleCacheEntityManager())->persist(new RelCompany('Acme Corp', 'company-1'));

        $repo = new EntityRepository(RelCompany::class);
        expect($repo->find('company-1')?->getName())->toBe('Acme Corp');

        // Simulate a cron swap: rewrite the data file directly, bypassing persist().
        $contents = (string)\file_get_contents(staleDataFile($location));
        \file_put_contents(staleDataFile($location), \str_replace('Acme Corp', 'Renamed Corp', $contents));

        // A fresh repository must detect the stale cache and serve content B.
        $repo2 = new EntityRepository(RelCompany::class);
        expect($repo2->find('company-1')?->getName())->toBe('Renamed Corp');
    } finally {
        finishTest($location, $saved);
    }
})->group('integration');

it('rebuilds the cache when the stored fingerprint no longer matches', function (): void {
    $location = staleLocation();
    $saved = isolateCacheEnv($location);

    try {
        QueryCache::build();
        $cache = (require $location . '/cache.php');
        $originalHash = $cache['__meta']['hash'];

        // Tamper the stored hash to simulate a cache written for different data.
        // The data file's mtime is backdated so the stat-first fast path is
        // bypassed (size + mtime differ from the stored meta) and the hash
        // comparison actually runs — a tampered hash alone with unchanged
        // size + mtime is intentionally treated as "file unchanged" now.
        $cache['__meta']['hash'] = \str_repeat('0', 64);
        \file_put_contents($location . '/cache.php', "<?php\n\nreturn " . \var_export($cache, true) . ";\n");
        \touch(staleDataFile($location), \time() - 3600);

        $loaded = QueryCache::load();
        expect($loaded)->not->toBeNull();
        expect($loaded['__meta']['hash'])->toBe($originalHash);          // rebuilt to the correct hash
        expect($loaded['__meta']['hash'])->not->toBe(\str_repeat('0', 64));
    } finally {
        finishTest($location, $saved);
    }
})->group('integration');

it('treats a legacy cache without a fingerprint as stale', function (): void {
    $location = staleLocation();
    $saved = isolateCacheEnv($location);

    try {
        QueryCache::build();
        $cache = (require $location . '/cache.php');
        unset($cache['__meta']); // emulate an old cache predating the fingerprint feature
        \file_put_contents($location . '/cache.php', "<?php\n\nreturn " . \var_export($cache, true) . ";\n");

        $loaded = QueryCache::load();
        expect($loaded)->not->toBeNull();
        expect($loaded['__meta']['hash'])->toBe(StorageService::fromConfig()->fingerprint()['hash']);
    } finally {
        finishTest($location, $saved);
    }
})->group('integration');

it('serves a cache whose fingerprint still matches without rebuilding', function (): void {
    $location = staleLocation();
    $saved = isolateCacheEnv($location);

    try {
        QueryCache::build();
        $before = (require $location . '/cache.php');

        $loaded = QueryCache::load();
        expect($loaded)->not->toBeNull();
        // Identical fingerprint => no rebuild, meta untouched.
        expect($loaded['__meta']['hash'])->toBe($before['__meta']['hash']);
    } finally {
        finishTest($location, $saved);
    }
})->group('integration');
