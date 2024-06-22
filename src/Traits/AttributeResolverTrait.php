<?php

declare(strict_types=1);

namespace DOM\ORM\Traits;

use DOM\ORM\Entity\EntityInterface;
use DOM\ORM\Mapping\Fragment;
use DOM\ORM\Mapping\Group;
use DOM\ORM\Mapping\Item;

trait AttributeResolverTrait
{
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
