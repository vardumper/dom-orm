<?php

declare(strict_types=1);

namespace DOM\ORM\Repository;

use DOM\ORM\Entity\EntityInterface;
use Ramsey\Collection\Collection;

interface EntityRepositoryInterface
{
    /**
     * @todo should be resolved via PHP8 Attributes instead of a class property (which cannot easily be enforced with neither abstraction nor interface)
     * @tutorial that said, we do want the possibility to validate the XPath of an Entity before saving it.
     * for example: a folder should be saved under /data/group[@type="folders"] and nowhere else
     * should be resolved via PHP8 Attributes instead of a class property
     */
    // public function hasAllowedParentPaths(): bool;

    public function find($id, $lockMode = null, $lockVersion = null): ?EntityInterface;

    public function findOneBy(array $criteria): ?EntityInterface;

    public function findBy(array $criteria): ?Collection;
}
