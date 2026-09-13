# Performance Roadmap

DOM-ORM's read path is benchmarked against real databases (SQLite, Doctrine + PostgreSQL,
Doctrine + MariaDB) using a **cold per-request model**: every measured query runs in a fresh
PHP process (fresh file load / DB connection / EntityManager), 30 iterations, medians.
Results and charts live in `benchmarks/compare/`.

This roadmap tracks the remaining optimization phases. Phase 1 is done; phases 2–5 are
planned. Each phase ends with a green test suite (pest, phpstan, ecs) and a re-baselined
benchmark run recorded in `benchmarks/compare/charts/SUMMARY.md`.

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

## Phase 2 — Chunked cache + LRU eviction

**Goal:** a cold `find()` should load one small chunk, not the whole payload.

- **Chunked payload format.** Replace the monolithic `cache.php` with per-entity chunk
  files, sharded: `chunks/{type}/{id[0:2]}/{id}.php` (one `return [...]` per file →
  opcache-friendly, individually evictable). `cache-index.php` stays the lookup layer
  (id → field values + chunk path).
- **`QueryCache` API.** Add `put(type, id, itemData)`, `get(type, id)`, `delete(type, id)`,
  `evict(maxBytes)`; new `cache_max_bytes` config (`DOM_ORM_CACHE_MAX_BYTES`, default
  e.g. 64 MB).
- **LRU bookkeeping + eviction.** LRU state in a small `cache-lru.php` file (id →
  last-access tick), updated on hit; eviction deletes the coldest chunk files when the
  budget is exceeded and prunes index entries for evicted ids. LRU is best-effort across
  processes (flock around index/lru updates, same lock as writes); a missed chunk falls
  back to an XML read + chunk write (self-healing).
- **Files:** `src/Storage/QueryCache.php`, `src/Storage/StorageService.php` (multi-file
  atomic write helper), `src/helpers.php` (new config keys).
- **Risk:** index/chunk divergence under concurrent writers — mitigated by the existing
  flock plus a "chunk missing → rebuild from XML" fallback; add concurrency tests.

## Phase 3 — SAX streaming + lazy result sets

**Goal:** `findAll()` should not materialize the whole dataset (memory peak 2.9 GB @500k).

- **`XmlStreamReader` (XMLReader).** New `src/Storage/XmlStreamReader.php` streams
  `<item>` elements one at a time (O(1) memory), reusing the existing decode logic
  (nested `<group>`, `searchable-hash`). Used by cache/index build (replaces DOM+XPath),
  targeted single-entity extraction for `find($id)`, and lazy iteration.
- **Lazy result sets.** New `src/Repository/LazyCollection.php`
  (`IteratorAggregate + Countable + ArrayAccess` over a generator; `count()` is
  lazy-cached, documented O(n)). New additive repository methods:
  `findAllLazy(): ?LazyCollection`, `findByLazy(array $criteria, ...): ?LazyCollection`.
  Existing `findAll()`/`findBy()` keep their exact signatures and materialized behavior.
- **Risk:** XMLReader has no XPath — group nesting and attribute edge cases need
  dedicated tests against the existing DOM decoder (same output for a corpus of files).
  Consumers expecting `Ramsey\Collection` stay on the old methods.

## Phase 4 — Write-through incremental invalidation

**Goal:** a write should update the cache in O(entity + index), not rebuild it
(full rebuild costs 7–10 s per write @500k with `on_persist`).

- **New `cache_strategy = 'incremental'`** (alongside `manual`, `on_persist`; default
  stays `manual`):
  - `persist()` → upsert the entity's chunk + update `cache-index.php` (diff old vs new
    field values, move the id between value buckets).
  - `removeById()` → delete chunk + prune index buckets.
  - `persistBatch()` → collect all diffs, one index rewrite at the end (protects the
    0.015 ms/entity batch number).
- **Files:** `src/Traits/EntityManagerTrait.php` (`writeCurrentState()` hook),
  `src/Storage/QueryCache.php` (`applyUpsert`/`applyDelete`).
- **Risk:** index drift after a crash mid-write — extend the existing SHA-256 fingerprint
  check to cover the index file hash, forcing a full rebuild when data and cache disagree.

## Phase 5 — Prove it

- **Extend the benchmark.** New ops in `benchmarks/compare/worker.php`: `find_all_lazy`
  (iterate + count) and `find_by_chunked` (chunked-cache find), measured in the same cold
  per-request model. Re-run the full suite at all 5 sizes; regenerate the 4 charts +
  `SUMMARY.md`; add a before/after panel per feature.
- **Acceptance targets** (from the Phase 1 baseline):

| target | baseline (Phase 1) | target |
|---|---|---|
| cold find() @500k | 1219 ms | < 50 ms |
| findAll() @500k memory | 2.9 GB peak | < 500 MB |
| incremental write @500k | ~8 s (on_persist) | < 100 ms |

- **Quality gates:** pest unit tests per feature (chunked cache CRUD + eviction, index
  coherence, streamer parity with the DOM decoder, LazyCollection semantics, incremental
  invalidation incl. a concurrent-writer test), plus the existing `composer test`,
  `phpstan`, `ecs` gates.

## Phase 6 — Resident runtime support (Swoole / RoadRunner)

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

## Sequencing

| Phase | Depends on | Can run parallel with |
|---|---|---|
| 2 (chunked cache + LRU) | 1 | 3 |
| 3 (SAX + lazy results) | — | 2 |
| 4 (incremental writes) | 2 | — |
| 5 (benchmark + gates) | 2–4 | — |
| 6 (resident runtimes) | 1 | 2–5 |

## Open questions (answer before starting phase 2)

1. LRU budget default: 64 MB? Evict by bytes, chunk count, or both?
2. Chunk sharding: 2-hex prefix (256 shards) or 1 hex (16 shards) for smaller datasets?
3. Should `findBy` support `IN`/prefix patterns, or stay equality-only (as today)?
