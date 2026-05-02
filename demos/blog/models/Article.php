<?php

declare(strict_types=1);

use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Mapping as ORM;

#[ORM\Item(entityType: 'article')]
final class Article extends AbstractEntity
{
    public function __construct(
        #[ORM\Fragment]
        private string $title,
        #[ORM\Fragment]
        private string $body,
        #[ORM\Fragment]
        private string $author,
        #[ORM\Fragment]
        private string $subline = '',
        #[ORM\Group(entity: Image::class, groupType: 'images')]
        private array $images = [],
        #[ORM\Group(entity: Comment::class, groupType: 'comments')]
        private array $comments = [],
        ?string $id = null,
        ?\DateTimeInterface $createdAt = null,
    ) {
        parent::__construct($id, $createdAt);
    }

    public function __toString(): string
    {
        return $this->title;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getSubline(): string
    {
        return $this->subline;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getAuthor(): string
    {
        return $this->author;
    }

    public function getImage(): ?Image
    {
        return $this->images[0] ?? null;
    }

    public function getImages(): array
    {
        return $this->images;
    }

    public function setImage(?Image $image): void
    {
        $this->images = $image !== null ? [$image] : [];
    }

    public function getComments(): array
    {
        return $this->comments;
    }

    public function setTitle(string $title): void
    {
        $this->title = $title;
    }

    public function setSubline(string $subline): void
    {
        $this->subline = $subline;
    }

    public function setBody(string $body): void
    {
        $this->body = $body;
    }

    public function setAuthor(string $author): void
    {
        $this->author = $author;
    }

    public function addComment(Comment $comment): void
    {
        $this->comments[] = $comment;
    }
}
