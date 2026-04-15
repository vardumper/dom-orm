<?php

declare(strict_types=1);

namespace DOM\ORM\Mapping;

#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class Group
{
    private const FETCH_EAGER = 'EAGER';

    public function __construct(
        public string $entity,
        public ?string $groupType = null,
        public ?array $allowedParentPaths = null,
        public ?string $fetch = self::FETCH_EAGER
    ) {
    }
}
