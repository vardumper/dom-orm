<?php

declare(strict_types=1);

namespace DOM\ORM\Mapping;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Group
{
    public const ELEMENT_NAME = 'group';
    public const FETCH_EAGER = 'EAGER';
    public const FETCH_LAZY = 'LAZY';
    public ?string $entity;
    public ?string $groupType;
    public ?array $allowedParentPaths;
    public ?string $fetch; // does this make sense in the DOM context? Everything is there.

    public function __construct(string $entity, ?string $groupType = null, ?array $allowedParentPaths = [], ?string $fetch = self::FETCH_EAGER)
    {
        $this->entity = $entity;
        $this->groupType = $groupType;
        $this->allowedParentPaths = $allowedParentPaths;
        $this->fetch = $fetch;
    }
}
