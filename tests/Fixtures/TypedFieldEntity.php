<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Mapping as ORM;

/**
 * Fixture entity with int, float, bool, and string Fragment fields.
 * Used to verify that non-string scalar types survive the full
 * normalize → encode → decode → denormalize round-trip.
 */
#[ORM\Item(entityType: 'typed_entity')]
class TypedFieldEntity extends AbstractEntity
{
    public function __construct(
        #[ORM\Fragment]
        private string $label,
        #[ORM\Fragment]
        private int $count,
        #[ORM\Fragment]
        private float $ratio,
        #[ORM\Fragment]
        private bool $active,
        ?string $id = null,
        ?\DateTimeInterface $createdAt = null,
    ) {
        parent::__construct($id, $createdAt);
    }

    public function getLabel(): string
    {
        return $this->label;
    }
    public function getCount(): int
    {
        return $this->count;
    }
    public function getRatio(): float
    {
        return $this->ratio;
    }
    public function getActive(): bool
    {
        return $this->active;
    }

    public function setLabel(string $label): void
    {
        $this->label = $label;
    }
    public function setCount(int $count): void
    {
        $this->count = $count;
    }
    public function setRatio(float $ratio): void
    {
        $this->ratio = $ratio;
    }
    public function setActive(bool $active): void
    {
        $this->active = $active;
    }
}
