<?php

declare(strict_types=1);

namespace DOM\ORM\Mapping;

/**
 * Declares fragment rename/removal history for a class so the ORM can hydrate
 * entities that still have old fragment names in XML, and so the `migrate` and
 * `cleanup` CLI commands know which XML fragments are now orphaned.
 *
 * Usage:
 *
 *   #[ORM\Item(entityType: 'user')]
 *   #[ORM\FragmentMap(['fullName' => 'name', 'legacyEmail' => null])]
 *   class User extends AbstractEntity { ... }
 *
 * Map semantics:
 *   - 'oldName' => 'newName'  — fragment was renamed; old XML data is migrated to the new field
 *   - 'oldName' => null       — fragment was removed; old XML data is ignored and the orphan
 *                               can be pruned by `./vendor/bin/dom-orm cleanup`
 *
 * Conflict rule: if an item in XML already has BOTH the old name AND the new name, the new
 * name takes precedence and the old fragment is treated as orphaned.
 *
 * @phpstan-type FragmentMapping array<string, string|null>
 */
#[\Attribute(\Attribute::TARGET_CLASS | \Attribute::IS_REPEATABLE)]
final class FragmentMap
{
    /**
     * @param array<string, string|null> $map Keys are legacy XML fragment names.
     *        Values are the current fragment name (rename) or null (removed).
     */
    public function __construct(
        public readonly array $map = [],
    ) {
    }
}
