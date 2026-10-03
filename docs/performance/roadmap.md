# Performance Roadmap

DOM-ORM's read path is benchmarked against real databases (SQLite, Doctrine + PostgreSQL,
Doctrine + MariaDB) using a **cold per-request model**: every measured query runs in a fresh
PHP process (fresh file load / DB connection / EntityManager), 30 iterations, medians.
Results and charts live in `benchmarks/compare/`.

This roadmap tracks the optimization phases. **Phases 1, 2, 3, and 5 are done; phases 4
and 6 are deferred.** Each completed phase ended with a green test suite (pest, phpstan,
ecs) and a re-baselined benchmark run recorded in `benchmarks/compare/charts/`. Measured
results live in [plan.md](plan.md).

## Phase 1 — Quick wins (done)

- **Lazy DOM load** — `EntityManagerTrait::init()` no longer parses the XML; the DOM is
  loaded only when an XPath fallback or a write needs it. Cache-backed reads never touch
  the data file.
- **Split cache (payload + index)** — `cache_path` now produces two files:
  `cache.php` (entity payloads) and `cache-index.php` (per-field inverted indexes +
  encrypted-field markers). `findBy()`/`findOneBy()` resolve candidate IDs from the index
  alone, so a non-matching criterion answers with an empty result without loading the
  payload (or the DOM). Legacy single-file caches are rebuilt exactly once.

Measured effect (cold cache backend, median ms @500k entities):

| op | before | after |
|---|---|---|
| find() by ID | 3551.6 | 1219.4 (2.9×) |
| findOneBy() by email | 3654.1 | 2284.6 (1.6×) |
| findBy() by city | 3525.3 | 2287.7 (1.5×) |
| findAll() | 9629.7 | 6397.2 (1.5×) |

The remaining gap to databases is dominated by loading the monolithic payload file and by
materializing full result sets. That is what phases 2 and 3 target.

## Phase 2 — Chunked cache (done)

**Goal:** a `find()` should load one small chunk, not the whole payload.

- **Chunked payload format.** The monolithic `cache.php` was replaced by a chunked
  directory under `{cache_path}/../cache/`: `meta.php` (fingerprint), `index.php`
  (inverted index), `all.php` (full payload for scans), `ids.php` (ordered id list),
  and 256 shard files `{00..ff}.php` keyed by `crc32(id) & 0xff`. A point lookup loads
  exactly one shard (~1/256th of the data); a full scan loads `all.php` in one `require`
  so `findAll()` does not pay a 256-shard fan-out.
- **Why crc32, not `substr(id, 0, 2)`:** DOM-ORM ids are hex-encoded ASCII, so the first
  two chars only span 16 values — `crc32` spreads any distribution across all 256 shards.
- **Files:** `src/Storage/ChunkStore.php` (new), `src/Storage/QueryCache.php`,
  `src/helpers.php` (`cache_max_bytes` config key, default 64 MB).
- **Result:** `find($id)` 100 ms → 0.39 ms (256×); `findAll()` 968 ms → 697 ms (1.39×).
- **Deferred:** LRU eviction (`cache_max_bytes` is configured but not yet enforced) and
  the `put`/`get`/`delete`/`evict` per-entity cache API.

## Phase 3 — Lazy result sets (done)

**Goal:** `findAll()` should not materialize the whole dataset.

- **Lazy result sets.** New `src/Repository/LazyCollection.php`
  (`IteratorAggregate + Countable + ArrayAccess` over the `ChunkStore` + the ordered
  `ids.php`; `count()` is O(1)). New additive repository methods:
  `findAllLazy(): ?LazyCollection`, `findByLazy(array $criteria, ...): ?LazyCollection`.
  Existing `findAll()`/`findBy()` keep their exact signatures and materialized behavior.
  `get()` / `first()` / `last()` / `map()` / `filter()` hydrate on first access and cache;
  memory is flat in records *touched*, not records *found*.
- **Result:** `findAllLazy()` 50 K is 16.3 MB vs 114 MB materialized (−86%); 500 K lazy is
  115.8 MB vs ~1.1 GB materialized.
- **Deferred:** SAX streaming (`XmlStreamReader` via XMLReader) and ghost objects. The
  lazy collection alone meets the memory gate, so neither was needed for this scope.

## Phase 4 — Write-through incremental invalidation (deferred)

**Goal:** a write should update the cache in O(entity + index), not rebuild it
(full rebuild costs 7–10 s per write @500k with `on_persist`).

- **New `cache_strategy = 'incremental'`** (alongside `manual`, `on_persist`; default
  stays `manual`):
  - `persist()` → upsert the entity's shard + update `index.php` (diff old vs new
    field values, move the id between value buckets).
  - `removeById()` → delete chunk + prune index buckets.
  - `persistBatch()` → collect all diffs, one index rewrite at the end (protects the
    0.015 ms/entity batch number).
- **Files:** `src/Traits/EntityManagerTrait.php` (`writeCurrentState()` hook),
  `src/Storage/QueryCache.php` (`applyUpsert`/`applyDelete`).
- **Risk:** index drift after a crash mid-write — extend the existing SHA-256 fingerprint
  check to cover the index file hash, forcing a full rebuild when data and cache disagree.

## Phase 5 — Prove it

- **Benchmark harness.** `benchmarks/compare/phase5-bench.php` (time gates, median of 10,
  opcache-warm), `phase5-memory.php` (memory in fresh processes), and
  `phase5-generate.php` (500 K dataset). Results recorded in
  `benchmarks/compare/charts/PHASE5-GATES.md`.
- **Acceptance gates (50 K, opcache-warm):**

| gate | target | actual | status |
|---|---|---|---|
| `find($id)` latency | ≤ 10 ms | 0.02 ms | ✅ (5015×) |
| `findAll()` cached | ≤ 320 ms | 718 ms | ❌ unreachable (allocation floor ~1.37×) |
| memory (materialized) | < 100 MB | 114.1 MB | ❌ |
| memory (lazy) | < 100 MB | 16.3 MB | ✅ |
| 500 K lazy sanity | — | 115.8 MB (vs ~1.1 GB) | ✅ |

- **Quality gates:** pest (182 passed, 0 failed), phpstan, and ecs all green.

## Phase 6 — Resident runtime support (Swoole / RoadRunner) (deferred)

**Goal:** in a long-lived worker runtime, a request should pay the warm
in-process numbers (0.011 ms lookups), not the per-request numbers.

Background: PHP exposes no userland API for compiled XPath expressions —
`DOMXPath::query()` always hands the raw string to libxml2, which re-parses it
per call (`xmlXPathCompExpr` exists only inside libxml2/C). XPath-level
expression caching is therefore **not an option in userland** (recorded as a
rejected path). The query cache already avoids XPath entirely on the read
path, so the remaining lever is **document persistence**: in a worker runtime
(Swoole, RoadRunner) the process stays alive, so the DOM *and* the loaded
cache arrays stay resident across requests — the repository's memoized cache
instance then serves every request from memory with no per-request array
rebuild, no stat, no require.

- **Benchmark model.** Add a `resident` run to `benchmarks/compare/`: one
  long-lived process, N sequential requests against a persistent
  EntityManager/Repository. Report as Model D alongside cold / opcache-warm /
  warm-inprocess.
- **Resident-mode fingerprint.** The stat-first staleness check already costs
  one `stat()` per query; add optional `cache_check_interval` (seconds,
  default `0` = check every query) for resident workers that accept slightly
  staler reads in exchange for skipping the stat.
- **Docs.** Deployment guide for Swoole/RoadRunner: keep the EntityManager
  alive across requests; rebuild the cache on deploy (not per request);
  `on_persist` stays viable because writes happen in the same resident process.

## Status summary

| Phase | Status |
|---|---|
| 1 (quick wins) | done |
| 2 (chunked cache) | done (LRU eviction deferred) |
| 3 (lazy result sets) | done (SAX streaming + ghosts deferred) |
| 4 (incremental writes) | deferred |
| 5 (benchmark + gates) | done |
| 6 (resident runtimes) | deferred |

## Decisions (resolved)

1. LRU budget default: **64 MB** (`cache_max_bytes`, `DOM_ORM_CACHE_MAX_BYTES`) — configured,
   enforcement deferred with Phase 4.
2. Chunk sharding: **256 shards** via `crc32(id) & 0xff` (not a 2-hex prefix, which only
   spans 16 values for hex-encoded ids).
3. `findBy` stays **equality-only** (as today).
