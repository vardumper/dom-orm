# Performance

## No overhead
DOM-ORM can actually be faster than a regular database because it operates as an in-memory data structure for read operations. We are talking microseconds instead of milliseconds. This is achieved by eliminating network latency and disk I/O. On the downside, a large XML file (a large databse) also leads to increased memory consumption, and slower write/pre-compile operations.

## Hash Maps and Query Cache
Under the hood, every DOM-ORM Repository method makes use of a pre-compiled in-memory PHP hash map. 
The PHP array cache generates a PHP file that PHP's opcache can pre-compile, giving O(1) ID
lookups and fast in-memory scans without XPath overhead. 

The file format (written to cache_path):

```php
<?php return [
    'user' => [
        '__idx' => [
            'name' => ['Alice' => ['uuid1'], 'Bob' => ['uuid2']],
            'city' => ['Berlin' => ['uuid1', 'uuid3'], ...],
        ],
        'uuid1' => ['@id' => 'uuid1', '@type' => 'user', 'name' => 'Alice', ...],
    ],
];
```

The `'__idx'` sub-key holds per-field inverted indexes (non-encrypted fields only).
The inner item arrays match the shape produced by `SchemaDecoder::decodeItem()`, so 
they can be fed directly back into `SchemaDenormalizer` which speeds up lookups as it allows us to bypass costly XPath queries.

#### Configuration
Add `cache_path` to your `dom-orm.php`:

```php
<?php return [
    'dom-orm' => [
        // … existing config …
        'cache_path'     => __DIR__ . '/storage/cache.php',
        'cache_strategy' => 'manual',   // 'manual' (default) or 'on_persist'
    ],
];
```

| Option | Values | Description |
|--------|--------|-------------|
| `cache_path` | file path | Where the PHP cache file is written. `null` disables the cache entirely. |
| `cache_strategy` | `manual` | Cache is only rebuilt when you run `build-cache`. Recommended for write-heavy workloads. |
| | `on_persist` | Cache is rebuilt automatically after every `persist()` / `remove()`. Convenient for small datasets. |

#### 2. Build the cache

```bash
./vendor/bin/dom-orm build-cache
# → Cache written to /path/to/storage/cache.php.
```

Re-run any time after modifying the XML directly (persisting data, import, migrate, cleanup, etc.).

When `cache_strategy` is `on_persist`, DOM ORM can also emit one or several export formats in
the same save cycle. That keeps the XML source, the PHP query cache, and any derived read-only
snapshots in sync automatically.

#### 3. Reads are served from cache automatically

Once the cache file exists, `EntityRepository::find()`, `findAll()`, `findBy()`, and
`findOneBy()` use the cache instead of XPath — no code changes needed:

```php
$repo = new EntityRepository(User::class);
$user = $repo->find('uuid1');        // reads from cache.php
$users = $repo->findBy(['name' => 'Alice']);  // in-memory filter over cache
```

Queries involving encrypted sensitive fields fall back to XPath automatically (the cache
stores ciphertext, which cannot be matched without knowing the plaintext).

#### 4. Flush the cache

```bash
./vendor/bin/dom-orm flush-cache
```

XML remains the source of truth at all times. The cache is a derivative artifact that can be
rebuilt or deleted at any point.

> **Tip:** Add `build-cache` to your deployment script after running `migrate` and `cleanup`
> to keep reads fast after a schema change:
> ```bash
> ./vendor/bin/dom-orm migrate && ./vendor/bin/dom-orm cleanup && ./vendor/bin/dom-orm build-cache
> ```

### CLI Command
```bash
./dom-orm cache:build
./dom-orm cache:flush
```

## Batch Inserts
Persisting many entities can be slow, as the XML file has to be rewritten each time `persist($entity)` is called. To address this, there is a `persistBatch($entities)` method, which writes to the XML file only once.