<?php

declare(strict_types=1);

namespace DOM\ORM\Traits;

use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Entity\EntityInterface;
use DOM\ORM\Mapping\Fragment;
use DOM\ORM\Mapping\Group;
use DOM\ORM\Mapping\Item;
use DOM\ORM\Mapping\Sensitive;

trait AttributeResolverTrait
{
    /**
     * @var array<string, class-string<AbstractEntity>>|null
     */
    private static ?array $entityTypeToClassMap = null;

    /**
     * @var array<class-string<AbstractEntity>, string|null>
     */
    private static array $entityTypeByClass = [];

    /**
     * @var array<class-string<AbstractEntity>, list<string>|null>
     */
    private static array $allowedParentPathsByClass = [];

    /**
     * @var array<class-string<AbstractEntity>, list<array{0: string|null, 1: string, 2: string}>|null>
     */
    private static array $fragmentsByClass = [];

    /**
     * @var array<class-string<AbstractEntity>, list<array{0: class-string, 1: string|null, 2: string}>|null>
     */
    private static array $groupsByClass = [];

    /**
     * @var array<class-string<AbstractEntity>, list<string>>
     */
    private static array $sensitivePropertiesByClass = [];

    public static function warmUpReflectionCache(): void
    {
        foreach (\get_declared_classes() as $class) {
            self::primeReflectionCacheForClass($class);
        }
    }

    public function getEntityByEntityType(string $entityType): ?string
    {
        if (self::$entityTypeToClassMap === null) {
            self::$entityTypeToClassMap = [];
            self::warmUpReflectionCache();
        }

        if (isset(self::$entityTypeToClassMap[$entityType])) {
            return self::$entityTypeToClassMap[$entityType];
        }

        // Warmup again in case additional entity classes were autoloaded later.
        self::warmUpReflectionCache();

        if (isset(self::$entityTypeToClassMap[$entityType])) {
            return self::$entityTypeToClassMap[$entityType];
        }

        throw new \Exception(\sprintf('Entity type %s not implemented yet.', $entityType));
    }

    protected function resolveEntityType(string|EntityInterface $entity): ?string
    {
        $className = $this->resolveEntityClassName($entity);

        if (!\array_key_exists($className, self::$entityTypeByClass)) {
            self::primeReflectionCacheForClass($className);
        }

        return self::$entityTypeByClass[$className] ?? null;
    }

    /**
     * Returns property names marked with both #[Fragment] and #[Sensitive].
     *
     * @return list<string>
     */
    protected function resolveSensitiveProperties(string|EntityInterface $entity): array
    {
        $className = $this->resolveEntityClassName($entity);

        if (!\array_key_exists($className, self::$sensitivePropertiesByClass)) {
            self::primeReflectionCacheForClass($className);
        }

        return self::$sensitivePropertiesByClass[$className] ?? [];
    }

    /**
     * @return list<string>|null
     */
    private function resolveAllowedParentPaths(string|EntityInterface $entity): ?array
    {
        $className = $this->resolveEntityClassName($entity);

        if (!\array_key_exists($className, self::$allowedParentPathsByClass)) {
            self::primeReflectionCacheForClass($className);
        }

        return self::$allowedParentPathsByClass[$className] ?? null;
    }

    /**
     * @return list<array{0: string|null, 1: string, 2: string}>|null
     */
    private function resolveFragments(string|EntityInterface $entity): ?array
    {
        $className = $this->resolveEntityClassName($entity);

        if (!\array_key_exists($className, self::$fragmentsByClass)) {
            self::primeReflectionCacheForClass($className);
        }

        return self::$fragmentsByClass[$className] ?? null;
    }

    /**
     * @return list<array{0: class-string, 1: string|null, 2: string}>|null
     */
    private function resolveGroups(string|EntityInterface $entity): ?array
    {
        $className = $this->resolveEntityClassName($entity);

        if (!\array_key_exists($className, self::$groupsByClass)) {
            self::primeReflectionCacheForClass($className);
        }

        return self::$groupsByClass[$className] ?? null;
    }

    private function resolveEntityClassName(string|EntityInterface $entity): string
    {
        return \is_string($entity) ? $entity : $entity::class;
    }

    private static function primeReflectionCacheForClass(string $class): void
    {
        if (!\is_subclass_of($class, AbstractEntity::class)) {
            return;
        }

        if (
            \array_key_exists($class, self::$entityTypeByClass)
            && \array_key_exists($class, self::$allowedParentPathsByClass)
            && \array_key_exists($class, self::$fragmentsByClass)
            && \array_key_exists($class, self::$groupsByClass)
        ) {
            return;
        }

        $reflectionClass = new \ReflectionClass($class);

        $entityType = null;
        $allowedParentPaths = null;
        foreach ($reflectionClass->getAttributes(Item::class) as $attribute) {
            $item = $attribute->newInstance();
            $entityType = $item->entityType;
            if (\is_array($item->allowedParentPaths)) {
                $allowedParentPaths = $item->allowedParentPaths;
            }
            break;
        }

        self::$entityTypeByClass[$class] = $entityType;
        self::$allowedParentPathsByClass[$class] = $allowedParentPaths;

        if ($entityType !== null) {
            self::$entityTypeToClassMap ??= [];
            self::$entityTypeToClassMap[$entityType] = $class;
        }

        $parentClass = $reflectionClass->getParentClass();
        $parentProperties = ($parentClass === false) ? [] : $parentClass->getProperties();
        $properties = \array_merge($reflectionClass->getProperties(), $parentProperties);

        $fragments = [];
        $groups = [];
        $sensitiveProperties = [];

        foreach ($properties as $property) {
            foreach ($property->getAttributes(Fragment::class) as $attribute) {
                $fragment = $attribute->newInstance();
                $fragments[] = [
                    $fragment->storageStrategy,
                    $fragment->fragmentName ?? $property->getName(),
                    $property->getName(),
                ];

                // Collect properties that are also marked #[Sensitive]
                if (!empty($property->getAttributes(Sensitive::class))) {
                    $sensitiveProperties[] = $property->getName();
                }
            }

            foreach ($property->getAttributes(Group::class) as $attribute) {
                $group = $attribute->newInstance();
                $groups[] = [
                    $group->entity,
                    $group->groupType,
                    $property->getName(),
                ];
            }
        }

        self::$fragmentsByClass[$class] = (empty($fragments)) ? null : $fragments;
        self::$groupsByClass[$class] = (empty($groups)) ? null : $groups;
        self::$sensitivePropertiesByClass[$class] = $sensitiveProperties;
    }
}
