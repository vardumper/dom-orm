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
    public function getEntityByEntityType(string $entityType): ?string
    {
        // get Entity classes only
        $entityClasses = [];
        foreach (get_declared_classes() as $class) {
            if (is_subclass_of($class, AbstractEntity::class)) {
                $entityClasses[] = $class;
            }
        }

        foreach ($entityClasses as $className) {
            $reflectionClass = new \ReflectionClass($className);
            $attributes = $reflectionClass->getAttributes(Item::class);
            foreach ($attributes as $attribute) {
                $args = $attribute->getArguments();
                if (!array_key_exists('entityType', $args)) {
                    continue;
                }
                if ($args['entityType'] === $entityType) {
                    return $className;
                }
            }
        }

        throw new \Exception(sprintf('Entity type %s not implemented yet.', $entityType));
    }

    /**
     * figures out if the entity has fixed parent paths
     */
    private function resolveAllowedParentPaths(string|EntityInterface $entity): ?array
    {
        $reflectionClass = new \ReflectionClass($entity);
        foreach ($reflectionClass->getAttributes(Item::class) as $attribute) {
            $value = $attribute->newInstance()->allowedParentPaths;
            if (is_array($value)) {
                return $value;
            }
        }

        return null;
    }

    private function resolveEntityType(string|EntityInterface $entity): ?string
    {
        $reflectionClass = new \ReflectionClass($entity);
        foreach ($reflectionClass->getAttributes(Item::class) as $attribute) {
            return $attribute->newInstance()->entityType;
        }

        return null;
    }

    private function resolveFragments(string|EntityInterface $entity): ?array
    {
        $reflectionClass = new \ReflectionClass($entity);

        $properties = array_merge($reflectionClass->getProperties(), $reflectionClass->getParentClass()->getProperties());

        $fragments = [];
        foreach ($properties as $property) {
            // var_dump(get_class_methods($property));
            $attributes = $property->getAttributes(Fragment::class);

            foreach ($attributes as $attribute) {
                $fragment = $attribute->newInstance();

                $fragments[] = [
                    // The event that's configured on the attribute
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

    private function resolveGroups(string|EntityInterface $entity): ?array
    {
        $reflectionClass = new \ReflectionClass($entity);

        $properties = array_merge(
            $reflectionClass->getProperties(),
            $reflectionClass->getParentClass()->getProperties()
        );

        $groups = [];
        foreach ($properties as $property) {
            $attributes = $property->getAttributes(Group::class);

            foreach ($attributes as $attribute) {
                $group = $attribute->newInstance();

                $groups[] = [
                    $group->entity, // eg: App\Entity\UserRole
                    $group->groupType, // eg: 'user_roles'
                    $property->getName(), // eg: 'roles'
                ];
            }
        }

        if (empty($groups)) {
            return null;
        }

        return $groups;
    }
}
