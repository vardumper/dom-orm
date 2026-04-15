<?php

declare(strict_types=1);

namespace DOM\ORM\Entity;

use DOM\ORM\Mapping\Root;
use Ramsey\Collection\Collection;

/**
 * The root document Element
 */
#[Root]
final class Data extends AbstractEntity
{
    /**
     * @var Collection<AbstractEntity>|null
     */
    protected ?Collection $items = null;

    /**
     * @var Collection<AbstractEntity>|null
     */
    protected ?Collection $groups = null;
}
