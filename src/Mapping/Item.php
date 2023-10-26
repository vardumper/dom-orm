<?php

declare(strict_types=1);

namespace DOM\ORM\Mapping;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class Item
{
    public const ELEMENT_NAME = 'item';
    public string $entityType;
    public ?array $allowedParentPaths;

    public function __construct(string $entityType, ?array $allowedParentPaths = [])
    {
        $this->entityType = $entityType;
        $this->allowedParentPaths = $allowedParentPaths;
    }
}
