<?php

declare(strict_types=1);

namespace DOM\ORM\Mapping;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class UniqueConstraint
{
    public function __construct(
        public readonly ?array $fragments = null
    ) {
    }
}
