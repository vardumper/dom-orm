<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Mapping as ORM;

#[ORM\Item(entityType: 'nullable_typed_entity')]
class NullableTypedFieldEntity extends AbstractEntity
{
    public function __construct(
        #[ORM\Fragment]
        private ?string $label = null,
        #[ORM\Fragment]
        private ?int $count = null,
        #[ORM\Fragment]
        private ?float $ratio = null,
        #[ORM\Fragment]
        private ?bool $active = null,
        ?string $id = null,
        ?\DateTimeInterface $createdAt = null,
    ) {
        parent::__construct($id, $createdAt);
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    public function getCount(): ?int
    {
        return $this->count;
    }

    public function getRatio(): ?float
    {
        return $this->ratio;
    }

    public function getActive(): ?bool
    {
        return $this->active;
    }

    public function setLabel(?string $label): void
    {
        $this->label = $label;
    }

    public function setCount(?int $count): void
    {
        $this->count = $count;
    }

    public function setRatio(?float $ratio): void
    {
        $this->ratio = $ratio;
    }

    public function setActive(?bool $active): void
    {
        $this->active = $active;
    }
}
