<?php

declare(strict_types=1);

namespace DOM\ORM\Mapping;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class Item
{
    public const ELEMENT_NAME = 'item';

    public function __construct(
        public string $entityType,
        public ?array $allowedParentPaths = []
    ) {
    }
}
