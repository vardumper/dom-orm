<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Mapping as ORM;

#[ORM\Item(entityType: 'scoped_comment', allowedParentPaths: [
    '//group[@type="articles"]/item[@type="scoped_article"]',
    '//group[@type="posts"]/item[@type="scoped_post"]',
])]
class ScopedComment extends AbstractEntity
{
    public function __construct(
        #[ORM\Fragment]
        private string $body,
        ?string $id = null,
        ?\DateTimeInterface $createdAt = null,
    ) {
        parent::__construct($id, $createdAt);
    }

    public function getBody(): string
    {
        return $this->body;
    }
}
