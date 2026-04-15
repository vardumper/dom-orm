<?php

declare(strict_types=1);

use DOM\ORM\Repository\EntityRepository;
use DOM\ORM\Serializer\Encoder\SchemaDecoder;
use DOM\ORM\Serializer\Encoder\SchemaEncoder;
use DOM\ORM\Storage\StorageService;
use DOM\ORM\Traits\EntityManagerTrait;
use Tests\Fixtures\Tag;

final class ReadmeExampleService
{
    use EntityManagerTrait;

    public function addTag(string $name, string $id): void
    {
        $this->persist(new Tag($name, $id));
    }
}

function readmeStorageFile(): string
{
    return getcwd() . '/storage/data.xml';
}

function readmeStorageBackupFile(): string
{
    return readmeStorageFile() . '.bak';
}

beforeEach(function (): void {
    $storageFile = readmeStorageFile();
    $storageBackup = readmeStorageBackupFile();

    if (!is_dir(dirname($storageFile))) {
        mkdir(dirname($storageFile), 0755, true);
    }

    if (file_exists($storageFile)) {
        rename($storageFile, $storageBackup);
    }

    file_put_contents($storageFile, '<data />');
});

afterEach(function (): void {
    $storageFile = readmeStorageFile();
    $storageBackup = readmeStorageBackupFile();

    if (file_exists($storageFile)) {
        unlink($storageFile);
    }

    if (file_exists($storageBackup)) {
        rename($storageBackup, $storageFile);
    }
});

it('covers README persist and repository query flow end-to-end', function (): void {
    $service = new ReadmeExampleService();
    $service->addTag('Alpha', 'readme-alpha');
    $service->addTag('Tag "Beta"', 'readme-beta');
    $service->addTag("Gamma's Tag", 'readme-gamma');

    $repository = new EntityRepository(Tag::class);

    $all = $repository->findAll();
    expect($all)->not->toBeNull();
    expect($all)->toHaveCount(3);

    $byId = $repository->find('readme-beta');
    expect($byId)->toBeInstanceOf(Tag::class);
    /** @var Tag $byId */
    expect($byId->getName())->toBe('Tag "Beta"');

    $byName = $repository->findOneBy([
        'name' => 'Tag "Beta"',
    ]);
    expect($byName)->toBeInstanceOf(Tag::class);
    expect($byName?->getId())->toBe('readme-beta');

    $singleByIdCriteria = $repository->findOneBy([
        'id' => 'readme-gamma',
    ]);
    expect($singleByIdCriteria)->toBeInstanceOf(Tag::class);
    /** @var Tag $singleByIdCriteria */
    expect($singleByIdCriteria->getName())->toBe("Gamma's Tag");
})->group('integration');

it('covers README raw DOMXPath and DOMDocument querying examples', function (): void {
    $service = new ReadmeExampleService();
    $service->addTag('First', 'dom-1');
    $service->addTag('Second', 'dom-2');

    $xml = StorageService::fromConfig()->read();

    $dom = new DOMDocument();
    $loaded = $dom->loadXML($xml);
    expect($loaded)->toBeTrue();

    $xpath = new DOMXPath($dom);

    $allTags = $xpath->query('//item[@type="tag"]');
    expect($allTags)->not->toBeFalse();
    expect($allTags?->length)->toBe(2);

    $singleTag = $xpath->query('//item[@type="tag" and @id="dom-2"]');
    expect($singleTag)->not->toBeFalse();
    expect($singleTag?->length)->toBe(1);

    $items = $dom->getElementsByTagName('item');
    expect($items->length)->toBe(2);
})->group('integration');

it('covers README templating decode example from a DOMElement', function (): void {
    $service = new ReadmeExampleService();
    $service->addTag('TwigTag', 'twig-1');

    $xml = StorageService::fromConfig()->read();

    $dom = new DOMDocument();
    $loaded = $dom->loadXML($xml);
    expect($loaded)->toBeTrue();

    $item = $dom->getElementsByTagName('item')->item(0);
    expect($item)->toBeInstanceOf(DOMElement::class);

    $decoded = (new SchemaDecoder())->decode($item, SchemaEncoder::FORMAT);

    expect($decoded)->toBeArray();
    expect($decoded)->toHaveKey('item-twig-1');
    expect($decoded['item-twig-1'])->toBeArray();
    expect($decoded['item-twig-1']['@id'])->toBe('twig-1');
    expect($decoded['item-twig-1']['@type'])->toBe('tag');
    expect($decoded['item-twig-1']['name'])->toBe('TwigTag');
})->group('integration');

it('covers README remove flow through repository', function (): void {
    $service = new ReadmeExampleService();
    $service->addTag('Keep', 'keep-id');
    $service->addTag('Remove', 'remove-id');

    $repository = new EntityRepository(Tag::class);
    $repository->remove('remove-id');

    expect($repository->find('remove-id'))->toBeNull();

    $remaining = $repository->find('keep-id');
    expect($remaining)->toBeInstanceOf(Tag::class);
    /** @var Tag $remaining */
    expect($remaining->getName())->toBe('Keep');

    $xml = StorageService::fromConfig()->read();
    expect($xml)->toContain('keep-id');
    expect($xml)->not->toContain('remove-id');
})->group('integration');
