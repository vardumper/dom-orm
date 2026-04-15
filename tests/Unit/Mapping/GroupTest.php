<?php

declare(strict_types=1);

use DOM\ORM\Mapping\Group;
use Tests\Fixtures\Tag;

it('instantiates with entity class and defaults', function (): void {
    $group = new Group(entity: Tag::class);
    expect($group->entity)->toBe(Tag::class);
    expect($group->groupType)->toBeNull();
    expect($group->allowedParentPaths)->toBeNull();
    expect($group->fetch)->toBe('EAGER');
});

it('instantiates with all arguments', function (): void {
    $group = new Group(
        entity: Tag::class,
        groupType: 'tags',
        allowedParentPaths: ['//data'],
        fetch: 'LAZY'
    );
    expect($group->entity)->toBe(Tag::class);
    expect($group->groupType)->toBe('tags');
    expect($group->allowedParentPaths)->toBe(['//data']);
    expect($group->fetch)->toBe('LAZY');
});
