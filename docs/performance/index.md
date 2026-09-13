# Performance

## No overhead
DOM-ORM can actually be faster than a regular database because it operates as an in-memory data structure for read operations. We are talking microseconds instead of milliseconds. This is achieved by eliminating network latency and disk I/O. On the downside, a large XML file (a large databse) also leads to increased memory consumption, and slower write/pre-compile operations.

## Measured performance (5K–50K records)

Benchmarked against SQLite (raw PDO), PostgreSQL and MariaDB (Doctrine ORM)
with identical rows, a fresh PHP process per request, 30 iterations, medians.
Full data, charts and analysis: `benchmarks/compare/analysis/`.

Two request models matter:

- **Cold** (opcache disabled): every request compiles + loads the cache files.
- **Opcache-warm** (a real warmed deployment): opcache holds the compiled
  cache files in shared memory; only the first request after a deploy or
  rebuild compiles. See [opcache.md](opcache.md) for the configuration.

`find_by_id` (median ms):

| Backend | 5K cold | 5K warm | 10K cold | 10K warm | 50K cold | 50K warm |
|---|---|---|---|---|---|---|
| SQLite (raw PDO) | 0.30 | 0.29 | 0.32 | 0.31 | 0.34 | 0.36 |
| **DOM-ORM (cached)** | 19.0 | **2.04** | 29.1 | **2.08** | 109.3 | **2.22** |
| Doctrine SQLite | 15.9 | 2.52 | 16.2 | 2.70 | 16.5 | 2.97 |
| Doctrine MariaDB | 17.0 | 3.18 | 16.4 | 3.19 | 16.9 | 3.35 |
| Doctrine PostgreSQL | 22.7 | 8.81 | 21.9 | 8.92 | 22.4 | 8.99 |
| DOM-ORM (XPath, no cache) | 30.8 | 23.3 | 53.6 | 44.8 | 235.2 | 224.9 |

Takeaways:

- **With opcache, cached point lookups are size-independent: ~2 ms flat from
  5K to 50K** — the index-first lookup answers from warm opcodes without
  touching the XML. Faster than all three Doctrine+DB combinations at 50K;
  only raw PDO SQLite is quicker.
- **The ORM, not the engine, dominates cold numbers:** through Doctrine,
  SQLite (16.5 ms @50K) sits in the same band as MariaDB (16.9) and
  PostgreSQL (22.4) — vs 0.34 ms as raw PDO.
- **Without opcache the gap is real** (109 ms vs 16.5–22.4 ms vs 0.34 ms
  @50K) — opcache is the prerequisite for the competitive position.
- **`findAll()` is the weak spot** (624 ms cold → 487 ms opcache-warm @50K):
  opcache caches compiled opcodes, not evaluated arrays, so the payload array
  is rebuilt per request. Chunked cache (roadmap Phase 2) is the structural
  fix; raw SQLite owns bulk reads (27.8 ms @50K, 178–203 ms through an ORM).
- **Batch writes beat the networked Doctrine engines 3–5×** (0.019 ms/entity
  @50K vs 0.057 PostgreSQL / 0.071 MariaDB; Doctrine+SQLite 0.026).
- Methodology note: the raw PDO SQLite worker returns raw
  `PDO::FETCH_ASSOC` arrays — it does **not** hydrate entity objects the way
  DOM-ORM and Doctrine do, so its numbers are the "raw array" floor, not a
  like-for-like ORM comparison. Doctrine+SQLite is the fair one.

## Hash Maps and Query Cache
Under the hood, every DOM-ORM Repository method makes use of a pre-compiled in-memory PHP hash map. 
The PHP array cache generates a PHP file that PHP's opcache can pre-compile, giving O(1) ID
lookups and fast in-memory scans without XPath overhead. 

Two files are written, both derived from `cache_path` (e.g. `storage/cache.php` →
`storage/cache-index.php`):

```php
// cache.php — the payload: all entity data
<?php return [
    'user' => [
        'uuid1' => ['@id' => 'uuid1', '@type' => 'user', 'name' => 'Alice', ...],
    ],
    '__meta' => ['format' => 2, 'data_file' => 'data.xml', 'size' => ..., 'mtime' => ..., 'hash' => '...'],
];
```

```php
// cache-index.php — per-field inverted indexes (non-encrypted fields only)
<?php return [
    'user' => [
        'name' => ['Alice' => ['uuid1'], 'Bob' => ['uuid2']],
        'city' => ['Berlin' => ['uuid1', 'uuid3'], ...],
    ],
    '__meta' => ['format' => 2, 'hash' => '...', 'encrypted' => ['user' => ['ssn' => true], ...]],
];
```

The item arrays match the shape produced by `SchemaDecoder::decodeItem()`, so they can be
fed directly back into `SchemaDenormalizer`, which speeds up lookups by bypassing costly
XPath queries. Because the indexes live in their own file, `findBy()`/`findOneBy()` can
resolve candidate IDs — or answer a non-matching criterion with an empty result — without
loading the payload file (or the XML data file). Encrypted fields are listed in the index
`__meta.encrypted` block so such queries fall back to XPath (searchable-hash matching).
Legacy single-file caches (with `'__idx'` inside the payload) are detected on load and
rebuilt exactly once.

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

#### 4. Automatic staleness detection

Every cache file records a `__meta` block with a fingerprint of the data file it was
built from (`data_file`, `size`, `mtime`, `hash`). On every read the cache is checked against
the current data file before it is served:

- **Data changed externally** — if the fingerprint no longer matches (for example a cron job
  or a manual edit replaced `data.xml` between writes), the cache is **rebuilt automatically**
  from the current data and the fresh cache is served. No manual `build-cache` needed, and no
  stale data is served.
- **Legacy cache** — a cache with no fingerprint (written before this feature) is treated as
  stale and rebuilt once.
- **Fingerprint matches** — the cache is served as-is; there is no rebuild and no extra cost
  beyond the cheap fingerprint comparison.

The check is **stat-first**: when the data file's size and mtime are unchanged, the
comparison is a single `stat()` (microseconds) and the SHA-256 hash is not computed. The
full hash only runs when the stat differs. (Corner case: a rewrite that lands in the same
second *and* produces the same file size slips the fast path — the hash then catches it.)

This keeps reads correct when the XML is modified outside of DOM ORM, while preserving the
fast, in-memory read path for the common case. The fingerprint is written whenever the cache
is built (via `build-cache` or on `on_persist`), and verified on every subsequent read.

#### 5. Flush the cache

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
./dom-orm build-cache   # build (or rebuild) the query cache from the XML
./dom-orm flush-cache   # delete the query cache files
./dom-orm warm-cache    # build (if needed) + warm opcache, prints per-file status
```

## Batch Inserts
Persisting many entities can be slow, as the XML file has to be rewritten each time `persist($entity)` is called. To address this, there is a `persistBatch($entities)` method, which writes to the XML file only once.