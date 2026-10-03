<?php

declare(strict_types=1);

use DOM\ORM\Storage\Hydrator;
use Tests\Fixtures\Tag;

/** @var string|null $tmpDir */
$tmpDir = null;

beforeEach(function () use (&$tmpDir): void {
    $tmpDir = \sys_get_temp_dir() . '/dom-orm-hydrator-' . \uniqid('', true);
    \mkdir($tmpDir . '/generated', 0755, true);
    \putenv('DOM_ORM_FLYSYSTEM_LOCATION=' . $tmpDir);
    /** Reset the cached generated dir so the new location takes effect. */
    $p = new \ReflectionProperty(Hydrator::class, 'generatedDir');
    $p->setValue(null, null);
});

afterEach(function () use (&$tmpDir): void {
    \putenv('DOM_ORM_FLYSYSTEM_LOCATION');
    if ($tmpDir !== null && \is_dir($tmpDir . '/generated')) {
        \array_map('unlink', (array)\glob($tmpDir . '/generated/*'));
        \rmdir($tmpDir . '/generated');
    }
    if ($tmpDir !== null && \is_dir($tmpDir)) {
        \rmdir($tmpDir);
    }
    $tmpDir = null;
});

it('get returns null for an unknown entity type', function (): void {
    $fn = Hydrator::get('nonexistent_entity_type_xyz', null, static fn () => null);
    expect($fn)->toBeNull();
});

it('get returns a hydration callable that builds the entity', function (): void {
    \class_exists(Tag::class); /** force autoload so warmUp() can see the type */
    $fn = Hydrator::get('tag', null, static fn () => null);
    expect($fn)->toBeCallable();
    $row = [
        'item-abc123' => [
            '@id' => 'abc123',
            '@type' => 'tag',
            'name' => 'TestTag',
        ],
    ];
    $entity = $fn($row);
    expect($entity)->toBeInstanceOf(Tag::class);
    expect($entity->getName())->toBe('TestTag');
});

it('readFileHash returns null for a missing file', function (): void {
    $m = new \ReflectionMethod(Hydrator::class, 'readFileHash');
    $result = $m->invoke(null, '/nonexistent/path/file.php');
    expect($result)->toBeNull();
});

it('readFileHash returns null when the header has no hash', function () use (&$tmpDir): void {
    if ($tmpDir === null) {
        throw new RuntimeException('Hydrator test fixture was not initialized.');
    }
    $path = $tmpDir . '/generated/nohash.php';
    \file_put_contents($path, '<?php // no hash in this header');
    $m = new \ReflectionMethod(Hydrator::class, 'readFileHash');
    $result = $m->invoke(null, $path);
    expect($result)->toBeNull();
});
