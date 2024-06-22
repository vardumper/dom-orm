<?php
declare(strict_types=1);

namespace DOM\ORM\Repository;

use DOM\ORM\Entity\EntityInterface;
use DOM\ORM\Serializer\Encoder\SchemaEncoder;
use DOM\ORM\Traits\AttributeResolverTrait;
use DOM\ORM\Traits\EntityManagerTrait;
use Ramsey\Collection\Collection;

abstract class AbstractEntityRepository implements EntityRepositoryInterface
{
    use EntityManagerTrait;
    use AttributeResolverTrait;

    protected string $entityType;
    protected string $entityClass;

    public function __construct(string $entityType)
    {
        $this->entityType = $entityType;
        $this->entityClass = $this->getEntityByEntityType($entityType);
        $this->init();
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function findAll(): ?Collection
    {
        $nodes = $this->xpath->query(sprintf('//item[@type="%s"]', $this->entityType));
        if ($nodes->length < 1) {
            return null;
        }

        $array = $this->serializer->decode($nodes, SchemaEncoder::FORMAT);

        return $this->serializer->denormalize($array, $this->entityClass);
    }

    public function find(string $id): ?EntityInterface
    {
        $node = $this->xpath->query(sprintf('//item[@type="%s" and @id="%s"]', $this->entityType, $id));
        if ($node->length > 1) {
            throw new \Exception('Multiple entities found with the same ID.');
        }

        if ($node->length < 1) {
            return null;
        }

        $array = $this->serializer->decode($node, SchemaEncoder::FORMAT);

        return $this->serializer->denormalize($array, $this->entityClass);
    }

    public function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null): ?Collection
    {
        return new Collection($this->entityClass);
    }

    public function findOneBy(array $criteria, array $orderBy = null): ?EntityInterface
    {
        $this->xpath->query('//item[@type="section"]');

        return null;
    }
}
