<?php

declare(strict_types=1);

use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Mapping as ORM;

#[ORM\Item(entityType: 'image')]
final class Image extends AbstractEntity
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
        return $this->name . ' (' . $this->mimeType . ', ' . $this->size . ' bytes)';
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

    public function getSize(): int
    {
        return $this->size;
    }
}
