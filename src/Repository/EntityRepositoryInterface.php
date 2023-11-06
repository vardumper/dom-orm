<?php

declare(strict_types=1);

namespace DOM\ORM\Repository;

use DOM\ORM\Entity\EntityInterface;
use Ramsey\Collection\Collection;

interface EntityRepositoryInterface
{
    public function find(string $id): ?EntityInterface;

    public function findAll(): ?Collection;

    public function findOneBy(array $criteria): ?EntityInterface;

    public function findBy(array $criteria): ?Collection;
}
