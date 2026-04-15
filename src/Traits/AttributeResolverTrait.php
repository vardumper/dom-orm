<?php

declare(strict_types=1);

namespace DOM\ORM\Traits;

use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Entity\EntityInterface;
use DOM\ORM\Mapping\Fragment;
use DOM\ORM\Mapping\Group;
use DOM\ORM\Mapping\Item;

trait AttributeResolverTrait
{
    /**
     * @var array<string, class-string<AbstractEntity>>|null
     */
    private static ?array $entityTypeToClassMap = null;

    public function getEntityByEntityType(string $entityType): ?string
    {
        if (self::$entityTypeToClassMap === null) {
            self::$entityTypeToClassMap = [];

            foreach (\get_declared_classes() as $class) {
                if (!\is_subclass_of($class, AbstractEntity::class)) {
                    continue;
                }

                $reflectionClass = new \ReflectionClass($class);
                $attributes = $reflectionClass->getAttributes(Item::class);
                foreach ($attributes as $attribute) {
                    $args = $attribute->getArguments();
                    if (!\array_key_exists('entityType', $args)) {
                        continue;
                    }

                    self::$entityTypeToClassMap[$args['entityType']] = $class;
                }
            }
        }

        if (isset(self::$entityTypeToClassMap[$entityType])) {
            return self::$entityTypeToClassMap[$entityType];
        }

        throw new \Exception(\sprintf('Entity type %s not implemented yet.', $entityType));
    }

    protected function resolveEntityType(string|EntityInterface $entity): ?string
    {
        $reflectionClass = new \ReflectionClass($entity);
        foreach ($reflectionClass->getAttributes(Item::class) as $attribute) {
            return $attribute->newInstance()->entityType;
        }

        return null;
    }

    /**
     * figures out if the entity has fixed parent paths
     */
    /**
     * @return list<string>|null
     */
    private function resolveAllowedParentPaths(string|EntityInterface $entity): ?array
    {
        $reflectionClass = new \ReflectionClass($entity);
        foreach ($reflectionClass->getAttributes(Item::class) as $attribute) {
            $value = $attribute->newInstance()->allowedParentPaths;
            if (\is_array($value)) {
                return $value;
            }
        }

        return null;
    }

    /**
     * @return list<array{0: string|null, 1: string, 2: string}>|null
     */
    private function resolveFragments(string|EntityInterface $entity): ?array
    {
        $reflectionClass = new \ReflectionClass($entity);

        $parentClass = $reflectionClass->getParentClass();
        $parentProperties = ($parentClass === false) ? [] : $parentClass->getProperties();
        $properties = \array_merge($reflectionClass->getProperties(), $parentProperties);

        $fragments = [];
        foreach ($properties as $property) {
            $attributes = $property->getAttributes(Fragment::class);

            foreach ($attributes as $attribute) {
                $fragment = $attribute->newInstance();

                $fragments[] = [
                    $fragment->storageStrategy,
                    $fragment->fragmentName ?? $property->getName(),
                    $property->getName(),
                ];
            }
        }

        if (empty($fragments)) {
            return null;
        }

        return $fragments;
    }

    /**
     * @return list<array{0: class-string, 1: string|null, 2: string}>|null
     */
    private function resolveGroups(string|EntityInterface $entity): ?array
    {
        $reflectionClass = new \ReflectionClass($entity);
        $parentClass = $reflectionClass->getParentClass();
        $parentProperties = ($parentClass === false) ? [] : $parentClass->getProperties();

        $properties = \array_merge(
            $reflectionClass->getProperties(),
            $parentProperties
        );

        $groups = [];
        foreach ($properties as $property) {
            $attributes = $property->getAttributes(Group::class);

            foreach ($attributes as $attribute) {
                $group = $attribute->newInstance();

                $groups[] = [
                    $group->entity,
                    $group->groupType,
                    $property->getName(),
                ];
            }
        }

        if (empty($groups)) {
            return null;
        }

        return $groups;
    }
}
