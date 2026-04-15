<?php

declare(strict_types=1);

namespace DOM\ORM\Mapping;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class Item
{
    /**
     * @param list<string>|null $allowedParentPaths
     */
    public function __construct(
        public string $entityType,
        public ?array $allowedParentPaths = null
    ) {
    }
}
