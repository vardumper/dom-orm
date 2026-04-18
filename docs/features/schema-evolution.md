# Schema Evolution

When you rename or remove a `#[Fragment]` property from an entity, the old XML fragment stays
in the file. DOM ORM handles this gracefully:

- **Unknown fragments are non-fatal** — hydration silently skips any fragment whose setter no
  longer exists, so your app keeps working after a property is removed.
- **Orphaned data is cleaned up explicitly** via CLI commands — there is no silent data loss.

## Declaring renames and removals

Add `#[ORM\FragmentMap]` at the class level to describe what changed. The attribute is
repeatable, so you can accumulate historical changes across multiple releases:

```php
use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Mapping as ORM;

#[ORM\Item(entityType: 'user')]
#[ORM\FragmentMap([
    'fullName'    => 'name',   // renamed: old XML key → new property name
    'legacyEmail' => null,     // removed: drop this fragment during cleanup
])]
class User extends AbstractEntity
{
    public function __construct(
        #[ORM\Fragment] private string $name,
        #[ORM\Fragment] private string $email,
    ) {
        parent::__construct();
    }
}
```

Map semantics:

| Value | Meaning |
|-------|---------|
| `'oldKey' => 'newKey'` | Renamed — old XML data is read into the new property until `migrate` is run |
| `'oldKey' => null`     | Removed — fragment is ignored during hydration; pruned by `cleanup` |

**Conflict rule:** if an XML item already has both `oldKey` and `newKey`, the new key wins and
the old fragment is treated as orphaned.

## How hydration works with a FragmentMap

The ORM applies the fragment map at read time **before** constructor/setter hydration, so
entities load correctly from old XML without any manual migration step:

1. Fragment `fullName` exists in XML → it is presented to the constructor as `name`.
2. Fragment `legacyEmail` exists in XML → it is dropped before hydration (setter is never called).
3. New entities written after the class change will only contain `name`/`email` fragments.

Old XML items keep their original shape until you run the CLI migration.

## CLI workflow

Always run `--dry-run` first to preview changes before writing.

### 1. Preview and apply renames/removals

```bash
# Preview what migrate would change
./vendor/bin/dom-orm migrate --dry-run

# Apply all FragmentMap renames and removals to the XML
./vendor/bin/dom-orm migrate
```

Example output:

```
Applied: 42 fragment(s) renamed, 7 fragment(s) removed across 31 item(s).
Done.
```

### 2. Remove truly orphaned fragments

After `migrate`, run `cleanup` to prune any remaining fragments that are not declared in any
current entity class (including ones with no `#[FragmentMap]` history):

```bash
# Preview what cleanup would delete
./vendor/bin/dom-orm cleanup --dry-run

# Apply cleanup
./vendor/bin/dom-orm cleanup
```

Example output:

```
Removed: 12 orphaned fragment(s) removed across 8 item(s).
Done.
```

> **Tip:** Add `./vendor/bin/dom-orm migrate && ./vendor/bin/dom-orm cleanup` to your
> deployment script to keep the XML clean after every schema change.

