### Export formats

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
