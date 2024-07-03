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
        $additionalArgs = ' and position() = 1'; // we want only one
        if (isset($criteria['id'])) {
            $additionalArgs .= sprintf(' and @id="%s" ', $criteria['id']);
            unset($criteria['id']);
        }

        foreach ($criteria as $key => $value) {
            // ./book[./author/name = 'John']
            $additionalArgs .= sprintf('and ./fragment[@name="%s"] = "%s"', $key, $value);
        }

        $query = sprintf('//item[@type="%s" %s]', $this->entityType, $additionalArgs);
        $node = $this->xpath->query($query);

        if ($node->length > 1) {
            throw new \Exception('Multiple entities found with the same ID.');
        }

        if ($node->length < 1) {
            return null;
        }

        $array = $this->serializer->decode($node, SchemaEncoder::FORMAT);

        return $this->serializer->denormalize($array, $this->entityClass);
    }

    public function remove(string $id): void
    {
        // we assume that IDs are unique and that the entity having that unique ID is of the type we want to remove
        // @todo add tests for this
        $this->removeById($id);
    }
}
