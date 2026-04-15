<?php

declare(strict_types=1);

use DOM\ORM\Mapping\Item;

it('instantiates with entityType only', function (): void {
    $item = new Item(entityType: 'tag');
    expect($item->entityType)->toBe('tag');
    expect($item->allowedParentPaths)->toBeNull();
});

it('instantiates with allowedParentPaths', function (): void {
    $item = new Item(entityType: 'comment', allowedParentPaths: ['//data', '//group[@type="comments"]']);
    expect($item->entityType)->toBe('comment');
    expect($item->allowedParentPaths)->toBe(['//data', '//group[@type="comments"]']);
});
