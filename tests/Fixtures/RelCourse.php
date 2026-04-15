<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Mapping as ORM;

#[ORM\Item(entityType: 'rel_course')]
class RelCourse extends AbstractEntity
{
    public function __construct(
        #[ORM\Fragment]
        private string $title,
        ?string $id = null,
        ?\DateTimeInterface $createdAt = null
    ) {
        parent::__construct($id, $createdAt);
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }
}
