<?php

declare(strict_types=1);

namespace DOM\ORM\Command;

use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Mapping\Fragment;
use DOM\ORM\Mapping\FragmentMap;
use DOM\ORM\Mapping\Item;
use DOM\ORM\Storage\StorageService;

/**
 * Applies all #[FragmentMap] rename/removal declarations to the live XML.
 *
 * For every <item> in the document:
 *   - If the entity class has a FragmentMap entry 'old' => 'new', and the item
 *     has <fragment name="old"> but NOT <fragment name="new">, the old fragment
 *     is renamed in-place.
 *   - If the entry is 'old' => null the old fragment is removed.
 *   - If BOTH old and new already exist, the old fragment is removed (new wins).
 *
 * Supports --dry-run (preview only, no writes) and --force (skip confirmation).
 */
class Migrate
{
    /**
     * @param bool $dryRun  Preview changes only, do not write.
     * @param bool $force   Skip the "are you sure?" prompt (always writes when not dry-run).
     * @return array{renamed: int, removed: int, items_affected: int}
     */
    public static function run(bool $dryRun = false, bool $force = false): array
    {
        $storage = StorageService::fromConfig();
        $xml = $storage->read();

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $dom->formatOutput = true;
        $dom->loadXML($xml);

        $xpath = new \DOMXPath($dom);

        // Build entity-type → class map from all declared entities.
        $typeToClass = self::buildTypeToClassMap();

        $stats = [
            'renamed' => 0,
            'removed' => 0,
            'items_affected' => 0,
        ];

        /** @var \DOMElement $item */
        foreach ($xpath->query('//item') as $item) {
            $type = $item->getAttribute('type');
            $entityClass = $typeToClass[$type] ?? null;
            if ($entityClass === null) {
                continue;
            }

            $fragmentMap = self::resolveFragmentMap($entityClass);
            if ($fragmentMap === []) {
                continue;
            }

            $itemAffected = false;
            foreach ($fragmentMap as $oldName => $newName) {
                $oldFragments = $xpath->query(\sprintf('fragment[@name="%s"]', $oldName), $item);
                if ($oldFragments === false || $oldFragments->length === 0) {
                    continue;
                }

                $oldFragment = $oldFragments->item(0);
                \assert($oldFragment instanceof \DOMElement);

                if ($newName !== null) {
                    // Check if new fragment already exists (conflict: new wins).
                    $newFragments = $xpath->query(\sprintf('fragment[@name="%s"]', $newName), $item);
                    if ($newFragments !== false && $newFragments->length > 0) {
                        // Remove orphaned old fragment (new already present).
                        if (!$dryRun) {
                            $item->removeChild($oldFragment);
                        }
                        $stats['removed']++;
                    } else {
                        // Rename: update name attribute in place.
                        if (!$dryRun) {
                            $oldFragment->setAttribute('name', $newName);
                        }
                        $stats['renamed']++;
                    }
                } else {
                    // Removal: delete the fragment.
                    if (!$dryRun) {
                        $item->removeChild($oldFragment);
                    }
                    $stats['removed']++;
                }

                $itemAffected = true;
            }

            if ($itemAffected) {
                $stats['items_affected']++;
            }
        }

        if (!$dryRun && ($stats['renamed'] > 0 || $stats['removed'] > 0)) {
            $storage->write($dom->saveXML());
        }

        return $stats;
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

    /**
     * @param class-string<AbstractEntity> $class
     * @return array<string, string|null>
     */
    private static function resolveFragmentMap(string $class): array
    {
        $map = [];
        $ref = new \ReflectionClass($class);
        foreach ($ref->getAttributes(FragmentMap::class) as $attr) {
            $map = \array_merge($map, $attr->newInstance()->map);
        }

        return $map;
    }
}
