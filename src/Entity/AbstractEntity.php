<?php

declare(strict_types=1);

namespace DOM\ORM\Entity;

use DOM\ORM\Mapping\Fragment;
use Ramsey\Uuid\Uuid;

abstract class AbstractEntity implements EntityInterface
{
    #[Fragment(storageStrategy: 'inline')]
    private string $id;

    #[Fragment]
    private \DateTimeInterface $createdAt;

    private array $allowedParentPaths;

    #[Fragment]
    private ?\DateTimeInterface $updatedAt = null;

    #[Fragment]
    private ?\DateTimeInterface $deletedAt = null;

    public function __construct(?string $id = null, ?\DateTimeInterface $createdAt = null)
    {
        $this->id = $id ?? Uuid::uuid4()->getHex()->toString();
        $this->createdAt = $createdAt ?? new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): void
    {
        $this->id = $id;
    }

    public function getDeletedAt(): ?\DateTimeInterface
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(\DateTimeInterface $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function hasAllowedParentPaths(): bool
    {
        return $this->allowedParentPaths !== null;
    }
}
