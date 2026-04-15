<?php
declare(strict_types=1);

namespace DOM\ORM\Repository;

use DOM\ORM\Encryption\EncryptionService;
use DOM\ORM\{Entity\EntityInterface, Serializer\Encoder\SchemaEncoder, Traits\EntityManagerTrait};
use DOM\ORM\Storage\QueryCache;
use Ramsey\Collection\Collection;

abstract class AbstractEntityRepository implements EntityRepositoryInterface
{
    use EntityManagerTrait;

    private string $entityType;
    private string $entityTypeXPathLiteral;
    private string $entityClass;
    private ?EncryptionService $encryption = null;

    /**
     * @var array<string, array<string, array<string, mixed>>>|null
     */
    private ?array $queryCache = null;
    private bool $queryCacheLoaded = false;

    public function __construct(string $entityType)
    {
        $this->entityType = $entityType;
        $this->entityTypeXPathLiteral = $this->toXPathValue($entityType);
        $this->entityClass = $this->getEntityByEntityType($entityType);
        $this->init();
        $this->encryption = $this->getEncryptionService();
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
        $cache = $this->getQueryCache();
        if ($cache !== null) {
            $array = QueryCache::findAll($cache, $this->entityType);
            if ($array !== null) {
                return $this->serializer->denormalize($array, $this->entityClass);
            }

            return null;
        }

        $nodes = $this->queryNodes(\sprintf('//item[@type=%s]', $this->entityTypeXPathLiteral));
        if ($nodes === null) {
            return null;
        }

        if ($nodes->length < 1) {
            return null;
        }

        $array = $this->serializer->decode($nodes, SchemaEncoder::FORMAT);

        return $this->serializer->denormalize($array, $this->entityClass);
    }

    public function find(string $id): ?EntityInterface
    {
        $cache = $this->getQueryCache();
        if ($cache !== null) {
            $array = QueryCache::findById($cache, $this->entityType, $id);
            if ($array === null) {
                return null;
            }
            /** @var Collection<EntityInterface>|null $collection */
            $collection = $this->serializer->denormalize($array, $this->entityClass);

            return ($collection === null || $collection->count() < 1) ? null : $collection->first();
        }

        $nodes = $this->queryNodes(\sprintf(
            '//item[@type=%s and @id=%s]',
            $this->entityTypeXPathLiteral,
            $this->toXPathValue($id)
        ));
        if ($nodes === null) {
            return null;
        }

        if ($nodes->length > 1) {
            throw new \Exception('Multiple entities found with the same ID.');
        }

        if ($nodes->length < 1) {
            return null;
        }

        $array = $this->serializer->decode($nodes, SchemaEncoder::FORMAT);
        /** @var Collection<EntityInterface>|null $collection */
        $collection = $this->serializer->denormalize($array, $this->entityClass);

        if ($collection === null || $collection->count() < 1) {
            return null;
        }

        /** @var EntityInterface $entity */
        $entity = $collection->first();

        return $entity;
    }

    /**
     * @param array<string, scalar> $criteria
     * @param array<string, 'ASC'|'DESC'>|null $orderBy
     * @return Collection<EntityInterface>|null
     */
    public function findBy(array $criteria, ?array $orderBy = null, ?int $limit = null, ?int $offset = null): ?Collection
    {
        if (empty($criteria)) {
            return $this->findAll();
        }

        // Check query cache before paying for XPath predicate construction.
        $cache = $this->getQueryCache();
        if ($cache !== null) {
            // QueryCache::findBy returns null when it detects encrypted criteria — fall through to XPath.
            $array = QueryCache::findBy($cache, $this->entityType, $criteria);
            if ($array !== null) {
                return $this->serializer->denormalize($array, $this->entityClass);
            }
        }

        $predicates = [\sprintf('@type=%s', $this->entityTypeXPathLiteral)];
        if (isset($criteria['id'])) {
            $predicates[] = \sprintf('@id=%s', $this->toXPathValue((string)$criteria['id']));
            unset($criteria['id']);
        }

        foreach ($criteria as $key => $value) {
            $predicates[] = $this->buildFragmentPredicate($key, (string)$value);
        }

        $query = '//item[' . \implode(' and ', $predicates) . ']';
        $nodes = $this->queryNodes($query);
        if ($nodes === null || $nodes->length < 1) {
            return null;
        }

        $array = $this->serializer->decode($nodes, SchemaEncoder::FORMAT);

        return $this->serializer->denormalize($array, $this->entityClass);
    }

    /**
     * @param array<string, scalar> $criteria
     * @param array<string, 'ASC'|'DESC'>|null $orderBy
     */
    public function findOneBy(array $criteria, ?array $orderBy = null): ?EntityInterface
    {
        if (isset($criteria['id']) && \count($criteria) === 1) {
            return $this->find((string)$criteria['id']);
        }

        // Check query cache before XPath predicate construction.
        $cache = $this->getQueryCache();
        if ($cache !== null) {
            $array = QueryCache::findBy($cache, $this->entityType, $criteria);
            if ($array !== null) {
                /** @var Collection<EntityInterface>|null $collection */
                $collection = $this->serializer->denormalize($array, $this->entityClass);

                return ($collection === null || $collection->count() < 1) ? null : $collection->first();
            }
        }

        $predicates = [\sprintf('@type=%s', $this->entityTypeXPathLiteral)];
        if (isset($criteria['id'])) {
            $predicates[] = \sprintf('@id=%s', $this->toXPathValue((string)$criteria['id']));
            unset($criteria['id']);
        }

        foreach ($criteria as $key => $value) {
            $predicates[] = $this->buildFragmentPredicate($key, (string)$value);
        }

        $query = '//item[' . \implode(' and ', $predicates) . ']';
        $nodes = $this->queryNodes($query);
        if ($nodes === null) {
            return null;
        }

        $node = $nodes->item(0);
        if ($node === null) {
            return null;
        }

        $array = $this->serializer->decode($node, SchemaEncoder::FORMAT);
        if ($array === null) {
            return null;
        }

        /** @var Collection<EntityInterface>|null $collection */
        $collection = $this->serializer->denormalize([
            'data' => [$array],
        ], $this->entityClass);
        if ($collection === null || $collection->count() < 1) {
            return null;
        }

        /** @var EntityInterface $entity */
        $entity = $collection->first();

        return $entity;
    }

    public function remove(string $id): void
    {
        $this->removeById($id);
    }

    /**
     * Returns the loaded query cache array, or null if cache is not enabled/available.
     * The result is memoised for the lifetime of this repository instance.
     *
     * @return array<string, array<string, array<string, mixed>>>|null
     */
    private function getQueryCache(): ?array
    {
        if ($this->queryCacheLoaded) {
            return $this->queryCache;
        }
        $this->queryCacheLoaded = true;
        $this->queryCache = (QueryCache::isEnabled() && QueryCache::exists()) ? QueryCache::load() : null;

        return $this->queryCache;
    }

    /**
     * @return \DOMNodeList<\DOMNode>|null
     */
    private function queryNodes(string $query): ?\DOMNodeList
    {
        $nodes = $this->xpath->query($query);

        return ($nodes === false) ? null : $nodes;
    }

    private function xpathLiteral(string $value): string
    {
        if (!\str_contains($value, "'")) {
            return "'{$value}'";
        }

        if (!\str_contains($value, '"')) {
            return '"' . $value . '"';
        }

        $parts = \explode("'", $value);
        $quotedParts = \array_map(static fn (string $part): string => "'{$part}'", $parts);

        return 'concat(' . \implode(', "\'", ', $quotedParts) . ')';
    }

    private function toXPathValue(string $value): string
    {
        if (!\str_contains($value, "'") && !\str_contains($value, '"')) {
            return '"' . $value . '"';
        }

        return $this->xpathLiteral($value);
    }

    /**
     * Builds an XPath predicate for a fragment field.
     * When the field is sensitive, matches on the HMAC searchable-hash attribute
     * instead of the (encrypted) text content.
     */
    private function buildFragmentPredicate(string $fieldName, string $plainValue): string
    {
        $nameExpr = $this->toXPathValue($fieldName);

        if (
            $this->encryption !== null
            && \in_array($fieldName, $this->resolveSensitiveProperties($this->entityClass), true)
        ) {
            $hash = $this->encryption->searchHash($plainValue);

            return \sprintf(
                './fragment[@name=%s and @searchable-hash=%s]',
                $nameExpr,
                $this->toXPathValue($hash),
            );
        }

        return \sprintf(
            './fragment[@name=%s]=%s',
            $nameExpr,
            $this->toXPathValue($plainValue),
        );
    }
}
