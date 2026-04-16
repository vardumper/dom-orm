<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Mapping as ORM;

#[ORM\Item(entityType: 'rel_user_single')]
class RelUserSingle extends AbstractEntity
{
    public function __construct(
        #[ORM\Fragment]
        private string $username,
        #[ORM\Group(entity: RelProfile::class, groupType: 'profile')]
        private ?RelProfile $profile = null,
        ?string $id = null,
        ?\DateTimeInterface $createdAt = null
    ) {
        parent::__construct($id, $createdAt);
    }

    public function getUsername(): string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getProfile(): ?RelProfile
    {
        return $this->profile;
    }

    public function setProfile(?RelProfile $profile): static
    {
        $this->profile = $profile;

        return $this;
    }
}
