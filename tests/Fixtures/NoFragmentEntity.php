<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Mapping as ORM;

/**
 * Entity with no #[Fragment] properties, exercising the denormalizer's
 * resolveFragmentDataTypes() null-fragments branch.
 */
#[ORM\Item(entityType: 'no_fragment_entity')]
class NoFragmentEntity extends AbstractEntity
{
    public function __construct(
        ?string $id = null,
        ?\DateTimeInterface $createdAt = null
    ) {
        parent::__construct($id, $createdAt);
    }
}
