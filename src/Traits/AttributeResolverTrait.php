<?php

declare(strict_types=1);

namespace DOM\ORM\Traits;

use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Entity\EntityInterface;
use DOM\ORM\Mapping\Fragment;
use DOM\ORM\Mapping\FragmentMap;
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
     * @var array<class-string<AbstractEntity>, list<array{0: class-string, 1: string|null, 2: string, 3: bool}>|null>
     */
    private static array $groupsByClass = [];

    /**
     * @var array<class-string<AbstractEntity>, list<string>>
     */
    private static array $sensitivePropertiesByClass = [];

    /**
     * Merged fragment rename/removal map from all #[FragmentMap] attributes on a class.
     * Keys are legacy XML fragment names; values are new fragment names or null (removed).
     *
     * @var array<class-string<AbstractEntity>, array<string, string|null>>
     */
    private static array $fragmentMapByClass = [];

    /**
     * Cached ReflectionClass instances, keyed by class name.
     *
     * @var array<class-string<AbstractEntity>, \ReflectionClass<AbstractEntity>>
     */
    private static array $reflectionByClass = [];

    /**
     * Cached constructor parameter lists, keyed by class name.
     *
     * @var array<class-string<AbstractEntity>, list<\ReflectionParameter>>
     */
    private static array $constructorParamsByClass = [];

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
     * Returns the merged fragment rename/removal map declared via #[FragmentMap] on this class.
     * Keys are legacy XML fragment names; values are new names (string) or null (removed).
     *
     * @return array<string, string|null>
     */
    protected function resolveFragmentMap(string|EntityInterface $entity): array
    {
        $className = $this->resolveEntityClassName($entity);

        if (!\array_key_exists($className, self::$fragmentMapByClass)) {
            self::primeReflectionCacheForClass($className);
        }

        return self::$fragmentMapByClass[$className] ?? [];
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
     * Returns a memoised ReflectionClass for the given entity class.
     *
     * @template T of AbstractEntity
     * @param class-string<T> $class
     * @return \ReflectionClass<T>
     */
    protected function resolveReflectionClass(string $class): \ReflectionClass
    {
        if (!isset(self::$reflectionByClass[$class])) {
            self::primeReflectionCacheForClass($class);
            // primeReflectionCacheForClass stores the instance; guard for non-entity classes.
            self::$reflectionByClass[$class] ??= new \ReflectionClass($class);
        }

        return self::$reflectionByClass[$class];
    }

    /**
     * Returns a memoised list of constructor parameters for the given entity class.
     *
     * @param class-string<AbstractEntity> $class
     * @return list<\ReflectionParameter>
     */
    protected function resolveConstructorParams(string $class): array
    {
        if (!isset(self::$constructorParamsByClass[$class])) {
            $constructor = $this->resolveReflectionClass($class)->getConstructor();
            self::$constructorParamsByClass[$class] = $constructor !== null ? $constructor->getParameters() : [];
        }

        return self::$constructorParamsByClass[$class];
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
     * @return list<array{0: class-string, 1: string|null, 2: string, 3: bool}>|null
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
            && \array_key_exists($class, self::$fragmentMapByClass)
        ) {
            return;
        }

        $reflectionClass = self::$reflectionByClass[$class] = new \ReflectionClass($class);

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
                $propType = $property->getType();
                $isSingle = $propType instanceof \ReflectionNamedType
                    && !$propType->isBuiltin()
                    && \is_subclass_of($propType->getName(), AbstractEntity::class);
                $groups[] = [
                    $group->entity,
                    $group->groupType,
                    $property->getName(),
                    $isSingle,
                ];
            }
        }

        self::$fragmentsByClass[$class] = (empty($fragments)) ? null : $fragments;
        self::$groupsByClass[$class] = (empty($groups)) ? null : $groups;
        self::$sensitivePropertiesByClass[$class] = $sensitiveProperties;

        // Collect fragment rename/removal map from all #[FragmentMap] attributes (supports IS_REPEATABLE).
        $fragmentMap = [];
        foreach ($reflectionClass->getAttributes(FragmentMap::class) as $attr) {
            $fragmentMap = \array_merge($fragmentMap, $attr->newInstance()->map);
        }
        self::$fragmentMapByClass[$class] = $fragmentMap;
    }
}
