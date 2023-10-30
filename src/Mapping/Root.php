<?php

declare(strict_types=1);

namespace DOM\ORM\Mapping;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class Root
{
    public const ELEMENT_NAME = 'data';
    public string $allowedParentPath = '/';
}
