<?php

declare(strict_types=1);

namespace DOM\ORM\Mapping;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Group
{
    private const ELEMENT_NAME = 'group';
    private const FETCH_EAGER = 'EAGER';
    private const FETCH_LAZY = 'LAZY';

    public function __construct(
        public string $entity,
        public ?string $groupType = null,
        public ?array $allowedParentPaths = [],
        public ?string $fetch = self::FETCH_EAGER
    ) {
    }
}
