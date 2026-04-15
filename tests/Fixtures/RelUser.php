<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Mapping as ORM;

#[ORM\Item(entityType: 'rel_user')]
class RelUser extends AbstractEntity
{
    /**
     * @param list<RelProfile>|array<int, array<string, mixed>> $profile
     */
    public function __construct(
        #[ORM\Fragment]
        private string $username,
        #[ORM\Group(entity: RelProfile::class, groupType: 'profile')]
        private array $profile = [],
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

    /**
     * @return list<RelProfile>|array<int, array<string, mixed>>
     */
    public function getProfile(): array
    {
        return $this->profile;
    }

    /**
     * @param list<RelProfile>|array<int, array<string, mixed>> $profile
     */
    public function setProfile(array $profile): static
    {
        $this->profile = $profile;

        return $this;
    }
}
