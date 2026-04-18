# Headless DB
For headless frontends, it may or may not be useful to have a pre-compiled data file instead of resorting to PHP for querying data. That said, the plaintext data.xml file that DOM ORM writes to, i exactly that.  
So, you can also export into several formats such as `JSON`, `YAML` and more.

## Excluding fields from exports

Mark any `#[Fragment]` property with `#[Exclude]` to omit it from all export formats.
The field is still persisted to `data.xml`; it is only stripped during export.

```php
use DOM\ORM\Mapping\Exclude;
use DOM\ORM\Mapping\Fragment;
use DOM\ORM\Mapping\Item;
use DOM\ORM\Entity\AbstractEntity;

#[Item(entityType: 'post')]
class Post extends AbstractEntity
{
    #[Fragment]
    public string $title = '';

    #[Fragment]
    public string $body = '';

    /** Internal property — not to be exposed in API exports. */
    #[Fragment]
    #[Exclude]
    public float $rankScore = 0.0;
}
```

Any field tagged `#[Exclude]` will be absent from every output format (`--json`, `--yaml`, `--xml`, `--php`).

## Export formats

Run one or more `--json`, `--yaml`, `--xml`, or `--php` flags to produce output files alongside
your XML store:

```bash
# Export as JSON and YAML (auto-named next to storage/data.xml)
./vendor/bin/dom-orm export --json --yaml

# Export to specific paths
./vendor/bin/dom-orm export --json /tmp/snapshot.json --yaml /tmp/snapshot.yaml
```

The exported shape is a flat, human-readable structure grouped by entity type:

```json
{
  "user": [
    { "id": "uuid1", "type": "user", "name": "Alice", "createdAt": "2024-01-01T00:00:00+00:00" }
  ]
}
```