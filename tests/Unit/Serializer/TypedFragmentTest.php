<?php

declare(strict_types=1);

use DOM\ORM\Serializer\Encoder\SchemaEncoder;
use DOM\ORM\Serializer\Normalizer\SchemaDenormalizer;
use DOM\ORM\Serializer\Normalizer\SchemaNormalizer;
use Tests\Fixtures\ArrayFragmentEntity;
use Tests\Fixtures\JsonScalarArrayFragmentEntity;
use Tests\Fixtures\NullableTypedFieldEntity;
use Tests\Fixtures\TypedFieldEntity;

// ---------------------------------------------------------------------------
// Helper: build the decoded-array structure that SchemaDenormalizer expects
// (mirrors the output of SchemaEncoder::decode)
// ---------------------------------------------------------------------------
function makeTypedData(
    string $id = 'typed-1',
    string $label = 'hello',
    string $count = '42',
    string $ratio = '3.14',
    string $active = '1',
): array {
    return [
        'data' => [
            [
                'item-' . $id => [
                    '@id' => $id,
                    '@type' => 'typed_entity',
                    'label' => $label,
                    'count' => $count,
                    'ratio' => $ratio,
                    'active' => $active,
                ],
            ],
        ],
    ];
}

/**
 * Helper for nullable scalar fixture payloads.
 * Null values are represented by omitted keys to match fragment absence in XML.
 */
function makeNullableTypedData(
    string $id = 'nullable-1',
    ?string $label = null,
    ?string $count = null,
    ?string $ratio = null,
    ?string $active = null,
): array {
    $item = [
        '@id' => $id,
        '@type' => 'nullable_typed_entity',
    ];

    if ($label !== null) {
        $item['label'] = $label;
    }
    if ($count !== null) {
        $item['count'] = $count;
    }
    if ($ratio !== null) {
        $item['ratio'] = $ratio;
    }
    if ($active !== null) {
        $item['active'] = $active;
    }

    return [
        'data' => [
            [
                'item-' . $id => $item,
            ],
        ],
    ];
}

// ---------------------------------------------------------------------------
// SchemaNormalizer — coerces int/float/bool to string in normalized output
// ---------------------------------------------------------------------------

it('normalizer coerces int fragment to string', function (): void {
    $normalizer = new SchemaNormalizer();
    $entity = new TypedFieldEntity('hello', 42, 3.14, true, 'typed-1');

    $result = $normalizer->normalize($entity, SchemaNormalizer::FORMAT);
    $item = $result['item-typed-1'];

    expect($item['count'])->toBeString()->toBe('42');
})->group('typed-fragments');

it('normalizer coerces float fragment to string', function (): void {
    $normalizer = new SchemaNormalizer();
    $entity = new TypedFieldEntity('hello', 42, 3.14, true, 'typed-1');

    $result = $normalizer->normalize($entity, SchemaNormalizer::FORMAT);
    $item = $result['item-typed-1'];

    expect($item['ratio'])->toBeString()->toBe('3.14');
})->group('typed-fragments');

it('normalizer coerces bool true fragment to string "1"', function (): void {
    $normalizer = new SchemaNormalizer();
    $entity = new TypedFieldEntity('hello', 42, 3.14, true, 'typed-1');

    $result = $normalizer->normalize($entity, SchemaNormalizer::FORMAT);
    $item = $result['item-typed-1'];

    expect($item['active'])->toBeString()->toBe('1');
})->group('typed-fragments');

it('normalizer coerces bool false fragment to empty string', function (): void {
    $normalizer = new SchemaNormalizer();
    $entity = new TypedFieldEntity('hello', 0, 0.0, false, 'typed-2');

    $result = $normalizer->normalize($entity, SchemaNormalizer::FORMAT);
    $item = $result['item-typed-2'];

    expect($item['active'])->toBeString()->toBe('');
})->group('typed-fragments');

it('normalizer preserves string fragment unchanged', function (): void {
    $normalizer = new SchemaNormalizer();
    $entity = new TypedFieldEntity('world', 1, 1.0, false, 'typed-3');

    $result = $normalizer->normalize($entity, SchemaNormalizer::FORMAT);
    $item = $result['item-typed-3'];

    expect($item['label'])->toBeString()->toBe('world');
})->group('typed-fragments');

// ---------------------------------------------------------------------------
// SchemaEncoder — produces <fragment> CDATA for int/float/bool values
// ---------------------------------------------------------------------------

it('encoder writes an int value as a CDATA fragment', function (): void {
    $encoder = new SchemaEncoder();
    $xml = $encoder->encode([
        'item-typed-1' => [
            '@id' => 'typed-1',
            '@type' => 'typed_entity',
            'count' => 42,
        ],
    ], SchemaEncoder::FORMAT);

    $dom = new \DOMDocument();
    $dom->loadXML($xml);
    $fragments = $dom->getElementsByTagName('fragment');
    expect($fragments->length)->toBe(1);
    expect($fragments->item(0)->getAttribute('name'))->toBe('count');
    expect($fragments->item(0)->nodeValue)->toBe('42');
})->group('typed-fragments');

it('encoder writes a float value as a CDATA fragment', function (): void {
    $encoder = new SchemaEncoder();
    $xml = $encoder->encode([
        'item-typed-1' => [
            '@id' => 'typed-1',
            '@type' => 'typed_entity',
            'ratio' => 3.14,
        ],
    ], SchemaEncoder::FORMAT);

    $dom = new \DOMDocument();
    $dom->loadXML($xml);
    $fragments = $dom->getElementsByTagName('fragment');
    expect($fragments->length)->toBe(1);
    expect($fragments->item(0)->nodeValue)->toBe('3.14');
})->group('typed-fragments');

it('encoder writes bool true as "1" CDATA fragment', function (): void {
    $encoder = new SchemaEncoder();
    $xml = $encoder->encode([
        'item-typed-1' => [
            '@id' => 'typed-1',
            '@type' => 'typed_entity',
            'active' => true,
        ],
    ], SchemaEncoder::FORMAT);

    $dom = new \DOMDocument();
    $dom->loadXML($xml);
    $fragments = $dom->getElementsByTagName('fragment');
    expect($fragments->length)->toBe(1);
    expect($fragments->item(0)->nodeValue)->toBe('1');
})->group('typed-fragments');

it('encoder writes bool false as "" CDATA fragment', function (): void {
    $encoder = new SchemaEncoder();
    $xml = $encoder->encode([
        'item-typed-1' => [
            '@id' => 'typed-1',
            '@type' => 'typed_entity',
            'active' => false,
        ],
    ], SchemaEncoder::FORMAT);

    $dom = new \DOMDocument();
    $dom->loadXML($xml);
    $fragments = $dom->getElementsByTagName('fragment');
    expect($fragments->length)->toBe(1);
    expect($fragments->item(0)->nodeValue)->toBe('');
})->group('typed-fragments');

// ---------------------------------------------------------------------------
// SchemaDenormalizer — casts XML strings back to int/float/bool
// ---------------------------------------------------------------------------

it('denormalizer casts string "42" back to int for int-typed property', function (): void {
    $denormalizer = new SchemaDenormalizer();
    $collection = $denormalizer->denormalize(
        makeTypedData(count: '42'),
        TypedFieldEntity::class,
        SchemaNormalizer::FORMAT,
    );

    /** @var TypedFieldEntity $entity */
    $entity = $collection->first();
    expect($entity->getCount())->toBeInt()->toBe(42);
})->group('typed-fragments');

it('denormalizer casts string "3.14" back to float for float-typed property', function (): void {
    $denormalizer = new SchemaDenormalizer();
    $collection = $denormalizer->denormalize(
        makeTypedData(ratio: '3.14'),
        TypedFieldEntity::class,
        SchemaNormalizer::FORMAT,
    );

    /** @var TypedFieldEntity $entity */
    $entity = $collection->first();
    expect($entity->getRatio())->toBeFloat()->toBe(3.14);
})->group('typed-fragments');

it('denormalizer casts string "1" back to bool true for bool-typed property', function (): void {
    $denormalizer = new SchemaDenormalizer();
    $collection = $denormalizer->denormalize(
        makeTypedData(active: '1'),
        TypedFieldEntity::class,
        SchemaNormalizer::FORMAT,
    );

    /** @var TypedFieldEntity $entity */
    $entity = $collection->first();
    expect($entity->getActive())->toBeBool()->toBeTrue();
})->group('typed-fragments');

it('denormalizer casts string "true" back to bool true for bool-typed property', function (): void {
    $denormalizer = new SchemaDenormalizer();
    $collection = $denormalizer->denormalize(
        makeTypedData(active: 'true'),
        TypedFieldEntity::class,
        SchemaNormalizer::FORMAT,
    );

    /** @var TypedFieldEntity $entity */
    $entity = $collection->first();
    expect($entity->getActive())->toBeBool()->toBeTrue();
})->group('typed-fragments');

it('denormalizer casts string "false" back to bool false for bool-typed property', function (): void {
    $denormalizer = new SchemaDenormalizer();
    $collection = $denormalizer->denormalize(
        makeTypedData(active: 'false'),
        TypedFieldEntity::class,
        SchemaNormalizer::FORMAT,
    );

    /** @var TypedFieldEntity $entity */
    $entity = $collection->first();
    expect($entity->getActive())->toBeBool()->toBeFalse();
})->group('typed-fragments');

it('denormalizer casts string "0" back to bool false for bool-typed property', function (): void {
    $denormalizer = new SchemaDenormalizer();
    $collection = $denormalizer->denormalize(
        makeTypedData(active: '0'),
        TypedFieldEntity::class,
        SchemaNormalizer::FORMAT,
    );

    /** @var TypedFieldEntity $entity */
    $entity = $collection->first();
    expect($entity->getActive())->toBeBool()->toBeFalse();
})->group('typed-fragments');

it('denormalizer casts empty string back to bool false for bool-typed property', function (): void {
    $denormalizer = new SchemaDenormalizer();
    $collection = $denormalizer->denormalize(
        makeTypedData(active: ''),
        TypedFieldEntity::class,
        SchemaNormalizer::FORMAT,
    );

    /** @var TypedFieldEntity $entity */
    $entity = $collection->first();
    expect($entity->getActive())->toBeBool()->toBeFalse();
})->group('typed-fragments');

it('denormalizer preserves negative int correctly', function (): void {
    $denormalizer = new SchemaDenormalizer();
    $collection = $denormalizer->denormalize(
        makeTypedData(count: '-7'),
        TypedFieldEntity::class,
        SchemaNormalizer::FORMAT,
    );

    expect($collection->first()->getCount())->toBe(-7);
})->group('typed-fragments');

it('denormalizer preserves negative float correctly', function (): void {
    $denormalizer = new SchemaDenormalizer();
    $collection = $denormalizer->denormalize(
        makeTypedData(ratio: '-0.5'),
        TypedFieldEntity::class,
        SchemaNormalizer::FORMAT,
    );

    expect($collection->first()->getRatio())->toBe(-0.5);
})->group('typed-fragments');

it('denormalizer casts scientific notation string to float', function (): void {
    $denormalizer = new SchemaDenormalizer();
    $collection = $denormalizer->denormalize(
        makeTypedData(ratio: '1e-3'),
        TypedFieldEntity::class,
        SchemaNormalizer::FORMAT,
    );

    expect($collection->first()->getRatio())->toBeFloat()->toBe(0.001);
})->group('typed-fragments');

it('denormalizer keeps nullable scalar fields as null when fragment keys are absent', function (): void {
    $denormalizer = new SchemaDenormalizer();
    $collection = $denormalizer->denormalize(
        makeNullableTypedData(),
        NullableTypedFieldEntity::class,
        SchemaNormalizer::FORMAT,
    );

    /** @var NullableTypedFieldEntity $entity */
    $entity = $collection->first();
    expect($entity->getLabel())->toBeNull();
    expect($entity->getCount())->toBeNull();
    expect($entity->getRatio())->toBeNull();
    expect($entity->getActive())->toBeNull();
})->group('typed-fragments');

it('encoder rejects array fragment input with a clear exception', function (): void {
    $encoder = new SchemaEncoder();

    expect(function () use ($encoder): void {
        $encoder->encode([
            'item-typed-1' => [
                '@id' => 'typed-1',
                '@type' => 'typed_entity',
                'count' => [1, 2],
            ],
        ], SchemaEncoder::FORMAT);
    })->toThrow(\InvalidArgumentException::class, 'Only arrays are supported.');
})->group('typed-fragments');

it('normalizer rejects array Fragment values by default and points to Group mappings', function (): void {
    $normalizer = new SchemaNormalizer();
    $entity = new ArrayFragmentEntity(['news', 'updates'], 'arr-1');

    expect(fn () => $normalizer->normalize($entity, SchemaNormalizer::FORMAT))
        ->toThrow(\InvalidArgumentException::class, 'prefer #[Group] mappings for domain collections');
})->group('typed-fragments');

it('normalizer serializes json_scalar Fragment arrays as JSON strings', function (): void {
    $normalizer = new SchemaNormalizer();
    $entity = new JsonScalarArrayFragmentEntity([
        'tags' => ['news', 'updates'],
        'count' => 2,
        'enabled' => true,
        'nullable' => null,
    ], 'json-array-1');

    $result = $normalizer->normalize($entity, SchemaNormalizer::FORMAT);
    $item = $result['item-json-array-1'];

    expect($item['payload'])->toBeString();
    expect(\json_decode($item['payload'], true, 512, \JSON_THROW_ON_ERROR))->toBe([
        'tags' => ['news', 'updates'],
        'count' => 2,
        'enabled' => true,
        'nullable' => null,
    ]);
})->group('typed-fragments');

it('denormalizer restores json_scalar Fragment payload back to array', function (): void {
    $denormalizer = new SchemaDenormalizer();
    $collection = $denormalizer->denormalize(
        [
            'data' => [
                [
                    'item-json-array-1' => [
                        '@id' => 'json-array-1',
                        '@type' => 'json_scalar_array_fragment_entity',
                        'payload' => '{"tags":["news","updates"],"count":2,"enabled":true,"nullable":null}',
                    ],
                ],
            ],
        ],
        JsonScalarArrayFragmentEntity::class,
        SchemaNormalizer::FORMAT,
    );

    /** @var JsonScalarArrayFragmentEntity $entity */
    $entity = $collection->first();
    expect($entity->getPayload())->toBe([
        'tags' => ['news', 'updates'],
        'count' => 2,
        'enabled' => true,
        'nullable' => null,
    ]);
})->group('typed-fragments');

// ---------------------------------------------------------------------------
// Full round-trip: entity → normalize → encode (XML) → decode → denormalize → entity
// The SchemaEncoder encodes each entity as an <item> root; the decoder expects
// a <data> root (schema-validated), so we wrap before decoding.
// ---------------------------------------------------------------------------

function roundTrip(TypedFieldEntity $entity): TypedFieldEntity
{
    $normalizer = new SchemaNormalizer();
    $encoder = new SchemaEncoder();
    $denormalizer = new SchemaDenormalizer();

    $normalized = $normalizer->normalize($entity, SchemaNormalizer::FORMAT);

    // Encoder produces <item> as root element; wrap in <data> for a valid schema document.
    $itemXml = preg_replace('/^<\?xml[^>]+\?>\s*/', '', $encoder->encode($normalized, SchemaEncoder::FORMAT));
    $wrappedXml = '<?xml version="1.0" encoding="UTF-8"?><data>' . $itemXml . '</data>';

    $decoded = $encoder->decode($wrappedXml, SchemaEncoder::FORMAT);
    $collection = $denormalizer->denormalize($decoded, TypedFieldEntity::class, SchemaNormalizer::FORMAT);

    return $collection->first();
}

it('round-trip preserves int fragment value', function (): void {
    $hydrated = roundTrip(new TypedFieldEntity('hello', 99, 2.71, true, 'rt-1'));
    expect($hydrated->getCount())->toBeInt()->toBe(99);
})->group('typed-fragments', 'round-trip');

it('round-trip preserves float fragment value', function (): void {
    $hydrated = roundTrip(new TypedFieldEntity('hello', 1, 2.71, true, 'rt-2'));
    expect($hydrated->getRatio())->toBeFloat()->toBe(2.71);
})->group('typed-fragments', 'round-trip');

it('round-trip preserves bool true fragment value', function (): void {
    $hydrated = roundTrip(new TypedFieldEntity('hello', 1, 1.0, true, 'rt-3'));
    expect($hydrated->getActive())->toBeBool()->toBeTrue();
})->group('typed-fragments', 'round-trip');

it('round-trip preserves bool false fragment value', function (): void {
    $hydrated = roundTrip(new TypedFieldEntity('hello', 0, 0.0, false, 'rt-4'));
    expect($hydrated->getActive())->toBeBool()->toBeFalse();
})->group('typed-fragments', 'round-trip');

it('round-trip preserves string fragment value unchanged', function (): void {
    $hydrated = roundTrip(new TypedFieldEntity('greetings', 1, 1.0, true, 'rt-5'));
    expect($hydrated->getLabel())->toBeString()->toBe('greetings');
})->group('typed-fragments', 'round-trip');

it('round-trip preserves entity id', function (): void {
    $hydrated = roundTrip(new TypedFieldEntity('x', 1, 1.0, true, 'rt-id-check'));
    expect($hydrated->getId())->toBe('rt-id-check');
})->group('typed-fragments', 'round-trip');
