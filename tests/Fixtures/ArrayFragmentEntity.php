<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Mapping as ORM;

#[ORM\Item(entityType: 'array_fragment_entity')]
class ArrayFragmentEntity extends AbstractEntity
{
    /**
     * @param array<int, string> $tags
     */
    public function __construct(
        #[ORM\Fragment]
        private array $tags,
        ?string $id = null,
        ?\DateTimeInterface $createdAt = null,
    ) {
        parent::__construct($id, $createdAt);
    }

    /**
     * @return array<int, string>
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    /**
     * @param array<int, string> $tags
     */
    public function setTags(array $tags): void
    {
        $this->tags = $tags;
    }
}
