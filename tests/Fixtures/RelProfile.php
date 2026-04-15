<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Mapping as ORM;

#[ORM\Item(entityType: 'rel_profile')]
class RelProfile extends AbstractEntity
{
    public function __construct(
        #[ORM\Fragment]
        private string $bio,
        ?string $id = null,
        ?\DateTimeInterface $createdAt = null
    ) {
        parent::__construct($id, $createdAt);
    }

    public function getBio(): string
    {
        return $this->bio;
    }

    public function setBio(string $bio): static
    {
        $this->bio = $bio;

        return $this;
    }
}
