<?php

declare(strict_types=1);

namespace DOM\ORM\Mapping;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Group
{
    public const ELEMENT_NAME = 'group';
    public ?string $entity;
    public ?string $groupType;
    public ?array $allowedParentPaths;

    public function __construct(string $entity, ?string $groupType = null, ?array $allowedParentPaths = [])
    {
        $this->entity = $entity;
        $this->groupType = $groupType;
        $this->allowedParentPaths = $allowedParentPaths;
    }
}
