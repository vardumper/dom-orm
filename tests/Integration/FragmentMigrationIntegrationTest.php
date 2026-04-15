<?php

declare(strict_types=1);

use DOM\ORM\Command\Cleanup;
use DOM\ORM\Command\Migrate;
use DOM\ORM\Repository\EntityRepository;
use DOM\ORM\Traits\EntityManagerTrait;
use Tests\Fixtures\MigratingPerson;

// ---------------------------------------------------------------------------
// Helpers
// ---------------------------------------------------------------------------

function migrationStorageFile(): string
{
    return \getcwd() . '/storage/migration_test.xml';
}

function migrationConfigFile(): string
{
    return \getcwd() . '/dom-orm.php';
}

function writeMigrationConfig(): void
{
    \file_put_contents(migrationConfigFile(), '<?php return ' . \var_export([
        'dom-orm' => [
            'flysystem' => [
                'adapter' => \League\Flysystem\Local\LocalFilesystemAdapter::class,
                'config' => [
                    'location' => \dirname(migrationStorageFile()),
                ],
            ],
            'filename' => \basename(migrationStorageFile()),
            'encryption_key' => null,
        ],
    ], true) . ';');
}

/**
 * Write raw XML directly to the test storage file so we can simulate old data
 * that predates the current entity definition.
 */
function writeLegacyXml(string $xml): void
{
    \file_put_contents(migrationStorageFile(), $xml);
}

// ---------------------------------------------------------------------------
// EntityManager stub
// ---------------------------------------------------------------------------

final class MigrationTestEntityManager
{
    use EntityManagerTrait;

    public function add(MigratingPerson $person): void
    {
        $this->persist($person);
    }
}

// ---------------------------------------------------------------------------
// Setup / teardown
// ---------------------------------------------------------------------------

beforeEach(function (): void {
    // Reset statics BEFORE each test so we always start from a clean slate,
    // regardless of what previous test files left in the shared singletons.
    $resetStatics = static function (string $class): void {
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
    };

    $resetStatics(MigrationTestEntityManager::class);
    $resetStatics(\DOM\ORM\Repository\EntityRepository::class);

    writeMigrationConfig();

    if (!\is_dir(\dirname(migrationStorageFile()))) {
        \mkdir(\dirname(migrationStorageFile()), 0755, true);
    }
    if (\file_exists(migrationStorageFile())) {
        \unlink(migrationStorageFile());
    }
});

afterEach(function (): void {
    if (\file_exists(migrationConfigFile())) {
        \unlink(migrationConfigFile());
    }
    if (\file_exists(migrationStorageFile())) {
        \unlink(migrationStorageFile());
    }

    // Reset shared EntityManagerTrait singletons so the next test starts
    // with a fresh StorageService pointing to the correct config/file.
    $resetStatics = static function (string $class): void {
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
    };

    $resetStatics(MigrationTestEntityManager::class);
    $resetStatics(\DOM\ORM\Repository\EntityRepository::class);
});

// ---------------------------------------------------------------------------
// Tests: safe hydration (runtime, no CLI needed)
// ---------------------------------------------------------------------------

it('hydrates entity correctly when XML still has the old fragment name (fullName → name)', function (): void {
    // Simulate legacy XML written before the rename.
    writeLegacyXml(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<data>
  <item type="migrating_person" id="person-1">
    <fragment name="fullName"><![CDATA[Alice Smith]]></fragment>
    <fragment name="createdAt"><![CDATA[2024-01-01T00:00:00+00:00]]></fragment>
  </item>
</data>
XML);

    $repo = new EntityRepository(MigratingPerson::class);
    $person = $repo->find('person-1');

    expect($person)->toBeInstanceOf(MigratingPerson::class);
    expect($person->getName())->toBe('Alice Smith');
})->group('integration');

it('hydrates entity and silently ignores a removed fragment (legacyBio)', function (): void {
    writeLegacyXml(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<data>
  <item type="migrating_person" id="person-2">
    <fragment name="name"><![CDATA[Bob]]></fragment>
    <fragment name="legacyBio"><![CDATA[Outdated bio that was removed]]></fragment>
    <fragment name="createdAt"><![CDATA[2024-01-01T00:00:00+00:00]]></fragment>
  </item>
</data>
XML);

    $repo = new EntityRepository(MigratingPerson::class);
    $person = $repo->find('person-2');

    expect($person)->toBeInstanceOf(MigratingPerson::class);
    expect($person->getName())->toBe('Bob');
})->group('integration');

it('does not throw when XML contains a completely unknown fragment with no setter', function (): void {
    writeLegacyXml(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<data>
  <item type="migrating_person" id="person-3">
    <fragment name="name"><![CDATA[Carol]]></fragment>
    <fragment name="unknownOrphan"><![CDATA[ghost data]]></fragment>
    <fragment name="createdAt"><![CDATA[2024-01-01T00:00:00+00:00]]></fragment>
  </item>
</data>
XML);

    $repo = new EntityRepository(MigratingPerson::class);
    $person = $repo->find('person-3');

    expect($person)->toBeInstanceOf(MigratingPerson::class);
    expect($person->getName())->toBe('Carol');
})->group('integration');

it('prefers new fragment name when BOTH old and new exist in XML (conflict rule)', function (): void {
    writeLegacyXml(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<data>
  <item type="migrating_person" id="person-4">
    <fragment name="fullName"><![CDATA[Old Name]]></fragment>
    <fragment name="name"><![CDATA[New Name]]></fragment>
    <fragment name="createdAt"><![CDATA[2024-01-01T00:00:00+00:00]]></fragment>
  </item>
</data>
XML);

    $repo = new EntityRepository(MigratingPerson::class);
    $person = $repo->find('person-4');

    expect($person)->toBeInstanceOf(MigratingPerson::class);
    expect($person->getName())->toBe('New Name');
})->group('integration');

// ---------------------------------------------------------------------------
// Tests: Migrate command (dry-run)
// ---------------------------------------------------------------------------

it('migrate dry-run reports correct counts and does not mutate XML', function (): void {
    writeLegacyXml(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<data>
  <item type="migrating_person" id="person-5">
    <fragment name="fullName"><![CDATA[Dave]]></fragment>
    <fragment name="legacyBio"><![CDATA[Old bio]]></fragment>
    <fragment name="createdAt"><![CDATA[2024-01-01T00:00:00+00:00]]></fragment>
  </item>
</data>
XML);

    $originalXml = \file_get_contents(migrationStorageFile());

    $stats = Migrate::run(dryRun: true);

    expect($stats['renamed'])->toBe(1)   // fullName → name
        ->and($stats['removed'])->toBe(1)  // legacyBio → null
        ->and($stats['items_affected'])->toBe(1);

    // XML must not have changed
    expect(\file_get_contents(migrationStorageFile()))->toBe($originalXml);
})->group('integration');

it('migrate apply renames and removes fragments in XML', function (): void {
    writeLegacyXml(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<data>
  <item type="migrating_person" id="person-6">
    <fragment name="fullName"><![CDATA[Eve]]></fragment>
    <fragment name="legacyBio"><![CDATA[Old bio]]></fragment>
    <fragment name="createdAt"><![CDATA[2024-01-01T00:00:00+00:00]]></fragment>
  </item>
</data>
XML);

    $stats = Migrate::run(dryRun: false);

    expect($stats['renamed'])->toBe(1)
        ->and($stats['removed'])->toBe(1)
        ->and($stats['items_affected'])->toBe(1);

    $xml = \file_get_contents(migrationStorageFile());
    expect($xml)->toContain('name="name"')
        ->and($xml)->not->toContain('name="fullName"')
        ->and($xml)->not->toContain('name="legacyBio"');

    // Entity must now hydrate correctly directly from new XML.
    $repo = new EntityRepository(MigratingPerson::class);
    $person = $repo->find('person-6');
    expect($person->getName())->toBe('Eve');
})->group('integration');

// ---------------------------------------------------------------------------
// Tests: Cleanup command
// ---------------------------------------------------------------------------

it('cleanup dry-run reports orphaned fragments and does not mutate XML', function (): void {
    writeLegacyXml(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<data>
  <item type="migrating_person" id="person-7">
    <fragment name="name"><![CDATA[Frank]]></fragment>
    <fragment name="totallyOrphan"><![CDATA[no class property]]></fragment>
    <fragment name="createdAt"><![CDATA[2024-01-01T00:00:00+00:00]]></fragment>
  </item>
</data>
XML);

    $originalXml = \file_get_contents(migrationStorageFile());
    $stats = Cleanup::run(dryRun: true);

    expect($stats['removed'])->toBe(1)
        ->and($stats['items_affected'])->toBe(1);

    expect(\file_get_contents(migrationStorageFile()))->toBe($originalXml);
})->group('integration');

it('cleanup apply removes orphaned fragments from XML', function (): void {
    writeLegacyXml(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<data>
  <item type="migrating_person" id="person-8">
    <fragment name="name"><![CDATA[Grace]]></fragment>
    <fragment name="ghost1"><![CDATA[orphan a]]></fragment>
    <fragment name="ghost2"><![CDATA[orphan b]]></fragment>
    <fragment name="createdAt"><![CDATA[2024-01-01T00:00:00+00:00]]></fragment>
  </item>
</data>
XML);

    $stats = Cleanup::run(dryRun: false);

    expect($stats['removed'])->toBe(2)
        ->and($stats['items_affected'])->toBe(1);

    $xml = \file_get_contents(migrationStorageFile());
    expect($xml)->not->toContain('name="ghost1"')
        ->and($xml)->not->toContain('name="ghost2"')
        ->and($xml)->toContain('name="name"');
})->group('integration');

it('cleanup skips item types that have no matching entity class and reports them', function (): void {
    writeLegacyXml(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<data>
  <item type="migrating_person" id="person-9">
    <fragment name="name"><![CDATA[Heidi]]></fragment>
    <fragment name="createdAt"><![CDATA[2024-01-01T00:00:00+00:00]]></fragment>
  </item>
  <item type="unknown_entity_type" id="unk-1">
    <fragment name="whatever"><![CDATA[ignored]]></fragment>
  </item>
</data>
XML);

    $stats = Cleanup::run(dryRun: true);

    expect($stats['unknown_types'])->toContain('unknown_entity_type');
})->group('integration');
