<?php

declare(strict_types=1);

namespace DOM\ORM\Mapping;

#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class UniqueConstraint
{
    /**
     * @var array<string>|null
     * @readonly
     */
    public $fragments;

    /**
     * @param array<string>|null       $fragments
     */
    public function __construct(
        ?array $fragments = null
    ) {
        $this->fragments = $fragments;
    }
}
