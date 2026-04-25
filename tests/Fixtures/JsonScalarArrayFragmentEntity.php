<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Mapping as ORM;

#[ORM\Item(entityType: 'json_scalar_array_fragment_entity')]
class JsonScalarArrayFragmentEntity extends AbstractEntity
{
    /**
     * @param array<int|string, scalar|array<mixed>|null> $payload
     */
    public function __construct(
        #[ORM\Fragment(dataType: ORM\Fragment::DATA_TYPE_JSON_SCALAR)]
        private array $payload,
        ?string $id = null,
        ?\DateTimeInterface $createdAt = null,
    ) {
        parent::__construct($id, $createdAt);
    }

    /**
     * @return array<int|string, scalar|array<mixed>|null>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @param array<int|string, scalar|array<mixed>|null> $payload
     */
    public function setPayload(array $payload): void
    {
        $this->payload = $payload;
    }
}
