<?php

declare(strict_types=1);

use DOM\ORM\Entity\EntityInterface;
use DOM\ORM\Traits\AttributeResolverTrait;
use Tests\Fixtures\Tag;

// Expose private/protected trait methods for testing
class AttributeResolverTestHelper
{
    use AttributeResolverTrait;

    public function exposeResolveEntityType(EntityInterface|string $entity): ?string
    {
        return $this->resolveEntityType($entity);
    }

    public function exposeResolveFragments(EntityInterface|string $entity): ?array
    {
        return $this->resolveFragments($entity);
    }

    public function exposeResolveGroups(EntityInterface|string $entity): ?array
    {
        return $this->resolveGroups($entity);
    }

    public function exposeResolveAllowedParentPaths(EntityInterface|string $entity): ?array
    {
        return $this->resolveAllowedParentPaths($entity);
    }
}

it('resolveEntityType returns entity type for an object', function (): void {
    $resolver = new AttributeResolverTestHelper();
    $tag = new Tag('test');
    expect($resolver->exposeResolveEntityType($tag))->toBe('tag');
});

it('resolveEntityType returns entity type from a class string', function (): void {
    $resolver = new AttributeResolverTestHelper();
    expect($resolver->exposeResolveEntityType(Tag::class))->toBe('tag');
});

it('resolveEntityType throws TypeError for unsupported input type', function (): void {
    $resolver = new AttributeResolverTestHelper();
    expect(fn () => $resolver->exposeResolveEntityType(new \stdClass()))->toThrow(\TypeError::class);
});

it('resolveFragments includes Tag-specific and AbstractEntity base fragments', function (): void {
    $resolver = new AttributeResolverTestHelper();
    $tag = new Tag('test');
    $fragments = $resolver->exposeResolveFragments($tag);

    expect($fragments)->toBeArray()->not->toBeEmpty();

    $propertyNames = \array_column($fragments, 2);
    expect($propertyNames)->toContain('name');
    expect($propertyNames)->toContain('id');
    expect($propertyNames)->toContain('createdAt');
});

it('resolveFragments returns storage strategy per fragment', function (): void {
    $resolver = new AttributeResolverTestHelper();
    $fragments = $resolver->exposeResolveFragments(new Tag('test'));

    // id has storageStrategy 'inline', name has 'standalone'
    $byProperty = [];
    foreach ($fragments as [$strategy, $fragmentName, $propertyName]) {
        $byProperty[$propertyName] = $strategy;
    }
    expect($byProperty['id'])->toBe('inline');
    expect($byProperty['name'])->toBe('standalone');
});

it('resolveGroups returns null for entity without group relations', function (): void {
    $resolver = new AttributeResolverTestHelper();
    expect($resolver->exposeResolveGroups(new Tag('test')))->toBeNull();
});

it('resolveAllowedParentPaths returns null for entity without path constraints', function (): void {
    $resolver = new AttributeResolverTestHelper();
    expect($resolver->exposeResolveAllowedParentPaths(new Tag('test')))->toBeNull();
});

it('getEntityByEntityType returns the class for a known entity type', function (): void {
    $resolver = new AttributeResolverTestHelper();
    expect($resolver->getEntityByEntityType('tag'))->toBe(Tag::class);
});

it('getEntityByEntityType throws for an unknown entity type', function (): void {
    $resolver = new AttributeResolverTestHelper();
    expect(fn () => $resolver->getEntityByEntityType('nonexistent_type_xyz'))->toThrow(\Exception::class);
});

it('getEntityByEntityType returns same result on repeated calls (cache hit)', function (): void {
    $resolver = new AttributeResolverTestHelper();
    $first = $resolver->getEntityByEntityType('tag');
    $second = $resolver->getEntityByEntityType('tag');
    expect($first)->toBe($second)->toBe(Tag::class);
});
