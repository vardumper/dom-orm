<?php

declare(strict_types=1);

use DOM\ORM\Storage\ChunkStore;

$dir = null;

beforeEach(function () use (&$dir): void {
    $dir = \sys_get_temp_dir() . '/dom-orm-chunk-' . \uniqid('', true);
});

afterEach(function () use (&$dir): void {
    if ($dir !== null && \is_dir($dir)) {
        ChunkStore::clearDir($dir);
    }

    $dir = null;
});

/**
 * Build a small two-record store and return a handle to it.
 */
function buildTestStore(string $dir): ChunkStore
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
            ],
            'uuid2' => [
                '@id' => 'uuid2',
                '@type' => 'user',
                'name' => 'Bob',
            ],
        ],
    ];
    $index = [
        'user' => [
            'name' => [
                'Alice' => ['uuid1'],
                'Bob' => ['uuid2'],
            ],
        ],
        '__meta' => [
            'format' => 2,
            'encrypted' => [],
        ],
    ];
    ChunkStore::build($dir, $payload, $index);

    return new ChunkStore($dir);
}

it('shardFor returns a two-hex-char key', function (): void {
    expect(ChunkStore::shardFor('uuid1'))->toMatch('/^[0-9a-f]{2}$/');
});

it('shardFor spreads ids across many shards', function (): void {
    $shards = [];
    for ($i = 0; $i < 200; $i++) {
        $shards[ChunkStore::shardFor('id-' . $i)] = true;
    }

    expect(\count($shards))->toBeGreaterThan(100);
});

it('dirFor appends a cache subdirectory', function (): void {
    expect(ChunkStore::dirFor('/tmp/data/cache.php'))->toBe('/tmp/data/cache');
});

it('build then exists', function () use (&$dir): void {
    if ($dir === null) {
        throw new \RuntimeException('dir not set');
    }

    expect(buildTestStore($dir)->exists())->toBeTrue();
});

it('exists is false for a missing directory', function () use (&$dir): void {
    if ($dir === null) {
        throw new \RuntimeException('dir not set');
    }

    expect((new ChunkStore($dir))->exists())->toBeFalse();
});

it('meta returns the stored fingerprint', function () use (&$dir): void {
    if ($dir === null) {
        throw new \RuntimeException('dir not set');
    }

    expect(buildTestStore($dir)->meta())->toBe([
        'format' => 2,
        'hash' => 'abc123',
        'size' => 100,
        'mtime' => 12345,
    ]);
});

it('meta returns null when no meta file exists', function () use (&$dir): void {
    if ($dir === null) {
        throw new \RuntimeException('dir not set');
    }

    \mkdir($dir, 0755, true);
    expect((new ChunkStore($dir))->meta())->toBeNull();
});

it('index returns the stored index', function () use (&$dir): void {
    if ($dir === null) {
        throw new \RuntimeException('dir not set');
    }

    expect(buildTestStore($dir)->index()['user']['name'])->toBe([
        'Alice' => ['uuid1'],
        'Bob' => ['uuid2'],
    ]);
});

it('index returns null when no index file exists', function () use (&$dir): void {
    if ($dir === null) {
        throw new \RuntimeException('dir not set');
    }

    \mkdir($dir, 0755, true);
    expect((new ChunkStore($dir))->index())->toBeNull();
});

it('ids returns the ordered id list per type', function () use (&$dir): void {
    if ($dir === null) {
        throw new \RuntimeException('dir not set');
    }

    $store = buildTestStore($dir);
    expect($store->ids('user'))->toBe(['uuid1', 'uuid2']);
    expect($store->ids('unknown'))->toBe([]);
});

it('findById returns the item data', function () use (&$dir): void {
    if ($dir === null) {
        throw new \RuntimeException('dir not set');
    }

    $store = buildTestStore($dir);
    expect($store->findById('user', 'uuid1'))->toBe([
        '@id' => 'uuid1',
        '@type' => 'user',
        'name' => 'Alice',
    ]);
    expect($store->findById('user', 'missing'))->toBeNull();
});

it('findAll returns all items for a type', function () use (&$dir): void {
    if ($dir === null) {
        throw new \RuntimeException('dir not set');
    }

    $store = buildTestStore($dir);
    $all = $store->findAll('user');
    expect(\count($all))->toBe(2);
    expect($all['uuid2']['name'])->toBe('Bob');
    expect($store->findAll('unknown'))->toBe([]);
});

it('findByIds returns only the requested ids', function () use (&$dir): void {
    if ($dir === null) {
        throw new \RuntimeException('dir not set');
    }

    $store = buildTestStore($dir);
    $found = $store->findByIds('user', ['uuid2', 'missing']);
    expect(\array_keys($found))->toBe(['uuid2']);
    expect($found['uuid2']['name'])->toBe('Bob');
});

it('loadShard returns the shard payload', function () use (&$dir): void {
    if ($dir === null) {
        throw new \RuntimeException('dir not set');
    }

    $store = buildTestStore($dir);
    $data = $store->loadShard(ChunkStore::shardFor('uuid1'));
    expect($data['user']['uuid1']['name'])->toBe('Alice');
});

it('loadShard returns an empty array for a missing shard', function () use (&$dir): void {
    if ($dir === null) {
        throw new \RuntimeException('dir not set');
    }

    expect(buildTestStore($dir)->loadShard('zz'))->toBe([]);
});

it('clearDir removes the cache directory', function () use (&$dir): void {
    if ($dir === null) {
        throw new \RuntimeException('dir not set');
    }

    buildTestStore($dir);
    expect($dir)->toBeDirectory();
    ChunkStore::clearDir($dir);
    expect($dir)->not->toBeDirectory();
});

it('clearDir is a no-op for a missing directory', function (): void {
    ChunkStore::clearDir(\sys_get_temp_dir() . '/dom-orm-does-not-exist-' . \uniqid());
    expect(true)->toBeTrue();
});
