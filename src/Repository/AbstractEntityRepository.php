<?php
declare(strict_types=1);

namespace DOM\ORM\Repository;

use DOM\ORM\Entity\EntityInterface;
use DOM\ORM\Serializer\Encoder\SchemaEncoder;
use DOM\ORM\Traits\XmlStorageManagerTrait;
use Ramsey\Collection\Collection;

abstract class AbstractEntityRepository implements EntityRepositoryInterface
{
    use XmlStorageManagerTrait;
    protected string $entityType;

    public function __construct(string $entityType)
    {
        $this->entityType = $entityType;
        $this->loadData(getcwd() . '/storage/data.xml');
    }

    public function getEntityType(): string
    {
        return $this->entityType;
    }

    public function findAll(): ?Collection
    {
        return null;
    }

    public function find(string $id): ?EntityInterface
    {
        $node = $this->xpath->query(sprintf('//item[@type="%s" and @id="%s"]', $this->entityType, $id));
        if ($node->length === 1) {
            $array = $this->serializer->decode($node, SchemaEncoder::FORMAT);

            return $this->serializer->denormalize($array);
        }

        return null;
    }

    public function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null): ?Collection
    {
        return new Collection($this->entityType);
    }



    public function findOneBy(array $criteria, array $orderBy = null): ?EntityInterface
    {
        $this->xpath->query('//item[@type="section"]');

        return null;
    }
}