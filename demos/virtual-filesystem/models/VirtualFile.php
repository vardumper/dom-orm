<?php

declare(strict_types=1);

use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Mapping as ORM;

#[ORM\Item(entityType: 'file')]
final class VirtualFile extends AbstractEntity
{
    public function __construct(
        #[ORM\Fragment]
        private string $name,
        #[ORM\Fragment]
        private string $mimeType,
        #[ORM\Fragment]
        private string $content,
        #[ORM\Fragment]
        private int $size = 0,
        ?string $id = null,
        ?\DateTimeInterface $createdAt = null,
    ) {
        parent::__construct($id, $createdAt);
    }

    public function __toString(): string
    {
        return $this->name . ' (' . $this->mimeType . ', ' . $this->getSize() . ' bytes)';
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getMimeType(): string
    {
        return $this->mimeType;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setMimeType(string $mimeType): void
    {
        $this->mimeType = $mimeType;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function getSize(): int
    {
        return $this->size;
    }

    public function setSize(int $size): void
    {
        $this->size = $size;
    }
}
