<?php

declare(strict_types=1);

namespace DOM\ORM\Command;

use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Mapping\Fragment;
use DOM\ORM\Mapping\FragmentMap;
use DOM\ORM\Mapping\Item;
use DOM\ORM\Storage\StorageService;

/**
 * Removes orphaned <fragment> nodes from the XML — i.e. fragments whose name no
 * longer appears in any current #[Fragment] attribute on the corresponding entity
 * class, and which are not listed as a rename target in #[FragmentMap].
 *
 * This is the safe counterpart to `migrate`: run `migrate` first to apply any
 * renames, then run `cleanup` to delete truly dead fragments.
 *
 * Supports --dry-run (preview only, no writes).
 */
class Cleanup
{
    /**
     * @param bool $dryRun  Preview changes only, do not write.
     * @return array{removed: int, items_affected: int, unknown_types: list<string>}
     */
    public static function run(bool $dryRun = false): array
    {
        $storage = StorageService::fromConfig();
        $xml = $storage->read();

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $dom->loadXML($xml);

        $xpath = new \DOMXPath($dom);

        $typeToClass = self::buildTypeToClassMap();
        $unknownTypes = [];

        $stats = [
            'removed' => 0,
            'items_affected' => 0,
            'unknown_types' => [],
        ];

        /** @var \DOMElement $item */
        foreach ($xpath->query('//item') as $item) {
            $type = $item->getAttribute('type');
            $entityClass = $typeToClass[$type] ?? null;
            if ($entityClass === null) {
                if (!\in_array($type, $unknownTypes, true)) {
                    $unknownTypes[] = $type;
                }

                continue;
            }

            // Compute the set of fragment names that are "live" for this entity:
            // all current #[Fragment] names PLUS all rename *targets* in #[FragmentMap]
            // (so a fragment just migrated to 'newName' is not immediately removed).
            $liveNames = self::resolveLiveFragmentNames($entityClass);

            $toRemove = [];
            /** @var \DOMElement $fragment */
            foreach ($xpath->query('fragment', $item) as $fragment) {
                $name = $fragment->getAttribute('name');
                if (!\in_array($name, $liveNames, true)) {
                    $toRemove[] = $fragment;
                }
            }

            if ($toRemove !== []) {
                foreach ($toRemove as $node) {
                    if (!$dryRun) {
                        $item->removeChild($node);
                    }
                    $stats['removed']++;
                }
                $stats['items_affected']++;
            }
        }

        $stats['unknown_types'] = $unknownTypes;

        if (!$dryRun && $stats['removed'] > 0) {
            $storage->write($dom->saveXML());
        }

        return $stats;
    }

    /**
     * Returns the set of fragment names that are considered "alive" for an entity:
     *   - Names from current #[Fragment] attributes (explicit fragmentName or property name)
     *   - Plus non-null rename targets from #[FragmentMap] (fragments just migrated)
     *
     * @param class-string<AbstractEntity> $class
     * @return list<string>
     */
    private static function resolveLiveFragmentNames(string $class): array
    {
        $names = [];
        $ref = new \ReflectionClass($class);

        // Current declared fragment names.
        $parentClass = $ref->getParentClass();
        $properties = \array_merge($ref->getProperties(), ($parentClass !== false) ? $parentClass->getProperties() : []);
        foreach ($properties as $property) {
            foreach ($property->getAttributes(Fragment::class) as $attr) {
                $fragment = $attr->newInstance();
                $names[] = $fragment->fragmentName ?? $property->getName();
            }
        }

        // Rename targets from FragmentMap (allow recently-migrated names to survive).
        foreach ($ref->getAttributes(FragmentMap::class) as $attr) {
            foreach ($attr->newInstance()->map as $newName) {
                if ($newName !== null) {
                    $names[] = $newName;
                }
            }
        }

        return \array_values(\array_unique($names));
    }

    /**
     * @return array<string, class-string<AbstractEntity>>
     */
    private static function buildTypeToClassMap(): array
    {
        $map = [];
        foreach (\get_declared_classes() as $class) {
            if (!\is_subclass_of($class, AbstractEntity::class)) {
                continue;
            }
            $ref = new \ReflectionClass($class);
            foreach ($ref->getAttributes(Item::class) as $attr) {
                $item = $attr->newInstance();
                $map[$item->entityType] = $class;
            }
        }

        return $map;
    }
}
