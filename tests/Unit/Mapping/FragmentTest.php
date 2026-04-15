<?php

declare(strict_types=1);

use DOM\ORM\Mapping\Fragment;

it('instantiates with all defaults', function (): void {
    $fragment = new Fragment();
    expect($fragment->fragmentName)->toBeNull();
    expect($fragment->storageStrategy)->toBe('standalone');
    expect($fragment->unique)->toBeFalse();
});

it('accepts a custom fragmentName', function (): void {
    $fragment = new Fragment(fragmentName: 'my_name');
    expect($fragment->fragmentName)->toBe('my_name');
});

it('accepts inline storageStrategy', function (): void {
    $fragment = new Fragment(storageStrategy: 'inline');
    expect($fragment->storageStrategy)->toBe('inline');
});

it('accepts unique flag set to true', function (): void {
    $fragment = new Fragment(unique: true);
    expect($fragment->unique)->toBeTrue();
});
