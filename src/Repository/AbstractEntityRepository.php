<?php
declare(strict_types=1);

namespace DOM\ORM\Repository;

use DOM\ORM\{Entity\EntityInterface, Serializer\Encoder\SchemaEncoder, Traits\EntityManagerTrait};
use Ramsey\Collection\Collection;

abstract class AbstractEntityRepository implements EntityRepositoryInterface
{
    use EntityManagerTrait;

    private string $entityType;
    private string $entityClass;

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

    /**
     * @return Collection<EntityInterface>|null
     */
    public function findAll(): ?Collection
    {
        $nodes = $this->xpath->query(\sprintf('//item[@type="%s"]', $this->entityType));
        if ($nodes->length < 1) {
            return null;
        }

        $array = $this->serializer->decode($nodes, SchemaEncoder::FORMAT);

        return $this->serializer->denormalize($array, $this->entityClass);
    }

    public function find(string $id): ?EntityInterface
    {
        $node = $this->xpath->query(\sprintf('//item[@type="%s" and @id="%s"]', $this->entityType, $id));
        if ($node->length > 1) {
            throw new \Exception('Multiple entities found with the same ID.');
        }

        if ($node->length < 1) {
            return null;
        }

        $array = $this->serializer->decode($node, SchemaEncoder::FORMAT);

        return $this->serializer->denormalize($array, $this->entityClass);
    }

    /**
     * @param array<string, scalar> $criteria
     * @param array<string, 'ASC'|'DESC'>|null $orderBy
     * @return Collection<EntityInterface>|null
     */
    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): ?Collection
    {
        return new Collection($this->entityClass);
    }

    /**
     * @param array<string, scalar> $criteria
     * @param array<string, 'ASC'|'DESC'>|null $orderBy
     */
    public function findOneBy(array $criteria, ?array $orderBy = null): ?EntityInterface
    {
        $additionalArgs = '';
        if (isset($criteria['id'])) {
            $additionalArgs .= \sprintf(' and @id="%s" ', $criteria['id']);
            unset($criteria['id']);
        }

        foreach ($criteria as $key => $value) {
            $additionalArgs .= \sprintf(' and ./fragment[@name="%s"] = "%s"', \trim($key), \trim((string)$value));
        }

        $query = \sprintf('//item[@type="%s" %s]', $this->entityType, $additionalArgs);
        $node = $this->xpath->query($query)->item(0);
        if ($node === null) {
            return null;
        }

        $array = $this->serializer->decode($node, SchemaEncoder::FORMAT);

        return $this->serializer->denormalize($array, $this->entityClass);
    }

    public function remove(string $id): void
    {
        $this->removeById($id);
    }
}
