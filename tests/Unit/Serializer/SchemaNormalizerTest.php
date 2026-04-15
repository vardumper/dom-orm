<?php

declare(strict_types=1);

use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Serializer\Normalizer\SchemaNormalizer;
use Tests\Fixtures\Tag;

it('normalizes a Tag entity to the expected array structure', function (): void {
    $normalizer = new SchemaNormalizer();
    $tag = new Tag('TestTag', 'abc123', new \DateTimeImmutable('2024-01-01T00:00:00+00:00'));

    $result = $normalizer->normalize($tag, SchemaNormalizer::FORMAT);

    expect($result)->toBeArray()->toHaveKey('item-abc123');
    $item = $result['item-abc123'];
    expect($item['@id'])->toBe('abc123');
    expect($item['@type'])->toBe('tag');
    expect($item['name'])->toBe('TestTag');
    expect($item['createdAt'])->toBe('2024-01-01T00:00:00+00:00');
});

it('includes id as an inline attribute (@id is present in item data)', function (): void {
    $normalizer = new SchemaNormalizer();
    $tag = new Tag('Hello', 'id-999');
    $result = $normalizer->normalize($tag, SchemaNormalizer::FORMAT);
    expect($result['item-id-999'])->toHaveKey('@id');
    expect($result['item-id-999'])->toHaveKey('@type');
});

it('throws InvalidArgumentException when normalizing a non-AbstractEntity', function (): void {
    $normalizer = new SchemaNormalizer();
    expect(fn () => $normalizer->normalize(new \stdClass(), SchemaNormalizer::FORMAT))
        ->toThrow(\InvalidArgumentException::class);
});

it('supportsNormalization returns false for wrong format', function (): void {
    $normalizer = new SchemaNormalizer();
    expect($normalizer->supportsNormalization(new Tag('test'), 'json'))->toBeFalse();
});

it('supportsNormalization returns true for AbstractEntity with correct format', function (): void {
    $normalizer = new SchemaNormalizer();
    expect($normalizer->supportsNormalization(new Tag('test'), SchemaNormalizer::FORMAT))->toBeTrue();
});

it('supportsNormalization returns false for non-AbstractEntity with correct format', function (): void {
    $normalizer = new SchemaNormalizer();
    expect($normalizer->supportsNormalization(new \stdClass(), SchemaNormalizer::FORMAT))->toBeFalse();
});

it('getSupportedTypes includes AbstractEntity as a key', function (): void {
    $normalizer = new SchemaNormalizer();
    $types = $normalizer->getSupportedTypes(SchemaNormalizer::FORMAT);
    expect($types)->toBeArray()->toHaveKey(AbstractEntity::class);
});

it('hasCacheableSupportsMethod returns true', function (): void {
    $normalizer = new SchemaNormalizer();
    expect($normalizer->hasCacheableSupportsMethod())->toBeTrue();
});
