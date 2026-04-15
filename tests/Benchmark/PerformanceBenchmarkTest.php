<?php

declare(strict_types=1);

use DOM\ORM\Repository\EntityRepository;
use DOM\ORM\Serializer\Encoder\SchemaEncoder;
use DOM\ORM\Serializer\Normalizer\SchemaNormalizer;
use DOM\ORM\Storage\QueryCache;
use Tests\Fixtures\Tag;
use Tests\Fixtures\Tag as BenchTag;

/**
 * Measures elapsed time in milliseconds for $n repetitions of $fn.
 */
function bench(callable $fn, int $n): float
{
    $start = hrtime(true);
    for ($i = 0; $i < $n; $i++) {
        $fn();
    }

    return (hrtime(true) - $start) / 1_000_000;
}

/**
 * Resets all AttributeResolverTrait static caches on the given class.
 * This simulates a cold-cache state without restarting the PHP process.
 *
 * @param class-string $usingClass A class that uses AttributeResolverTrait
 */
function clearAttributeResolverCache(string $usingClass): void
{
    $nullableProps = ['entityTypeToClassMap'];
    $arrayProps = ['entityTypeByClass', 'allowedParentPathsByClass', 'fragmentsByClass', 'groupsByClass'];

    foreach ($nullableProps as $prop) {
        $rp = new \ReflectionProperty($usingClass, $prop);
        $rp->setValue(null, null);
    }

    foreach ($arrayProps as $prop) {
        $rp = new \ReflectionProperty($usingClass, $prop);
        $rp->setValue(null, []);
    }
}

// ---------------------------------------------------------------------------
// Normalization
// ---------------------------------------------------------------------------

it('normalizes 500 entities within time budget', function (): void {
    $normalizer = new SchemaNormalizer();
    $tag = new Tag('BenchTag', 'bench-001', new \DateTimeImmutable('2024-06-01T12:00:00+00:00'));

    $ms = bench(fn () => $normalizer->normalize($tag, SchemaNormalizer::FORMAT), 500);

    expect($ms)->toBeLessThan(1_000, "500 normalizations took {$ms}ms (budget: 1 000ms)");
})->group('benchmark');

// ---------------------------------------------------------------------------
// Encoding
// ---------------------------------------------------------------------------

it('encodes 500 entity arrays to XML within time budget', function (): void {
    $normalizer = new SchemaNormalizer();
    $encoder = new SchemaEncoder();
    $tag = new Tag('BenchTag', 'bench-002', new \DateTimeImmutable('2024-06-01T12:00:00+00:00'));

    // Pre-normalize once so the encode loop is isolated.
    $data = $normalizer->normalize($tag, SchemaNormalizer::FORMAT);

    $ms = bench(fn () => $encoder->encode($data, SchemaEncoder::FORMAT), 500);

    expect($ms)->toBeLessThan(2_000, "500 encodes took {$ms}ms (budget: 2 000ms)");
})->group('benchmark');

// ---------------------------------------------------------------------------
// Full normalize → encode roundtrip
// ---------------------------------------------------------------------------

it('completes 200 normalize+encode roundtrips within time budget', function (): void {
    $normalizer = new SchemaNormalizer();
    $encoder = new SchemaEncoder();
    $tag = new Tag('BenchTag', 'bench-003', new \DateTimeImmutable('2024-06-01T12:00:00+00:00'));

    $ms = bench(function () use ($normalizer, $encoder, $tag): void {
        $data = $normalizer->normalize($tag, SchemaNormalizer::FORMAT);
        $encoder->encode($data, SchemaEncoder::FORMAT);
    }, 200);

    expect($ms)->toBeLessThan(2_000, "200 roundtrips took {$ms}ms (budget: 2 000ms)");
})->group('benchmark');

// ---------------------------------------------------------------------------
// Reflection cache: cold vs warm
// ---------------------------------------------------------------------------

// it('reflection cache warm-path is significantly faster than cold-path', function (): void {
//     $normalizer = new SchemaNormalizer();
//     $tag        = new Tag('BenchTag', 'bench-004');

//     // Measure COLD: reset cache before every single call.
//     $coldIterations = 20;
//     $coldMs         = bench(function () use ($normalizer, $tag): void {
//         clearAttributeResolverCache(SchemaNormalizer::class);
//         $normalizer->normalize($tag, SchemaNormalizer::FORMAT);
//     }, $coldIterations);

//     // Measure WARM: cache is already populated from cold runs above.
//     $warmIterations = 500;
//     $warmMs         = bench(fn () => $normalizer->normalize($tag, SchemaNormalizer::FORMAT), $warmIterations);

//     $coldPerCall = $coldMs / $coldIterations;
//     $warmPerCall = $warmMs / $warmIterations;

//     // Warm path must be at least 3× faster per call than cold path.
//     expect($warmPerCall)->toBeLessThan(
//         $coldPerCall / 3,
//         \sprintf(
//             'Warm cache (%.4fms/call) should be >3× faster than cold (%.4fms/call)',
//             $warmPerCall,
//             $coldPerCall,
//         ),
//     );
// })->group('benchmark');

// ---------------------------------------------------------------------------
// Reflection cache: idempotency (repeated warmup should be near-instant)
// ---------------------------------------------------------------------------

it('repeated reflection cache warmup calls are near-instant', function (): void {
    $normalizer = new SchemaNormalizer();
    $tag = new Tag('BenchTag', 'bench-005');

    // Prime the cache once.
    $normalizer->normalize($tag, SchemaNormalizer::FORMAT);

    // 1 000 additional warmup calls should be negligible.
    $ms = bench(fn () => SchemaNormalizer::warmUpReflectionCache(), 1_000);

    expect($ms)->toBeLessThan(500, "1 000 warm warmUpReflectionCache() calls took {$ms}ms (budget: 500ms)");
})->group('benchmark');

// ---------------------------------------------------------------------------
// Query cache: setup helpers
// ---------------------------------------------------------------------------

/**
 * Writes a config pointing at a temp storage file and returns the paths.
 *
 * @return array{config: string, storage: string, cache: string}
 */
function cacheBenchPaths(): array
{
    return [
        'config' => \getcwd() . '/dom-orm.php',
        'storage' => \getcwd() . '/storage/bench_cache_test.xml',
        'cache' => \sys_get_temp_dir() . '/dom_orm_bench_cache.php',
    ];
}

/**
 * Generate XML with $count Tag items.
 */
function generateBenchXml(int $count): string
{
    $fragments = '';
    for ($i = 1; $i <= $count; $i++) {
        $id = \sprintf('bench-%04d', $i);
        $name = "BenchTag{$i}";
        $ts = '2024-01-01T00:00:00+00:00';
        $fragments .= <<<XML
  <item type="tag" id="{$id}">
    <fragment name="name"><![CDATA[{$name}]]></fragment>
    <fragment name="createdAt"><![CDATA[{$ts}]]></fragment>
  </item>

XML;
    }

    return "<?xml version=\"1.0\" encoding=\"utf-8\"?>\n<data>\n{$fragments}</data>";
}

/**
 * Reset EntityManagerTrait shared statics on a class so the next test starts clean.
 */
function resetEntityManagerStatics(string $class): void
{
    $reflection = new \ReflectionClass($class);
    foreach (['sharedStorage', 'sharedSerializer'] as $prop) {
        while ($reflection !== false) {
            if ($reflection->hasProperty($prop)) {
                $p = $reflection->getProperty($prop);
                $p->setAccessible(true);
                $p->setValue(null, null);
                break;
            }
            $reflection = $reflection->getParentClass();
        }
        $reflection = new \ReflectionClass($class);
    }
}

defined('BENCH_ITEM_COUNT') or define('BENCH_ITEM_COUNT', 500);
defined('BENCH_ITERATIONS') or define('BENCH_ITERATIONS', 50);
defined('BENCH_SPEEDUP_FIND') or define('BENCH_SPEEDUP_FIND', 3);    // find() / findBy(): O(1) vs O(n) scan
defined('BENCH_SPEEDUP_ALL') or define('BENCH_SPEEDUP_ALL', 1.5);   // findAll(): decode savings only

describe('query cache vs XPath', function (): void {

    beforeEach(function (): void {
        $p = cacheBenchPaths();

        if (!\is_dir(\dirname($p['storage']))) {
            \mkdir(\dirname($p['storage']), 0755, true);
        }

        \file_put_contents($p['storage'], generateBenchXml(BENCH_ITEM_COUNT));

        resetEntityManagerStatics(EntityRepository::class);

        \file_put_contents($p['config'], '<?php return ' . \var_export([
            'dom-orm' => [
                'flysystem' => [
                    'adapter' => \League\Flysystem\Local\LocalFilesystemAdapter::class,
                    'config' => [
                        'location' => \dirname($p['storage']),
                    ],
                ],
                'filename' => \basename($p['storage']),
                'encryption_key' => null,
                'cache_path' => null,   // overridden per test
                'cache_strategy' => 'manual',
            ],
        ], true) . ';');
    });

    afterEach(function (): void {
        $p = cacheBenchPaths();

        foreach ([$p['config'], $p['storage'], $p['cache']] as $file) {
            if (\file_exists($file)) {
                \unlink($file);
            }
        }

        resetEntityManagerStatics(EntityRepository::class);
    });

    // -----------------------------------------------------------------------
    // find()
    // -----------------------------------------------------------------------

    it('find() via PHP cache is at least ' . BENCH_SPEEDUP_FIND . '× faster than XPath on ' . BENCH_ITEM_COUNT . ' items', function (): void {
        $p = cacheBenchPaths();

        // ---- Baseline: XPath only (no cache) -------------------------------
        resetEntityManagerStatics(EntityRepository::class);

        $repoXml = new EntityRepository(BenchTag::class);
        $xmlMs = bench(fn () => $repoXml->find('bench-0250'), BENCH_ITERATIONS);
        $xmlPer = $xmlMs / BENCH_ITERATIONS;

        // ---- Cache path ----------------------------------------------------
        \file_put_contents($p['config'], '<?php return ' . \var_export([
            'dom-orm' => [
                'flysystem' => [
                    'adapter' => \League\Flysystem\Local\LocalFilesystemAdapter::class,
                    'config' => [
                        'location' => \dirname($p['storage']),
                    ],
                ],
                'filename' => \basename($p['storage']),
                'encryption_key' => null,
                'cache_path' => $p['cache'],
                'cache_strategy' => 'manual',
            ],
        ], true) . ';');

        QueryCache::build();
        resetEntityManagerStatics(EntityRepository::class);

        $repoCached = new EntityRepository(BenchTag::class);
        $cacheMs = bench(fn () => $repoCached->find('bench-0250'), BENCH_ITERATIONS);
        $cachePer = $cacheMs / BENCH_ITERATIONS;

        $ratio = $xmlPer / \max($cachePer, 0.0001);

        expect($ratio)->toBeGreaterThan(
            BENCH_SPEEDUP_FIND,
            \sprintf(
                'Cache find() should be >%d× faster; got %.1f× (cache=%.4fms, xml=%.4fms per call)',
                BENCH_SPEEDUP_FIND,
                $ratio,
                $cachePer,
                $xmlPer,
            ),
        );
    });

    // -----------------------------------------------------------------------
    // findAll()
    // -----------------------------------------------------------------------

    it('findAll() via PHP cache is at least ' . BENCH_SPEEDUP_ALL . '× faster than XPath on ' . BENCH_ITEM_COUNT . ' items', function (): void {
        $p = cacheBenchPaths();

        // ---- Baseline ------------------------------------------------------
        resetEntityManagerStatics(EntityRepository::class);

        $repoXml = new EntityRepository(BenchTag::class);
        $xmlMs = bench(fn () => $repoXml->findAll(), BENCH_ITERATIONS);
        $xmlPer = $xmlMs / BENCH_ITERATIONS;

        // ---- Cache path ----------------------------------------------------
        \file_put_contents($p['config'], '<?php return ' . \var_export([
            'dom-orm' => [
                'flysystem' => [
                    'adapter' => \League\Flysystem\Local\LocalFilesystemAdapter::class,
                    'config' => [
                        'location' => \dirname($p['storage']),
                    ],
                ],
                'filename' => \basename($p['storage']),
                'encryption_key' => null,
                'cache_path' => $p['cache'],
                'cache_strategy' => 'manual',
            ],
        ], true) . ';');

        QueryCache::build();
        resetEntityManagerStatics(EntityRepository::class);

        $repoCached = new EntityRepository(BenchTag::class);
        $cacheMs = bench(fn () => $repoCached->findAll(), BENCH_ITERATIONS);
        $cachePer = $cacheMs / BENCH_ITERATIONS;

        $ratio = $xmlPer / \max($cachePer, 0.0001);

        expect($ratio)->toBeGreaterThan(
            BENCH_SPEEDUP_ALL,
            \sprintf(
                'Cache findAll() should be >%.1f× faster; got %.1f× (cache=%.4fms, xml=%.4fms per call)',
                BENCH_SPEEDUP_ALL,
                $ratio,
                $cachePer,
                $xmlPer,
            ),
        );
    });

    // -----------------------------------------------------------------------
    // findBy()
    // -----------------------------------------------------------------------

    it('findBy() via PHP cache is at least ' . BENCH_SPEEDUP_FIND . '× faster than XPath on ' . BENCH_ITEM_COUNT . ' items', function (): void {
        $p = cacheBenchPaths();

        // ---- Baseline ------------------------------------------------------
        resetEntityManagerStatics(EntityRepository::class);

        $repoXml = new EntityRepository(BenchTag::class);
        $xmlMs = bench(fn () => $repoXml->findBy([
            'name' => 'BenchTag250',
        ]), BENCH_ITERATIONS);
        $xmlPer = $xmlMs / BENCH_ITERATIONS;

        // ---- Cache path ----------------------------------------------------
        \file_put_contents($p['config'], '<?php return ' . \var_export([
            'dom-orm' => [
                'flysystem' => [
                    'adapter' => \League\Flysystem\Local\LocalFilesystemAdapter::class,
                    'config' => [
                        'location' => \dirname($p['storage']),
                    ],
                ],
                'filename' => \basename($p['storage']),
                'encryption_key' => null,
                'cache_path' => $p['cache'],
                'cache_strategy' => 'manual',
            ],
        ], true) . ';');

        QueryCache::build();
        resetEntityManagerStatics(EntityRepository::class);

        $repoCached = new EntityRepository(BenchTag::class);
        $cacheMs = bench(fn () => $repoCached->findBy([
            'name' => 'BenchTag250',
        ]), BENCH_ITERATIONS);
        $cachePer = $cacheMs / BENCH_ITERATIONS;

        $ratio = $xmlPer / \max($cachePer, 0.0001);

        expect($ratio)->toBeGreaterThan(
            BENCH_SPEEDUP_FIND,
            \sprintf(
                'Cache findBy() should be >%d× faster; got %.1f× (cache=%.4fms, xml=%.4fms per call)',
                BENCH_SPEEDUP_FIND,
                $ratio,
                $cachePer,
                $xmlPer,
            ),
        );
    });

});

// ---------------------------------------------------------------------------
// Memoization improvements (ReflectionClass cache, constructor params, findOneBy)
// ---------------------------------------------------------------------------

describe('memoization improvements', function (): void {

    beforeEach(function (): void {
        $p = cacheBenchPaths();

        if (!\is_dir(\dirname($p['storage']))) {
            \mkdir(\dirname($p['storage']), 0755, true);
        }

        \file_put_contents($p['storage'], generateBenchXml(BENCH_ITEM_COUNT));
        resetEntityManagerStatics(EntityRepository::class);
        \file_put_contents($p['config'], '<?php return ' . \var_export([
            'dom-orm' => [
                'flysystem' => [
                    'adapter' => \League\Flysystem\Local\LocalFilesystemAdapter::class,
                    'config' => [
                        'location' => \dirname($p['storage']),
                    ],
                ],
                'filename' => \basename($p['storage']),
                'encryption_key' => null,
                'cache_path' => null,
                'cache_strategy' => 'manual',
            ],
        ], true) . ';');
    });

    afterEach(function (): void {
        $p = cacheBenchPaths();
        foreach ([$p['config'], $p['storage'], $p['cache']] as $file) {
            if (\file_exists($file)) {
                \unlink($file);
            }
        }
        resetEntityManagerStatics(EntityRepository::class);
    });

    // -----------------------------------------------------------------------
    // 1. ReflectionClass + constructor params — cold vs warm
    // -----------------------------------------------------------------------

    it('resolveConstructorParams() is at least 2× faster than repeated new ReflectionClass()', function (): void {
        // We need an instance that uses AttributeResolverTrait to call the resolver.
        // SchemaDenormalizer extends it via the trait on SchemaDenormalizer.
        $denorm = new \DOM\ORM\Serializer\Normalizer\SchemaDenormalizer();

        // Prime the cache once.
        $resolveParams = \Closure::bind(
            fn (string $c) => $this->resolveConstructorParams($c),
            $denorm,
            $denorm::class,
        );
        $resolveParams(Tests\Fixtures\Tag::class);

        $iterations = 5_000;

        // Warm path — cache hit.
        $warmMs = bench(fn () => $resolveParams(Tests\Fixtures\Tag::class), $iterations);

        // Cold equivalent — construct a fresh ReflectionClass each call.
        $coldMs = bench(function (): void {
            $rc = new \ReflectionClass(Tests\Fixtures\Tag::class);
            $rc->getConstructor()?->getParameters();
        }, $iterations);

        $warmPer = $warmMs / $iterations;
        $coldPer = $coldMs / $iterations;
        $ratio = $coldPer / \max($warmPer, 0.00001);

        expect($ratio)->toBeGreaterThan(
            2,
            \sprintf(
                'resolveConstructorParams() warm (%.5fms/call) should be >2× faster than cold ReflectionClass (%.5fms/call); got %.1f×',
                $warmPer,
                $coldPer,
                $ratio,
            ),
        );
    });

    // -----------------------------------------------------------------------
    // 2. Aggregate ms savings: 500 warm resolveConstructorParams() calls
    //    vs 500 cold new ReflectionClass() + getParameters() calls
    // -----------------------------------------------------------------------

    it('warm resolveConstructorParams() saves measurable ms over ' . BENCH_ITEM_COUNT . ' entity instantiations', function (): void {
        $denorm = new \DOM\ORM\Serializer\Normalizer\SchemaDenormalizer();

        $resolveWarm = \Closure::bind(
            fn (string $c) => $this->resolveConstructorParams($c),
            $denorm,
            $denorm::class,
        );

        // Prime the cache.
        $resolveWarm(Tests\Fixtures\Tag::class);

        // Warm path — static array lookup per call.
        $warmMs = bench(fn () => $resolveWarm(Tests\Fixtures\Tag::class), BENCH_ITEM_COUNT);

        // Cold equivalent — construct a new ReflectionClass + getParameters() per call.
        $coldMs = bench(function (): void {
            $rc = new \ReflectionClass(Tests\Fixtures\Tag::class);
            $rc->getConstructor()?->getParameters();
        }, BENCH_ITEM_COUNT);

        $savedMs = $coldMs - $warmMs;

        expect($savedMs)->toBeGreaterThan(
            0.05,
            \sprintf(
                'Warm resolveConstructorParams() should save >0.05ms over %d calls (cold=%.3fms, warm=%.3fms, saved=%.3fms)',
                BENCH_ITEM_COUNT,
                $coldMs,
                $warmMs,
                $savedMs,
            ),
        );
    });

    // -----------------------------------------------------------------------
    // 3. findOneBy() — query cache hit vs XPath on 500 items
    // -----------------------------------------------------------------------

    it('findOneBy() via PHP cache is at least 3× faster than XPath on ' . BENCH_ITEM_COUNT . ' items', function (): void {
        $p = cacheBenchPaths();

        // ---- Baseline: no cache --------------------------------------------
        resetEntityManagerStatics(EntityRepository::class);
        $repoXml = new EntityRepository(BenchTag::class);
        $xmlMs = bench(fn () => $repoXml->findOneBy([
            'name' => 'BenchTag250',
        ]), BENCH_ITERATIONS);
        $xmlPer = $xmlMs / BENCH_ITERATIONS;

        // ---- Cache path ----------------------------------------------------
        \file_put_contents($p['config'], '<?php return ' . \var_export([
            'dom-orm' => [
                'flysystem' => [
                    'adapter' => \League\Flysystem\Local\LocalFilesystemAdapter::class,
                    'config' => [
                        'location' => \dirname($p['storage']),
                    ],
                ],
                'filename' => \basename($p['storage']),
                'encryption_key' => null,
                'cache_path' => $p['cache'],
                'cache_strategy' => 'manual',
            ],
        ], true) . ';');

        QueryCache::build();
        resetEntityManagerStatics(EntityRepository::class);

        $repoCached = new EntityRepository(BenchTag::class);
        $cacheMs = bench(fn () => $repoCached->findOneBy([
            'name' => 'BenchTag250',
        ]), BENCH_ITERATIONS);
        $cachePer = $cacheMs / BENCH_ITERATIONS;

        $ratio = $xmlPer / \max($cachePer, 0.0001);

        expect($ratio)->toBeGreaterThan(
            3,
            \sprintf(
                'findOneBy() cache (%.4fms/call) should be >3× faster than XPath (%.4fms/call); got %.1f×',
                $cachePer,
                $xmlPer,
                $ratio,
            ),
        );
    });

});
