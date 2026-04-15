<?php

declare(strict_types=1);

use Tests\Fixtures\Tag;

it('generates a uuid hex id when constructed without id argument', function (): void {
    $tag = new Tag('Test');
    expect($tag->getId())->toBeString()->toHaveLength(32);
});

it('uses provided id when given', function (): void {
    $tag = new Tag('Test', 'my-custom-id');
    expect($tag->getId())->toBe('my-custom-id');
});

it('sets createdAt to current time when not provided', function (): void {
    $before = new \DateTimeImmutable();
    $tag = new Tag('Test');
    $after = new \DateTimeImmutable();
    expect($tag->getCreatedAt())->toBeInstanceOf(\DateTimeInterface::class);
    expect($tag->getCreatedAt()->getTimestamp())
        ->toBeGreaterThanOrEqual($before->getTimestamp())
        ->toBeLessThanOrEqual($after->getTimestamp());
});

it('uses provided createdAt when given', function (): void {
    $date = new \DateTimeImmutable('2024-01-01T00:00:00Z');
    $tag = new Tag('Test', null, $date);
    expect($tag->getCreatedAt())->toBe($date);
});

it('setId returns static for fluent chaining', function (): void {
    $tag = new Tag('Test');
    $result = $tag->setId('new-id');
    expect($result)->toBe($tag);
    expect($tag->getId())->toBe('new-id');
});

it('setCreatedAt returns static and updates the value', function (): void {
    $tag = new Tag('Test');
    $date = new \DateTimeImmutable();
    $result = $tag->setCreatedAt($date);
    expect($result)->toBe($tag);
    expect($tag->getCreatedAt())->toBe($date);
});

it('getUpdatedAt returns null by default', function (): void {
    $tag = new Tag('Test');
    expect($tag->getUpdatedAt())->toBeNull();
});

it('setUpdatedAt returns static and persists value', function (): void {
    $tag = new Tag('Test');
    $date = new \DateTimeImmutable();
    $result = $tag->setUpdatedAt($date);
    expect($result)->toBe($tag);
    expect($tag->getUpdatedAt())->toBe($date);
});

it('getDeletedAt returns null by default', function (): void {
    $tag = new Tag('Test');
    expect($tag->getDeletedAt())->toBeNull();
});

it('setDeletedAt returns static and persists value', function (): void {
    $tag = new Tag('Test');
    $date = new \DateTimeImmutable();
    $result = $tag->setDeletedAt($date);
    expect($result)->toBe($tag);
    expect($tag->getDeletedAt())->toBe($date);
});
