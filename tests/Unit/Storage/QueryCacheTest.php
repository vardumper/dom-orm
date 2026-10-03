<?php

declare(strict_types=1);

use DOM\ORM\Storage\ChunkStore;
use DOM\ORM\Storage\QueryCache;

$dir = null;

beforeEach(function () use (&$dir): void {
    $dir = \sys_get_temp_dir() . '/dom-orm-qc-' . \uniqid('', true);
});

afterEach(function () use (&$dir): void {
    if ($dir !== null && \is_dir($dir)) {
        ChunkStore::clearDir($dir);
    }

    $dir = null;
});

/**
 * Build a two-user chunk store and return a handle to it.
 */
function buildQcStore(string $dir): ChunkStore
{
    $payload = [
        '__meta' => [
            'format' => 2,
            'hash' => 'abc123',
            'size' => 100,
            'mtime' => 12345,
        ],
        'user' => [
            'uuid1' => [
                '@id' => 'uuid1',
                '@type' => 'user',
                'name' => 'Alice',
                'city' => 'Berlin',
            ],
            'uuid2' => [
                '@id' => 'uuid2',
                '@type' => 'user',
                'name' => 'Bob',
                'city' => 'Paris',
            ],
        ],
    ];
    $index = [
        'user' => [
            'name' => [
                'Alice' => ['uuid1'],
                'Bob' => ['uuid2'],
            ],
            'city' => [
                'Berlin' => ['uuid1'],
                'Paris' => ['uuid2'],
            ],
        ],
        '__meta' => [
            'format' => 2,
            'encrypted' => [
                'user' => [
                    'ssn' => true,
                ],
            ],
        ],
    ];
    ChunkStore::build($dir, $payload, $index);

    return new ChunkStore($dir);
}

/**
 * The format-1 (monolith) cache shape that findBy() still understands.
 */
function makeLegacyCache(): array
{
    return [
        'user' => [
            'uuid1' => [
                '@id' => 'uuid1',
                '@type' => 'user',
                'name' => 'Alice',
                'city' => 'Berlin',
            ],
            'uuid2' => [
                '@id' => 'uuid2',
                '@type' => 'user',
                'name' => 'Bob',
                'city' => 'Paris',
            ],
            '__idx' => [
                'name' => [
                    'Alice' => ['uuid1'],
                    'Bob' => ['uuid2'],
                ],
                'city' => [
                    'Berlin' => ['uuid1'],
                    'Paris' => ['uuid2'],
                ],
            ],
        ],
    ];
}

it('findById returns denormalizer-shaped data', function () use (&$dir): void {
    $store = buildQcStore($dir);
    $result = QueryCache::findById($store, 'user', 'uuid1');
    expect($result)->toBe([
        'data' => [[
            'item-uuid1' => [
                '@id' => 'uuid1',
                '@type' => 'user',
                'name' => 'Alice',
                'city' => 'Berlin',
            ],
        ]],
    ]);
});

it('findById returns null for a missing id', function () use (&$dir): void {
    expect(QueryCache::findById(buildQcStore($dir), 'user', 'missing'))->toBeNull();
});

it('findAll returns every item of a type', function () use (&$dir): void {
    $result = QueryCache::findAll(buildQcStore($dir), 'user');
    expect(\count($result['data']))->toBe(2);
    expect($result['data'][0])->toBe([
        'item-uuid1' => [
            '@id' => 'uuid1',
            '@type' => 'user',
            'name' => 'Alice',
            'city' => 'Berlin',
        ],
    ]);
});

it('findAll returns null for an empty type', function () use (&$dir): void {
    expect(QueryCache::findAll(buildQcStore($dir), 'unknown'))->toBeNull();
});

it('findByIndex resolves a single-field match', function () use (&$dir): void {
    $index = buildQcStore($dir)->index();
    expect(QueryCache::findByIndex($index, 'user', [
        'name' => 'Alice',
    ]))->toBe(['uuid1']);
});

it('findByIndex returns empty for a non-matching value', function () use (&$dir): void {
    $index = buildQcStore($dir)->index();
    expect(QueryCache::findByIndex($index, 'user', [
        'name' => 'Nobody',
    ]))->toBe([]);
});

it('findByIndex intersects multiple criteria', function () use (&$dir): void {
    $index = buildQcStore($dir)->index();
    expect(QueryCache::findByIndex($index, 'user', [
        'name' => 'Alice',
        'city' => 'Berlin',
    ]))->toBe(['uuid1']);
});

it('findByIndex returns empty when criteria do not intersect', function () use (&$dir): void {
    $index = buildQcStore($dir)->index();
    expect(QueryCache::findByIndex($index, 'user', [
        'name' => 'Alice',
        'city' => 'Paris',
    ]))->toBe([]);
});

it('findByIndex returns null for an encrypted criterion', function () use (&$dir): void {
    $index = buildQcStore($dir)->index();
    expect(QueryCache::findByIndex($index, 'user', [
        'ssn' => '123',
    ]))->toBeNull();
});

it('findByIndex returns empty for an unknown non-encrypted field', function () use (&$dir): void {
    $index = buildQcStore($dir)->index();
    expect(QueryCache::findByIndex($index, 'user', [
        'unknown' => 'x',
    ]))->toBe([]);
});

it('findByIndex returns empty for a missing type', function () use (&$dir): void {
    $index = buildQcStore($dir)->index();
    expect(QueryCache::findByIndex($index, 'unknown', [
        'name' => 'Alice',
    ]))->toBe([]);
});

it('findByIndex applies an id-only filter', function () use (&$dir): void {
    $index = buildQcStore($dir)->index();
    expect(QueryCache::findByIndex($index, 'user', [
        'id' => 'uuid1',
    ]))->toBe(['uuid1']);
});

it('findByIndex narrows an id filter against other criteria', function () use (&$dir): void {
    $index = buildQcStore($dir)->index();
    expect(QueryCache::findByIndex($index, 'user', [
        'id' => 'uuid1',
        'name' => 'Alice',
    ]))->toBe(['uuid1']);
    expect(QueryCache::findByIndex($index, 'user', [
        'id' => 'uuid1',
        'name' => 'Bob',
    ]))->toBe([]);
});

it('findByIndex returns all ids when given no criteria', function () use (&$dir): void {
    $index = buildQcStore($dir)->index();
    expect(QueryCache::findByIndex($index, 'user', []))->toBe(['uuid1', 'uuid2']);
});

it('findByIds materialises the requested ids in order', function () use (&$dir): void {
    $result = QueryCache::findByIds(buildQcStore($dir), 'user', ['uuid2', 'uuid1']);
    expect(\array_keys($result['data']))->toBe([0, 1]);
    expect($result['data'][0])->toBe([
        'item-uuid2' => [
            '@id' => 'uuid2',
            '@type' => 'user',
            'name' => 'Bob',
            'city' => 'Paris',
        ],
    ]);
});

it('findByIds skips missing ids', function () use (&$dir): void {
    $result = QueryCache::findByIds(buildQcStore($dir), 'user', ['uuid1', 'missing']);
    expect(\count($result['data']))->toBe(1);
});

it('findBy returns null for a missing type', function (): void {
    expect(QueryCache::findBy(makeLegacyCache(), 'unknown', [
        'name' => 'Alice',
    ]))->toBeNull();
});

it('findBy returns null for a format-2 payload (no index)', function () use (&$dir): void {
    $payload = buildQcStore($dir)->findAll('user');
    expect(QueryCache::findBy([
        'user' => $payload,
    ], 'user', [
        'name' => 'Alice',
    ]))->toBeNull();
});

it('findBy resolves a single-field match', function (): void {
    $result = QueryCache::findBy(makeLegacyCache(), 'user', [
        'name' => 'Alice',
    ]);
    expect($result['data'])->toBe([[
        'item-uuid1' => [
            '@id' => 'uuid1',
            '@type' => 'user',
            'name' => 'Alice',
            'city' => 'Berlin',
        ],
    ]]);
});

it('findBy returns empty for a non-matching value', function (): void {
    expect(QueryCache::findBy(makeLegacyCache(), 'user', [
        'name' => 'Nobody',
    ]))->toBe([
        'data' => [],
    ]);
});

it('findBy intersects multiple criteria', function (): void {
    $result = QueryCache::findBy(makeLegacyCache(), 'user', [
        'name' => 'Alice',
        'city' => 'Berlin',
    ]);
    expect(\count($result['data']))->toBe(1);
});

it('findBy returns empty when criteria do not intersect', function (): void {
    expect(QueryCache::findBy(makeLegacyCache(), 'user', [
        'name' => 'Alice',
        'city' => 'Paris',
    ]))->toBe([
        'data' => [],
    ]);
});

it('findBy returns null for an encrypted field', function (): void {
    $cache = makeLegacyCache();
    $cache['user']['uuid1']['ssn'] = [
        'value' => 'x',
        'searchable-hash' => 'y',
    ];
    expect(QueryCache::findBy($cache, 'user', [
        'ssn' => '123',
    ]))->toBeNull();
});

it('findBy returns empty for an unknown non-encrypted field', function (): void {
    expect(QueryCache::findBy(makeLegacyCache(), 'user', [
        'unknown' => 'x',
    ]))->toBe([
        'data' => [],
    ]);
});

it('findBy applies an id-only filter', function (): void {
    $result = QueryCache::findBy(makeLegacyCache(), 'user', [
        'id' => 'uuid2',
    ]);
    expect($result['data'])->toBe([[
        'item-uuid2' => [
            '@id' => 'uuid2',
            '@type' => 'user',
            'name' => 'Bob',
            'city' => 'Paris',
        ],
    ]]);
});

it('findBy narrows an id filter against other criteria', function (): void {
    expect(QueryCache::findBy(makeLegacyCache(), 'user', [
        'id' => 'uuid1',
        'name' => 'Alice',
    ]))->not->toBe([
        'data' => [],
    ]);
    expect(QueryCache::findBy(makeLegacyCache(), 'user', [
        'id' => 'uuid1',
        'name' => 'Bob',
    ]))->toBe([
        'data' => [],
    ]);
});

it('findBy returns all items when given no criteria', function (): void {
    $result = QueryCache::findBy(makeLegacyCache(), 'user', []);
    expect(\count($result['data']))->toBe(2);
});
