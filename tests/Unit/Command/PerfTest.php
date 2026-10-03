<?php

declare(strict_types=1);

use DOM\ORM\Command\{Perf, PerfManager, PerfUser};
use DOM\ORM\Repository\AbstractEntityRepository;
use DOM\ORM\Storage\InMemoryFilesystemAdapter;

/**
 * Perf::run() mutates global state: the InMemoryFilesystemAdapter bucket store
 * and the EntityManagerTrait singletons, which are per-class statics (PerfManager
 * and AbstractEntityRepository each keep their own copy). Snapshot that state
 * before each case and restore it afterwards so none of it leaks into the suite.
 */

class_exists(Perf::class); /** load Perf.php so PerfManager is defined for the guard. */

/** @var array<class-string, list<string>> */
const MANAGER_CLASSES = [
    PerfManager::class => ['sharedStorage', 'sharedSerializer'],
    AbstractEntityRepository::class => ['sharedStorage', 'sharedSerializer'],
];

$perfState = null;

beforeEach(function () use (&$perfState): void {
    $perfState = [
        'buckets' => (new \ReflectionProperty(InMemoryFilesystemAdapter::class, 'buckets'))->getValue(null),
        'managers' => [],
    ];

    foreach (MANAGER_CLASSES as $class => $props) {
        $perfState['managers'][$class] = [];
        foreach ($props as $prop) {
            $perfState['managers'][$class][$prop] = (new \ReflectionProperty($class, $prop))->getValue(null);
        }
    }
});

afterEach(function () use (&$perfState): void {
    (new \ReflectionProperty(InMemoryFilesystemAdapter::class, 'buckets'))->setValue(null, $perfState['buckets']);

    foreach ($perfState['managers'] as $class => $props) {
        foreach ($props as $prop => $value) {
            (new \ReflectionProperty($class, $prop))->setValue(null, $value);
        }
    }
});

it('runs the perf benchmark against an in-memory dataset', function (): void {
    $result = Perf::run(count: 50, sampleSize: 10, useInMemory: true, iterations: 5);

    expect($result)->toHaveKey('adapter');
    expect($result['adapter'])->toBe('in_memory');
    expect($result['count'])->toBe(50);
    expect($result['sample_size'])->toBe(10);
    expect($result['iterations'])->toBe(5);
    expect($result)->toHaveKey('one_by_one_sample_ms');
    expect($result)->toHaveKey('batch_total_ms');
    expect($result)->toHaveKey('find_all_ms');
    expect($result)->toHaveKey('cache_build_ms');
    expect($result['find_by_id'])->toHaveKey('iterations');
    expect($result['find_by_id'])->toHaveKey('median_ms');
    expect($result['find_by_id'])->toHaveKey('p95_ms');
    expect($result['find_by_id'])->toHaveKey('min_ms');
    expect($result['find_by_id'])->toHaveKey('max_ms');
    expect($result['find_by_id']['iterations'])->toBe(5);
    expect($result['cache_find_by_id']['iterations'])->toBe(5);
});

it('runs the perf benchmark against a local filesystem dataset', function (): void {
    $result = Perf::run(count: 30, sampleSize: 5, useInMemory: false, iterations: 3);

    expect($result['adapter'])->toBe('local');
    expect($result['count'])->toBe(30);
    expect($result['find_by_id']['iterations'])->toBe(3);
    expect($result['batch_xml_kb'])->toBeGreaterThanOrEqual(0);
});

it('PerfUser setters are fluent and update state', function (): void {
    $user = new PerfUser('name', 'email@example.com', 'city');

    expect($user->setName('n2'))->toBe($user);
    expect($user->setEmail('e2@example.com'))->toBe($user);
    expect($user->setCity('c2'))->toBe($user);

    expect($user->getName())->toBe('n2');
    expect($user->getEmail())->toBe('e2@example.com');
    expect($user->getCity())->toBe('c2');
});
