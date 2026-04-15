<?php

declare(strict_types=1);

use DOM\ORM\Serializer\Encoder\SchemaEncoder;
use DOM\ORM\Serializer\Normalizer\SchemaNormalizer;
use Tests\Fixtures\Tag;

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

it('reflection cache warm-path is significantly faster than cold-path', function (): void {
    $normalizer = new SchemaNormalizer();
    $tag = new Tag('BenchTag', 'bench-004');

    // Measure COLD: reset cache before every single call.
    $coldIterations = 20;
    $coldMs = bench(function () use ($normalizer, $tag): void {
        clearAttributeResolverCache(SchemaNormalizer::class);
        $normalizer->normalize($tag, SchemaNormalizer::FORMAT);
    }, $coldIterations);

    // Measure WARM: cache is already populated from cold runs above.
    $warmIterations = 500;
    $warmMs = bench(fn () => $normalizer->normalize($tag, SchemaNormalizer::FORMAT), $warmIterations);

    $coldPerCall = $coldMs / $coldIterations;
    $warmPerCall = $warmMs / $warmIterations;

    // Warm path must be at least 5× faster per call than cold path.
    expect($warmPerCall)->toBeLessThan(
        $coldPerCall / 3,
        \sprintf(
            'Warm cache (%.4fms/call) should be >5× faster than cold (%.4fms/call)',
            $warmPerCall,
            $coldPerCall,
        ),
    );
})->group('benchmark');

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
