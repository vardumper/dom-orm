<?php

declare(strict_types=1);

namespace DOM\ORM\Entity;

interface EntityInterface
{
    public function getId(): string;

    public function getDeletedAt(): ?\DateTimeInterface;

    public function setDeletedAt(\DateTimeInterface $deletedAt): static;

    public function getCreatedAt(): \DateTimeInterface;

    public function getUpdatedAt(): ?\DateTimeInterface;

    public function setUpdatedAt(\DateTimeInterface $updatedAt): static;
}
