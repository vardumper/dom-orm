<?php

declare(strict_types=1);

namespace DOM\ORM\Repository;

use DOM\ORM\Entity\EntityInterface;
use Ramsey\Collection\Collection;

interface EntityRepositoryInterface
{
    public function find(string $id): ?EntityInterface;

    /**
     * @return Collection<EntityInterface>|null
     */
    public function findAll(): ?Collection;

    /**
     * @param array<string, scalar> $criteria
     * @param array<string, 'ASC'|'DESC'>|null $orderBy
     */
    public function findOneBy(array $criteria, ?array $orderBy = null): ?EntityInterface;

    /**
     * @param array<string, scalar> $criteria
     * @param array<string, 'ASC'|'DESC'>|null $orderBy
     * @return Collection<EntityInterface>|null
     */
    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): ?Collection;

    public function remove(string $id): void;
}
