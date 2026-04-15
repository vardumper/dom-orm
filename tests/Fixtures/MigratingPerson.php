<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Mapping as ORM;

/**
 * Entity that simulates a rename ('fullName' → 'name') and a removal ('legacyBio').
 * The current schema only has 'name'; old XML may still contain 'fullName' or 'legacyBio'.
 */
#[ORM\Item(entityType: 'migrating_person')]
#[ORM\FragmentMap([
    'fullName' => 'name',  // renamed
    'legacyBio' => null,    // removed
])]
class MigratingPerson extends AbstractEntity
{
    public function __construct(
        #[ORM\Fragment]
        private string $name,
        ?string $id = null,
        ?\DateTimeInterface $createdAt = null,
    ) {
        parent::__construct($id, $createdAt);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}
