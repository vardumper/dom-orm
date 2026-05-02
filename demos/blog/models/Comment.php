<?php

declare(strict_types=1);

use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Mapping as ORM;

#[ORM\Item(entityType: 'comment')]
final class Comment extends AbstractEntity
{
    public function __construct(
        #[ORM\Fragment]
        private string $author,
        #[ORM\Fragment]
        private string $body,
        ?string $id = null,
        ?\DateTimeInterface $createdAt = null,
    ) {
        parent::__construct($id, $createdAt);
    }

    public function __toString(): string
    {
        return $this->author . ': ' . $this->body;
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function setAuthor(string $author): void
    {
        $this->author = $author;
    }

    public function setBody(string $body): void
    {
        $this->body = $body;
    }
}
