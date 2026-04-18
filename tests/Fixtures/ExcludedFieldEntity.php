<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Mapping as ORM;

#[ORM\Item(entityType: 'excluded_field_entity')]
class ExcludedFieldEntity extends AbstractEntity
{
    public function __construct(
        #[ORM\Fragment]
        private string $title,
        #[ORM\Fragment]
        #[ORM\Exclude]
        private float $internalScore,
        ?string $id = null,
        ?\DateTimeInterface $createdAt = null,
    ) {
        parent::__construct($id, $createdAt);
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getInternalScore(): float
    {
        return $this->internalScore;
    }
}
