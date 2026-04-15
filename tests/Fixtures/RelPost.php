<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Mapping as ORM;

#[ORM\Item(entityType: 'rel_post')]
class RelPost extends AbstractEntity
{
    /**
     * @param list<RelComment>|array<int, array<string, mixed>> $comments
     */
    public function __construct(
        #[ORM\Fragment]
        private string $title,
        #[ORM\Group(entity: RelComment::class, groupType: 'comments')]
        private array $comments = [],
        ?string $id = null,
        ?\DateTimeInterface $createdAt = null
    ) {
        parent::__construct($id, $createdAt);
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return list<RelComment>|array<int, array<string, mixed>>
     */
    public function getComments(): array
    {
        return $this->comments;
    }

    /**
     * @param list<RelComment>|array<int, array<string, mixed>> $comments
     */
    public function setComments(array $comments): static
    {
        $this->comments = $comments;

        return $this;
    }
}
