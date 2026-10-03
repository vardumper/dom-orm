<?php

declare(strict_types=1);

namespace DOM\ORM\Mapping;

use DOM\ORM\Entity\AbstractEntity;

/**
 * Standalone entity-type <-> class resolver.
 *
 * AttributeResolverTrait keeps its own richer per-class caches (fragments,
 * groups, sensitive, fragment-map, reflection, constructor params), but calling
 * a *trait* static method from a class that does not use the trait is
 * deprecated in PHP 8.5. This class owns just the type<->class maps so the
 * storage layer (Hydrator, QueryCache) can resolve entity types without that
 * deprecation.
 */
final class EntityClassResolver
{
    /**
     * @var array<string, class-string<AbstractEntity>>|null
     */
    private static ?array $typeToClass = null;

    /**
     * @var array<class-string<AbstractEntity>, string|null>
     */
    private static array $classToType = [];

    /**
     * Resolve an entity type to its class, or null when unknown.
     *
     * @return class-string<AbstractEntity>|null
     */
    public static function classForEntityType(string $entityType): ?string
    {
        if (self::$typeToClass === null) {
            self::$typeToClass = [];
            self::warmUp();
        }

        if (isset(self::$typeToClass[$entityType])) {
            return self::$typeToClass[$entityType];
        }

        /** Warm up again in case additional entity classes were autoloaded later. */
        self::warmUp();

        return self::$typeToClass[$entityType] ?? null;
    }

    /**
     * Resolve a class to its entity type (from its #[Item] attribute), or null.
     */
    public static function entityTypeForClass(string $class): ?string
    {
        if (!\is_subclass_of($class, AbstractEntity::class)) {
            return null;
        }

        if (!\array_key_exists($class, self::$classToType)) {
            self::primeClass($class);
        }

        return self::$classToType[$class] ?? null;
    }

    public static function warmUp(): void
    {
        foreach (\get_declared_classes() as $class) {
            self::primeClass($class);
        }
    }

    private static function primeClass(string $class): void
    {
        if (!\is_subclass_of($class, AbstractEntity::class) || \array_key_exists($class, self::$classToType)) {
            return;
        }

        $rc = new \ReflectionClass($class);
        foreach ($rc->getAttributes(Item::class) as $attr) {
            $entityType = $attr->newInstance()->entityType;
            self::$classToType[$class] = $entityType;
            self::$typeToClass ??= [];
            self::$typeToClass[$entityType] = $class;

            return;
        }

        self::$classToType[$class] = null;
    }
}
