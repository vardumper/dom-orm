<?php

declare(strict_types=1);

namespace DOM\ORM\Mapping;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Group
{
    public const ELEMENT_NAME = 'group';
    public const FETCH_EAGER = 'EAGER';
    public const FETCH_LAZY = 'LAZY';

    public function __construct(
        public string $entity,
        public ?string $groupType = null,
        public ?array $allowedParentPaths = [],
        public ?string $fetch = self::FETCH_EAGER
    ) {
    }
}
