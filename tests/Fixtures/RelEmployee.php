<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Mapping as ORM;

#[ORM\Item(entityType: 'rel_employee')]
class RelEmployee extends AbstractEntity
{
    public function __construct(
        #[ORM\Fragment]
        private string $name,
        #[ORM\Fragment]
        private string $companyId,
        ?string $id = null,
        ?\DateTimeInterface $createdAt = null
    ) {
        parent::__construct($id, $createdAt);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getCompanyId(): string
    {
        return $this->companyId;
    }

    public function setCompanyId(string $companyId): static
    {
        $this->companyId = $companyId;

        return $this;
    }
}
