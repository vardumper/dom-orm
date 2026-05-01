<?php

declare(strict_types=1);

use DOM\ORM\Traits\EntityManagerTrait;
use Tests\Fixtures\ScopedArticle;
use Tests\Fixtures\ScopedComment;
use Tests\Fixtures\Tag;

// Minimal concrete class that exposes EntityManagerTrait for testing
class TestEntityManager
{
    use EntityManagerTrait;
}

$storageFile = null;
$storageBackup = null;
$lockFile = null;
$configFile = null;
$configBackup = null;
$manager = null;

function resetEntityManagerSharedState(): void
{
    $reflection = new \ReflectionClass(TestEntityManager::class);

    foreach (['sharedStorage', 'sharedSerializer'] as $propertyName) {
        $property = $reflection->getProperty($propertyName);
        $property->setValue(null, null);
    }
}

// Integration tests – same storage isolation pattern as EntityRepositoryTest
beforeEach(function () use (&$storageFile, &$storageBackup, &$lockFile, &$configFile, &$configBackup, &$manager): void {
    $storageFile = \getcwd() . '/storage/data.xml';
    $storageBackup = $storageFile . '.bak';
    $lockFile = $storageFile . '.lock';
    $configFile = \getcwd() . '/dom-orm.php';
    $configBackup = $configFile . '.bak';

    if (!\is_dir(\dirname($storageFile))) {
        \mkdir(\dirname($storageFile), 0755, true);
    }

    if (\file_exists($configFile)) {
        \rename($configFile, $configBackup);
    }

    if (\file_exists($storageFile)) {
        \rename($storageFile, $storageBackup);
    }

    \file_put_contents($storageFile, '<data />');

    resetEntityManagerSharedState();
    $manager = new TestEntityManager();
});

afterEach(function () use (&$storageFile, &$storageBackup, &$lockFile, &$configFile, &$configBackup, &$manager): void {
    if (\is_string($storageFile) && \file_exists($storageFile)) {
        \unlink($storageFile);
    }
    if (\is_string($storageBackup) && \is_string($storageFile) && \file_exists($storageBackup)) {
        \rename($storageBackup, $storageFile);
    }
    foreach ([
        $lockFile,
        \getcwd() . '/storage/cache.php',
        \getcwd() . '/storage/export.json',
        \getcwd() . '/storage/export.yaml',
        \getcwd() . '/storage/export.php',
        \getcwd() . '/storage/export.xml',
    ] as $artifact) {
        if (\file_exists($artifact)) {
            \unlink($artifact);
        }
    }
    if (\is_string($configFile) && \file_exists($configFile)) {
        \unlink($configFile);
    }
    if (\is_string($configBackup) && \is_string($configFile) && \file_exists($configBackup)) {
        \rename($configBackup, $configFile);
    }
    resetEntityManagerSharedState();
    $manager = null;
});

it('persist writes a tag entity to the XML store', function () use (&$storageFile, &$manager): void {
    if (!\is_string($storageFile) || !$manager instanceof TestEntityManager) {
        throw new \RuntimeException('Entity manager test fixture was not initialized.');
    }

    $tag = new Tag('Hello', 'test-persist-id');
    $manager->persist($tag);

    $xml = \file_get_contents($storageFile);
    expect($xml)->toContain('test-persist-id');
    expect($xml)->toContain('Hello');
    expect($xml)->toContain('type="tag"');
});

it('persist writes multiple entities to the XML store', function () use (&$storageFile, &$manager): void {
    if (!\is_string($storageFile) || !$manager instanceof TestEntityManager) {
        throw new \RuntimeException('Entity manager test fixture was not initialized.');
    }

    $manager->persist(new Tag('First', 'id-first'));
    $manager->persist(new Tag('Second', 'id-second'));

    $xml = \file_get_contents($storageFile);
    expect($xml)->toContain('id-first');
    expect($xml)->toContain('id-second');
});

it('removeById removes the entity from the XML store', function () use (&$storageFile, &$manager): void {
    if (!\is_string($storageFile) || !$manager instanceof TestEntityManager) {
        throw new \RuntimeException('Entity manager test fixture was not initialized.');
    }

    $tag = new Tag('ToRemove', 'remove-me');
    $manager->persist($tag);

    $xmlBefore = \file_get_contents($storageFile);
    expect($xmlBefore)->toContain('remove-me');

    $manager->removeById('remove-me');

    $xmlAfter = \file_get_contents($storageFile);
    expect($xmlAfter)->not->toContain('remove-me');
});

it('save writes the current DOM state to storage', function () use (&$storageFile, &$manager): void {
    if (!\is_string($storageFile) || !$manager instanceof TestEntityManager) {
        throw new \RuntimeException('Entity manager test fixture was not initialized.');
    }

    $tag = new Tag('SaveTest', 'save-id');
    $manager->persist($tag);

    // Verify storage has the content written by save() inside persist()
    $xml = \file_get_contents($storageFile);
    expect($xml)->toContain('save-id');
});

it('persist creates the local lock file', function () use (&$lockFile, &$manager): void {
    if (!\is_string($lockFile) || !$manager instanceof TestEntityManager) {
        throw new \RuntimeException('Entity manager test fixture was not initialized.');
    }

    $manager->persist(new Tag('LockTest', 'lock-id'));

    expect($lockFile)->toBeFile();
});

it('save-time cache rebuilds can export configured formats', function () use (&$configFile, &$manager): void {
    if (!\is_string($configFile)) {
        throw new \RuntimeException('Entity manager config fixture was not initialized.');
    }

    \file_put_contents($configFile, '<?php return ' . \var_export([
        'dom-orm' => [
            'cache_path' => \getcwd() . '/storage/cache.php',
            'cache_strategy' => 'on_persist',
            'export_on_persist' => [
                'json' => true,
                'php' => true,
            ],
        ],
    ], true) . ';');

    resetEntityManagerSharedState();
    $manager = new TestEntityManager();
    $manager->persist(new Tag('Exported', 'export-id'));

    expect(\getcwd() . '/storage/cache.php')->toBeFile();
    expect(\getcwd() . '/storage/export.json')->toBeFile();
    expect(\getcwd() . '/storage/export.php')->toBeFile();
    expect((string)\file_get_contents(\getcwd() . '/storage/export.json'))->toContain('export-id');
});

// ---------------------------------------------------------------------------
// allowedParentPaths
// ---------------------------------------------------------------------------

it('persist creates the group when the single allowedParentPath resolves to a missing group', function () use (&$storageFile, &$manager): void {
    if (!\is_string($storageFile) || !$manager instanceof TestEntityManager) {
        throw new \RuntimeException('Entity manager test fixture was not initialized.');
    }

    // Storage starts as plain <data /> — no <group type="articles"> exists yet.
    $manager->persist(new ScopedArticle('Hello World', 'art-1'));

    $xml = (string)\file_get_contents($storageFile);
    $dom = new \DOMDocument();
    $dom->loadXML($xml);
    $xpath = new \DOMXPath($dom);

    // The group must have been auto-created.
    $groups = $xpath->query('//group[@type="articles"]');
    expect($groups)->not->toBeFalse();
    expect((int)$groups->length)->toBe(1);

    // The entity must be nested inside the auto-created group.
    $items = $xpath->query('//group[@type="articles"]/item[@type="scoped_article" and @id="art-1"]');
    expect($items)->not->toBeFalse();
    expect((int)$items->length)->toBe(1);
    expect($xml)->toContain('Hello World');
});

it('persist places the entity under the correct group', function () use (&$storageFile, &$manager): void {
    if (!\is_string($storageFile) || !$manager instanceof TestEntityManager) {
        throw new \RuntimeException('Entity manager test fixture was not initialized.');
    }

    // Pre-create the group: verifies that persist() uses the existing node rather
    // than creating a duplicate when the group is already present.
    \file_put_contents($storageFile, '<data><group type="articles"/></data>');

    $manager->persist(new ScopedArticle('Hello World', 'art-1'));

    $xml = (string)\file_get_contents($storageFile);
    $dom = new \DOMDocument();
    $dom->loadXML($xml);
    $xpath = new \DOMXPath($dom);

    // Only one group must exist (no duplicate created).
    expect((int)$xpath->query('//group[@type="articles"]')->length)->toBe(1);

    $items = $xpath->query('//group[@type="articles"]/item[@type="scoped_article" and @id="art-1"]');
    expect($items)->not->toBeFalse();
    expect((int)$items->length)->toBe(1);
    expect($xml)->toContain('Hello World');
});

it('persist throws when multiple allowedParentPaths are defined and no explicit parent is provided', function () use (&$manager): void {
    if (!$manager instanceof TestEntityManager) {
        throw new \RuntimeException('Entity manager test fixture was not initialized.');
    }

    // ScopedComment defines two possible parent paths — persist() cannot auto-resolve
    // the ambiguity and must throw so the caller provides an explicit parent.
    expect(fn () => $manager->persist(new ScopedComment('Great post!', 'com-1')))
        ->toThrow(\InvalidArgumentException::class);
});

it('persist places entity under the explicit parent node when multiple allowedParentPaths are defined', function () use (&$storageFile, &$manager): void {
    if (!\is_string($storageFile) || !$manager instanceof TestEntityManager) {
        throw new \RuntimeException('Entity manager test fixture was not initialized.');
    }

    \file_put_contents($storageFile, <<<XML
        <data><group type="articles"><item type="scoped_article" id="art-1"><fragment name="title"><![CDATA[Hello World]]></fragment></item></group></data>
        XML);

    // Pass the parent as an XPath string — persist() resolves it against the freshly
    // loaded DOM inside its own write-lock cycle, so it always works correctly.
    $manager->persist(
        new ScopedComment('Great post!', 'com-1'),
        '//group[@type="articles"]/item[@type="scoped_article" and @id="art-1"]'
    );

    $result = (string)\file_get_contents($storageFile);
    $resultDom = new \DOMDocument();
    $resultDom->loadXML($result);
    $resultXpath = new \DOMXPath($resultDom);

    $comments = $resultXpath->query('//group[@type="articles"]/item[@type="scoped_article" and @id="art-1"]/item[@type="scoped_comment" and @id="com-1"]');
    expect($comments)->not->toBeFalse();
    expect((int)$comments->length)->toBe(1);
    expect($result)->toContain('Great post!');
});
