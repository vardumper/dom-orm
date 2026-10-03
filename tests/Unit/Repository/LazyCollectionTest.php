<?php

declare(strict_types=1);

use DOM\ORM\Repository\LazyCollection;
use DOM\ORM\Serializer\Normalizer\SchemaDenormalizer;
use DOM\ORM\Storage\ChunkStore;
use Tests\Fixtures\Tag;

$dir = null;

beforeEach(function () use (&$dir): void {
    $dir = \sys_get_temp_dir() . '/dom-orm-lazy-' . \uniqid('', true);
});

afterEach(function () use (&$dir): void {
    if ($dir !== null && \is_dir($dir)) {
        ChunkStore::clearDir($dir);
    }

    $dir = null;
});

/**
 * Build a store-backed lazy collection over three tags.
 */
function makeLazyStore(string $dir): LazyCollection
{
    $payload = [
        '__meta' => [
            'format' => 2,
            'hash' => 'abc123',
            'size' => 100,
            'mtime' => 12345,
        ],
        'tag' => [
            'tag-1' => [
                '@id' => 'tag-1',
                '@type' => 'tag',
                'name' => 'First',
                'createdAt' => '2024-01-01T00:00:00+00:00',
            ],
            'tag-2' => [
                '@id' => 'tag-2',
                '@type' => 'tag',
                'name' => 'Second',
                'createdAt' => '2024-01-02T00:00:00+00:00',
            ],
            'tag-3' => [
                '@id' => 'tag-3',
                '@type' => 'tag',
                'name' => 'Third',
                'createdAt' => '2024-01-03T00:00:00+00:00',
            ],
        ],
    ];
    ChunkStore::build($dir, $payload, []);

    return LazyCollection::fromStore(new ChunkStore($dir), 'tag', Tag::class, new SchemaDenormalizer());
}

it('count is O(1) and reflects the id list', function () use (&$dir): void {
    expect(makeLazyStore($dir)->count())->toBe(3);
});

it('get hydrates an entity on first access and caches it', function () use (&$dir): void {
    $collection = makeLazyStore($dir);
    $first = $collection->get(0);
    expect($first)->toBeInstanceOf(Tag::class);
    expect($first->getName())->toBe('First');
    expect($first->getId())->toBe('tag-1');

    /** Same instance is returned on the second access (cached). */
    expect($collection->get(0))->toBe($first);
});

it('get returns null for an out-of-range index', function () use (&$dir): void {
    expect(makeLazyStore($dir)->get(99))->toBeNull();
});

it('first and last resolve the boundary entities', function () use (&$dir): void {
    $collection = makeLazyStore($dir);
    expect($collection->first()->getName())->toBe('First');
    expect($collection->last()->getName())->toBe('Third');
});

it('all hydrates every entity in order', function () use (&$dir): void {
    $names = \array_map(static fn (Tag $tag) => $tag->getName(), makeLazyStore($dir)->all());
    expect($names)->toBe(['First', 'Second', 'Third']);
});

it('getIterator yields the hydrated entities', function () use (&$dir): void {
    $collected = [];
    foreach (makeLazyStore($dir) as $tag) {
        $collected[] = $tag->getName();
    }

    expect($collected)->toBe(['First', 'Second', 'Third']);
});

it('map transforms each hydrated entity', function () use (&$dir): void {
    $result = makeLazyStore($dir)->map(static fn (Tag $tag) => \strtoupper($tag->getName()));
    expect($result)->toBe(['FIRST', 'SECOND', 'THIRD']);
});

it('filter returns a lazy collection of the matching entities', function () use (&$dir): void {
    $filtered = makeLazyStore($dir)->filter(static fn (Tag $tag) => $tag->getName() !== 'Second');
    expect($filtered->count())->toBe(2);
    expect(\array_map(static fn (Tag $tag) => $tag->getName(), $filtered->all()))->toBe(['First', 'Third']);
});

it('fromStore accepts an explicit id list', function () use (&$dir): void {
    $payload = [
        '__meta' => [
            'format' => 2,
            'hash' => 'abc123',
            'size' => 100,
            'mtime' => 12345,
        ],
        'tag' => [
            'tag-1' => [
                '@id' => 'tag-1',
                '@type' => 'tag',
                'name' => 'First',
                'createdAt' => '2024-01-01T00:00:00+00:00',
            ],
            'tag-2' => [
                '@id' => 'tag-2',
                '@type' => 'tag',
                'name' => 'Second',
                'createdAt' => '2024-01-02T00:00:00+00:00',
            ],
        ],
    ];
    ChunkStore::build($dir, $payload, []);

    $collection = LazyCollection::fromStore(new ChunkStore($dir), 'tag', Tag::class, new SchemaDenormalizer(), ['tag-2']);
    expect($collection->count())->toBe(1);
    expect($collection->first()->getName())->toBe('Second');
});

it('fromEntities wraps already-materialized entities', function (): void {
    $a = new Tag('Alpha', 'id-a');
    $b = new Tag('Beta', 'id-b');
    $collection = LazyCollection::fromEntities([$a, $b], Tag::class);

    expect($collection->count())->toBe(2);
    expect($collection->get(0))->toBe($a);
    expect($collection->get(1))->toBe($b);
    expect($collection->first()->getName())->toBe('Alpha');
    expect($collection->last()->getName())->toBe('Beta');
});

it('fromEntities with an empty list yields an empty collection', function (): void {
    $collection = LazyCollection::fromEntities([], Tag::class);
    expect($collection->count())->toBe(0);
    expect($collection->all())->toBe([]);
    expect($collection->first())->toBeNull();
});
