<?php

declare(strict_types=1);

namespace DOM\ORM\Mapping;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final class Fragment
{
    public const ELEMENT_NAME = 'fragment';
    public const STORAGE_STRATEGY_INLINE = 'inline';
    public const STORAGE_STRATEGY_STANDALONE = 'standalone';

    public ?string $fragmentName = null;

    public ?string $storageStrategy = self::STORAGE_STRATEGY_STANDALONE;

    public function __construct(?string $fragmentName = null, ?string $storageStrategy = self::STORAGE_STRATEGY_STANDALONE)
    {
        $this->fragmentName = $fragmentName;
        $this->storageStrategy = $storageStrategy;
    }
}
