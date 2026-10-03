<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Mapping as ORM;

/**
 * Entity whose fragments are hydrated through setters (not the constructor),
 * exercising the reflection denormalizer's setter-based hydration path,
 * scalar casts, sensitive decryption, JSON-scalar decoding, and the orphaned
 * fragment guard.
 */
#[ORM\Item(entityType: 'setter_hydrated_entity')]
class SetterHydratedEntity extends AbstractEntity
{
    #[ORM\Fragment]
    private string $label = '';

    #[ORM\Fragment]
    #[ORM\Sensitive]
    private string $secret = '';

    /**
     * @var array<int|string, scalar|array<mixed>|null>
     */
    #[ORM\Fragment(dataType: ORM\Fragment::DATA_TYPE_JSON_SCALAR)]
    private array $payload = [];

    #[ORM\Fragment]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Fragment]
    private int $count = 0;

    #[ORM\Fragment]
    private float $score = 0.0;

    #[ORM\Fragment]
    private bool $active = false;

    public function __construct(
        #[ORM\Fragment]
        private string $name,
        ?string $id = null,
        ?\DateTimeInterface $createdAt = null
    ) {
        parent::__construct($id, $createdAt);
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function setLabel(string $label): static
    {
        $this->label = $label;

        return $this;
    }

    public function getSecret(): string
    {
        return $this->secret;
    }

    public function setSecret(string $secret): static
    {
        $this->secret = $secret;

        return $this;
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
    public function setPayload(array $payload): static
    {
        $this->payload = $payload;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getCount(): int
    {
        return $this->count;
    }

    public function setCount(int $count): static
    {
        $this->count = $count;

        return $this;
    }

    public function getScore(): float
    {
        return $this->score;
    }

    public function setScore(float $score): static
    {
        $this->score = $score;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }
}
