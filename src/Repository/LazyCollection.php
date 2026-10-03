<?php

declare(strict_types=1);

namespace DOM\ORM\Repository;

use DOM\ORM\Entity\EntityInterface;
use DOM\ORM\Storage\ChunkStore;
use Ramsey\Collection\Collection;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

/**
 * A lazily-hydrated result set (Phase 3).
 *
 * Store-backed collections resolve index -> id via the small ids.php list
 * (no payload load). Entities are hydrated on first access and cached, so a
 * collection over 50 K records costs only the id list (~1.6 MB) plus whatever
 * entities the caller actually touches — not the full payload.
 *
 * count() is O(1). Iteration and map()/filter() hydrate on demand.
 *
 * @implements \IteratorAggregate<int, EntityInterface>
 */
final class LazyCollection implements \Countable, \IteratorAggregate
{
    /**
     * @var list<string>
     */
    private array $ids;

    /**
     * @var array<int, EntityInterface>
     */
    private array $hydrated;

    /**
     * @param list<string> $ids
     * @param array<int, EntityInterface> $hydrated
     */
    private function __construct(
        private readonly ?ChunkStore $store,
        private readonly string $entityType,
        private readonly string $entityClass,
        private readonly ?DenormalizerInterface $denormalizer,
        array $ids,
        array $hydrated = [],
    ) {
        $this->ids = $ids;
        $this->hydrated = $hydrated;
    }

    /**
     * Build a store-backed lazy collection (hydrates on first access).
     *
     * @param list<string>|null $ids ordered id list; null loads it from the store
     */
    public static function fromStore(
        ChunkStore $store,
        string $entityType,
        string $entityClass,
        DenormalizerInterface $denormalizer,
        ?array $ids = null,
    ): self {
        return new self($store, $entityType, $entityClass, $denormalizer, $ids ?? $store->ids($entityType));
    }

    /**
     * Wrap already-materialized entities (fallback when no chunk cache exists).
     *
     * @param list<EntityInterface> $entities
     */
    public static function fromEntities(array $entities, string $entityClass): self
    {
        $ids = [];
        $hydrated = [];
        foreach ($entities as $i => $entity) {
            $ids[] = (string)$entity->getId();
            $hydrated[$i] = $entity;
        }

        return new self(null, '', $entityClass, null, $ids, $hydrated);
    }

    public function count(): int
    {
        return \count($this->ids);
    }

    public function get(int $index): ?EntityInterface
    {
        if (!\array_key_exists($index, $this->ids)) {
            return null;
        }

        if (!isset($this->hydrated[$index])) {
            if ($this->store === null || $this->denormalizer === null) {
                return null;
            }
            $id = $this->ids[$index];
            $itemData = $this->store->findById($this->entityType, $id);
            if ($itemData === null) {
                return null;
            }
            $this->hydrated[$index] = $this->hydrate($id, $itemData);
        }

        return $this->hydrated[$index];
    }

    public function first(): ?EntityInterface
    {
        return $this->get(0);
    }

    public function last(): ?EntityInterface
    {
        return $this->get(\count($this->ids) - 1);
    }

    /**
     * @return list<EntityInterface>
     */
    public function all(): array
    {
        $result = [];
        foreach ($this->ids as $i => $id) {
            $entity = $this->get($i);
            if ($entity !== null) {
                $result[] = $entity;
            }
        }

        return $result;
    }

    /**
     * @return \ArrayIterator<int, EntityInterface>
     */
    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->all());
    }

    /**
     * @template T
     * @param callable(EntityInterface): T $callback
     * @return list<T>
     */
    public function map(callable $callback): array
    {
        $result = [];
        foreach ($this->ids as $i => $id) {
            $entity = $this->get($i);
            if ($entity !== null) {
                $result[] = $callback($entity);
            }
        }

        return $result;
    }

    /**
     * @param callable(EntityInterface): bool $callback
     */
    public function filter(callable $callback): self
    {
        $filteredIds = [];
        foreach ($this->ids as $i => $id) {
            $entity = $this->get($i);
            if ($entity !== null && $callback($entity)) {
                $filteredIds[] = $id;
            }
        }

        return new self($this->store, $this->entityType, $this->entityClass, $this->denormalizer, $filteredIds);
    }

    /**
     * @param array<string, mixed> $itemData
     */
    private function hydrate(string $id, array $itemData): EntityInterface
    {
        $collection = $this->denormalizer->denormalize(
            [
                'data' => [[
                    'item-' . $id => $itemData,
                ]],
            ],
            $this->entityClass,
        );
        /** @var EntityInterface $entity */
        $entity = $collection instanceof Collection ? $collection->first() : $collection;

        return $entity;
    }
}
