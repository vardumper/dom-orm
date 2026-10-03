<?php

declare(strict_types=1);

use DOM\ORM\Storage\{ChunkStore, QueryCache};

/**
 * QueryCache paths that depend on the configured cache_path. The first block
 * runs with no cache configured (the default) to cover the null / not-configured
 * branches; the rest configure a path to cover index-path derivation, flush, and
 * the buildFromDom edge cases (encrypted fields, empty ids/types, malformed groups).
 */

$prevCachePathEnv = false;

beforeEach(function () use (&$prevCachePathEnv): void {
    $prevCachePathEnv = \getenv('DOM_ORM_CACHE_PATH');
    \putenv('DOM_ORM_CACHE_PATH');
});

afterEach(function () use (&$prevCachePathEnv): void {
    \putenv($prevCachePathEnv === false ? 'DOM_ORM_CACHE_PATH' : 'DOM_ORM_CACHE_PATH=' . $prevCachePathEnv);
});

it('getCachePath returns null when no cache is configured', function (): void {
    expect(QueryCache::getCachePath())->toBeNull();
});

it('getIndexPath returns null when no cache is configured', function (): void {
    expect(QueryCache::getIndexPath())->toBeNull();
});

it('getChunkDir returns null when no cache is configured', function (): void {
    expect(QueryCache::getChunkDir())->toBeNull();
});

it('load returns null when no cache is configured', function (): void {
    expect(QueryCache::load())->toBeNull();
});

it('loadIndex returns null when no cache is configured', function (): void {
    expect(QueryCache::loadIndex())->toBeNull();
});

it('isEnabled and exists are false when no cache is configured', function (): void {
    expect(QueryCache::isEnabled())->toBeFalse();
    expect(QueryCache::exists())->toBeFalse();
});

it('flush is a no-op when no cache is configured', function (): void {
    QueryCache::flush();
    expect(true)->toBeTrue();
});

it('getIndexPath appends -index.php for a non-php cache path', function (): void {
    \putenv('DOM_ORM_CACHE_PATH=/tmp/qc-cache.dat');
    expect(QueryCache::getIndexPath())->toBe('/tmp/qc-cache.dat-index.php');
});

it('buildFromDom handles encrypted fields, empty ids/types, and malformed groups', function (): void {
    $base = \sys_get_temp_dir() . '/dom-orm-qc-dom-' . \uniqid('', true);
    $cachePath = $base . '/cache.php';
    \putenv('DOM_ORM_CACHE_PATH=' . $cachePath);

    /** buildFromDom fingerprints the source data file, so it must exist. */
    $storageFile = \getcwd() . '/storage/data.xml';
    $storageBackup = $storageFile . '.qcbak';
    $hadStorage = \file_exists($storageFile);
    if ($hadStorage) {
        \rename($storageFile, $storageBackup);
    }
    if (!\is_dir(\dirname($storageFile))) {
        \mkdir(\dirname($storageFile), 0755, true);
    }
    \file_put_contents($storageFile, '<data />');

    try {
        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->loadXML(<<<'XML'
            <data>
                <item id="u1" type="user">
                    <fragment name="name">Alice</fragment>
                    <fragment name="ssn" searchable-hash="abc123">123-45-6789</fragment>
                </item>
                <item id="" type="user">
                    <fragment name="name">NoId</fragment>
                </item>
                <item id="u2" type="">
                    <fragment name="name">NoType</fragment>
                </item>
                <item id="u3" type="user">
                    <fragment name="name">Bob</fragment>
                    <group type="">
                        <item id="g0" type="friend"><fragment name="name">F0</fragment></item>
                    </group>
                    <group type="friends">
                        <text>not-an-item</text>
                        <item id="" type="friend"><fragment name="name">F1</fragment></item>
                        <item id="g2" type="friend"><fragment name="name">F2</fragment></item>
                    </group>
                </item>
            </data>
            XML);

        QueryCache::buildFromDom($dom);

        $store = new ChunkStore(ChunkStore::dirFor($cachePath));
        expect($store->ids('user'))->toBe(['u1', 'u3']);
        expect($store->findById('user', 'u1')['ssn'])->toBe([
            'value' => '123-45-6789',
            'searchable-hash' => 'abc123',
        ]);
        expect($store->findById('user', 'u3')['friends'])->toHaveCount(1);
        expect($store->findById('user', 'u3')['friends'][0])->toHaveKey('item-g2');
    } finally {
        if (\file_exists($storageFile)) {
            \unlink($storageFile);
        }
        if (\file_exists($storageBackup)) {
            \rename($storageBackup, $storageFile);
        }
        ChunkStore::clearDir(ChunkStore::dirFor($cachePath));
        if (\is_dir($base)) {
            \rmdir($base);
        }
    }
});
