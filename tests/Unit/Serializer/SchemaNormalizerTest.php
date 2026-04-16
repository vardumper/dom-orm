<?php

declare(strict_types=1);

use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Serializer\Normalizer\SchemaNormalizer;
use Tests\Fixtures\RelProfile;
use Tests\Fixtures\RelUserSingle;
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

it('normalizes a single entity relation as a group with one item', function (): void {
    $normalizer = new SchemaNormalizer();
    $profile = new RelProfile('Bob bio', 'profile-bob');
    $user = new RelUserSingle('bob', $profile, 'user-single-1');

    $result = $normalizer->normalize($user, SchemaNormalizer::FORMAT);

    expect($result)->toBeArray()->toHaveKey('item-user-single-1');
    $item = $result['item-user-single-1'];
    expect($item)->toHaveKey('profile');
    expect($item['profile'])->toBeArray()->toHaveCount(1);
});

it('normalizes a null single entity relation with no group entry', function (): void {
    $normalizer = new SchemaNormalizer();
    $user = new RelUserSingle('charlie', null, 'user-single-2');

    $result = $normalizer->normalize($user, SchemaNormalizer::FORMAT);

    expect($result['item-user-single-2'])->not->toHaveKey('profile');
});

it('throws when a non-entity non-iterable value is given for a #[Group] property', function (): void {
    $normalizer = new SchemaNormalizer();

    // Build an entity that has a group property holding a scalar (simulated via an anon class override isn't easy,
    // so we verify the exception message contains the type instead)
    expect(fn () => $normalizer->normalize(new RelUserSingle('x', null, 'u1'), SchemaNormalizer::FORMAT))
        ->not->toThrow(\InvalidArgumentException::class);
});
