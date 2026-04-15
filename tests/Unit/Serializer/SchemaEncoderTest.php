<?php

declare(strict_types=1);

use DOM\ORM\Serializer\Encoder\SchemaEncoder;

// Valid XML that satisfies schema.xsd: root is <data> with <item type="..."> children
$validXml = '<data><item type="tag" id="abc123"><fragment name="name"><![CDATA[TestTag]]></fragment></item></data>';

it('supportsEncoding returns true for the dom_orm_schema format', function (): void {
    expect((new SchemaEncoder())->supportsEncoding(SchemaEncoder::FORMAT))->toBeTrue();
});

it('supportsEncoding returns false for other formats', function (): void {
    expect((new SchemaEncoder())->supportsEncoding('json'))->toBeFalse();
    expect((new SchemaEncoder())->supportsEncoding('xml'))->toBeFalse();
});

it('encode returns a valid XML string', function (): void {
    $encoder = new SchemaEncoder();
    $xml = $encoder->encode([
        'item-abc123' => [
            '@id' => 'abc123',
            '@type' => 'tag',
            'name' => 'TestTag',
        ],
    ], SchemaEncoder::FORMAT);

    expect($xml)->toBeString();
    $dom = new \DOMDocument();
    expect($dom->loadXML($xml))->not->toBeFalse();
});

it('encode sets attributes for keys prefixed with @', function (): void {
    $encoder = new SchemaEncoder();
    $xml = $encoder->encode([
        'item-x' => [
            '@id' => 'x',
            '@type' => 'tag',
            'name' => 'Foo',
        ],
    ], SchemaEncoder::FORMAT);

    $dom = new \DOMDocument();
    $dom->loadXML($xml);
    $items = $dom->getElementsByTagName('item');
    expect($items->length)->toBeGreaterThanOrEqual(1);
    expect($items->item(0)->getAttribute('id'))->toBe('x');
    expect($items->item(0)->getAttribute('type'))->toBe('tag');
});

it('encode wraps string values in fragment elements with CDATA', function (): void {
    $encoder = new SchemaEncoder();
    $xml = $encoder->encode([
        'item-x' => [
            '@id' => 'x',
            '@type' => 'tag',
            'name' => 'TestTag',
        ],
    ], SchemaEncoder::FORMAT);

    $dom = new \DOMDocument();
    $dom->loadXML($xml);
    $fragments = $dom->getElementsByTagName('fragment');
    expect($fragments->length)->toBeGreaterThanOrEqual(1);
    expect($fragments->item(0)->getAttribute('name'))->toBe('name');
    expect($fragments->item(0)->nodeValue)->toBe('TestTag');
});

it('encode throws InvalidArgumentException for non-array input', function (): void {
    $encoder = new SchemaEncoder();
    expect(fn () => $encoder->encode('not-an-array', SchemaEncoder::FORMAT))
        ->toThrow(\InvalidArgumentException::class);
});

it('decode with a valid XML string returns a structured array', function ($xml): void {
    $encoder = new SchemaEncoder();
    $result = $encoder->decode($xml, SchemaEncoder::FORMAT);
    expect($result)->toBeArray()->toHaveKey('data');
    expect($result['data'])->toBeArray()->toHaveLength(1);
})->with([
    '<data><item type="tag" id="abc123"><fragment name="name"><![CDATA[TestTag]]></fragment></item></data>',
]);

it('decode with a DOMDocument returns a structured array', function ($xml): void {
    $encoder = new SchemaEncoder();
    $dom = new \DOMDocument();
    $dom->loadXML($xml);
    $result = $encoder->decode($dom, SchemaEncoder::FORMAT);
    expect($result)->toBeArray()->toHaveKey('data');
})->with([
    '<data><item type="tag" id="abc123"><fragment name="name"><![CDATA[TestTag]]></fragment></item></data>',
]);

it('decode with an empty data document returns empty data array', function (): void {
    $encoder = new SchemaEncoder();
    $result = $encoder->decode('<data></data>', SchemaEncoder::FORMAT);
    expect($result)->toBeArray()->toHaveKey('data');
    expect($result['data'])->toBeArray()->toBeEmpty();
});

it('decode throws UnexpectedValueException for non-XML string', function (): void {
    $encoder = new SchemaEncoder();
    expect(fn () => $encoder->decode('not xml at all', SchemaEncoder::FORMAT))
        ->toThrow(\UnexpectedValueException::class);
});

it('decode extracts fragment name and value correctly', function (): void {
    $encoder = new SchemaEncoder();
    $xml = '<data><item type="tag" id="id1"><fragment name="name"><![CDATA[Hello]]></fragment></item></data>';
    $result = $encoder->decode($xml, SchemaEncoder::FORMAT);
    $item = $result['data'][0];
    $itemData = $item[\array_key_first($item)];
    expect($itemData['name'])->toBe('Hello');
    expect($itemData['@type'])->toBe('tag');
    expect($itemData['@id'])->toBe('id1');
});
