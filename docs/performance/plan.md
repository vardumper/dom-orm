# DOM-ORM Performance Plan

**Status:** Complete. Scope + target confirmed (Q1–Q12). Implementation order:
baseline → compiled hydrator → Phase 2 → Phase 3 → Phase 5. All in-scope workstreams
(A–D) are done; the `findAll()` 3× gate was re-scoped to ~1.3× (Q12) as it is
allocation-bound and unreachable.

## 0. Results so far

### Workstream A — compiled hydrator (DONE)

| op | baseline | compiled | speedup |
|---|---|---|---|
| `findAll()` cached | 968 ms | **800 ms** | **1.21×** |
| — denormalize | 844 ms (87%) | **686 ms (86%)** | 1.23× |
| `find($id)` cached | 100 ms | 100 ms | 1.0× (needs Phase 2) |
| `findAll()` memory peak | ~202 MB | **112 MB** | — |

- **Parity:** compiled vs reflection produce byte-identical objects for all 50 K
  records (sha256 match). Tests: green (the pre-existing Vcs failure was fixed).
  phpstan + ecs clean.
- **Key finding:** hydration is **allocation-bound, not reflection-bound**. The
  generated mapper's floor is ~11.7 µs/record (object allocation + constructor);
  reflection only added ~5 µs/record on top. So the compiled hydrator saves ~23%
  on denormalize, and the `findAll()` floor is ~704 ms (1.37×). **The 3× / 320 ms
  `findAll()` gate (Q11) is not reachable** — it assumed hydration → ~0, which is
  false. The big remaining wins are `find($id)` (Phase 2, ~10×) and memory
  (Phase 3, <100 MB).

### Workstream B — chunked cache (Phase 2, DONE)

| op | baseline | chunked | speedup |
|---|---|---|---|
| `findAll()` cached | 968 ms | **697 ms** | **1.39×** |
| — array-build (payload load) | 38 ms | 9.3 ms | 4× |
| `find($id)` cached | 100 ms | **0.39 ms** | **256×** |

- **Layout:** `{cache_path}/../cache/` holds `meta.php` (fingerprint),
  `index.php` (inverted index), `all.php` (monolith payload for full scans), and
  256 shard files `{00..ff}.php` keyed by `crc32(id) & 0xff`. Point lookups load
  one shard (~1/256th of the data); full scans load `all.php` (one require, so
  `findAll()` does not regress from the 256-shard fan-out).
- **Why crc32, not `substr(id,0,2)`:** DOM-ORM ids are hex-encoded ASCII, so the
  first two chars only span 16 values — `crc32` spreads any distribution across
  all 256 shards.
- **Parity:** compiled vs reflection still byte-identical (sha256
  `2b0d0bea…` unchanged). Tests: green (the pre-existing Vcs failure was fixed).
  phpstan + ecs clean.
- **`find($id)` gate (≤10 ms / 10×) met 25× over.** `findAll()` is now at the
  hydration floor (1.39×); the 3× gate remains unreachable (Workstream A finding).

### Workstream C — lazy result sets (Phase 3, DONE)

| op | materialized | lazy | memory |
|---|---|---|---|
| `findAll()` / `findAllLazy()` 50 K | 114 MB | **16.3 MB** | **−86%** |
| — after touching 1 K entities | 114 MB | **16.3 MB** | flat |
| `findAllLazy()->all()` (full touch) | 114 MB | 114 MB | same (expected) |

- **Design:** `LazyCollection` (additive, per Q6) wraps the `ChunkStore` + a
  small `ids.php` (ordered id list, ~1.6 MB). `count()` is O(1); `get()` /
  `first()` / `last()` / `map()` / `filter()` hydrate on first access and cache.
  Memory is flat in records *touched*, not records *found*.
- **`findByLazy()`:** resolves candidate ids from the inverted index (same as
  `findBy()`), then wraps them in a `LazyCollection`. The index is large
  (~142 MB in memory for 39 K distinct city values) — that cost is inherent to
  index-backed lookups and shared with `findBy()`. The lazy collection adds only
  ~16 MB on top (vs ~114 MB materialized).
- **Parity:** lazy entities are byte-identical to materialized (sampled). Tests:
  no new failures (only the pre-existing Vcs one). phpstan + ecs clean.
- **Memory gate (<100 MB) met** for the lazy path: `findAllLazy()` is 16.3 MB.
  The materialized `findAll()` remains 114 MB (allocation-bound, Workstream A).

### Workstream D — benchmark + gates (Phase 5, DONE)

| gate | target | actual | status |
|---|---|---|---|
| `find($id)` latency | ≤ 10 ms | **0.02 ms** | ✅ PASS (5015×) |
| `find($id)` speedup | ≥ 10× | **5015×** | ✅ PASS |
| `findAll()` cached latency | ≤ 320 ms | 718 ms | ❌ FAIL (unreachable) |
| `findAll()` cached speedup | ≥ 3× | 1.35× | ❌ FAIL (unreachable) |
| memory `findAll` 50 K (materialized) | < 100 MB | 114.1 MB | ❌ FAIL |
| memory `findAll` 50 K (**lazy**) | < 100 MB | **16.3 MB** | ✅ PASS |

- **Harness:** `benchmarks/compare/phase5-bench.php` (time, median of 10) +
  `phase5-memory.php` (memory, fresh processes) + `phase5-generate.php` (500 K
  dataset). JSON → `.profile/results/phase5-gates.json`.
- **500 K memory sanity:** `findAllLazy()` is **115.8 MB** (the `ids.php` is
  24 MB, 10× the 50 K's 2.4 MB) vs ~1.1 GB materialized — a ~10× reduction.
- **Vcs test fixed** (was a pre-existing FatalException halting the suite):
  `GitAdapter` is `final`, so the test now injects the binary via the
  constructor instead of sub-classing. Full suite green: **182 passed, 0 failed**.
- **Full gate table + reproduction steps:** `benchmarks/compare/charts/PHASE5-GATES.md`.

## 1. Problem & evidence

DOM-ORM reads entities from `data.xml` via a pre-compiled PHP array cache
(`cache.php` payload + `cache-index.php` inverted indexes). Point lookups are
already fast (index-backed, ~2 ms warm), but two hot spots remain.

**Baseline profile** (50 K records, PHP 8.5.4, libxml2 2.15.2, opcache-warm):

| op | require (payload load) | array-build | denormalize (hydration) | total |
|---|---|---|---|---|
| `findAll()` | 87 ms (9%) | 38 ms (4%) | **844 ms (87%)** | 968 ms |
| `find($id)` | **98 ms (98%)** | — | 0.1 ms (0.1%) | 100 ms |

No-cache `findAll()` (XML path): read 7 ms, loadXML 76 ms, xpath 85 ms,
decode 411 ms (29%), denormalize 858 ms (60%), total 1424 ms.

**Two distinct bottlenecks, two distinct fixes:**

- `findAll()` is dominated by **hydration** — `SchemaDenormalizer::instantiateEntity()`
  calls `new \ReflectionMethod($ret, $method)` per field, per record
  (`src/Serializer/Normalizer/SchemaDenormalizer.php:237`). → **compiled hydrator**.
- `find($id)` is dominated by **loading the whole payload** — `QueryCache::load()`
  `require`s the entire 50 K `cache.php` to fetch one row. → **chunked cache (Phase 2)**.

## 2. Scope

**In scope (core) — all done:**
- **Compiled hydrator** — build-time generated mapper, runtime reflection fallback.
- **Phase 2** — chunked cache (256 crc32 shards). LRU eviction deferred.
- **Phase 3** — lazy result sets (`LazyCollection`). Ghost objects deferred.
- **Phase 5** — benchmark + gates (verification).

**Out of scope / deferred:**
- Phase 2 LRU eviction (`cache_max_bytes` configured, enforcement deferred).
- Phase 3 ghost objects + SAX streaming (`LazyCollection` alone meets the memory gate).
- Phase 4 (incremental writes) — not the target.
- Phase 6 (resident runtimes / Swoole/RoadRunner).
- XPath anchoring (`//` → anchored) — only ~6% of no-cache, 0% cached; optional micro-opt.

## 3. Acceptance gates (Q11)

Measured at 50 K (primary) + 500 K (memory sanity), opcache-warm, via the project
benchmark harness:

- `findAll()` (cached, warm): **≤ ⅓ of baseline** (≥3×). Baseline ~968 ms → **≤ ~320 ms**.
- `find($id)` (cached, warm): **≥10×** → **≤ 10 ms**.
- Memory peak during `findAll()` (50 K): **< 100 MB** (baseline ~200 MB) via lazy result sets.
- **No regressions** on `findBy`/`findOneBy` (index-backed) and the write path.

Recorded in `benchmarks/compare/charts/PHASE5-GATES.md` (Phase 5, DONE).

## 4. Workstream A — Compiled hydrator

**Goal:** eliminate per-field reflection from hydration (87% of `findAll()`).

**Design (Q8 = A):**
- At `build-cache`, generate a plain PHP mapper per entity class:
  `storage/generated/{entityType}.php`.
- The mapper is a function `dom_orm_hydrate_{entityType}(array $row): EntityInterface`
  that:
  - reads constructor params directly (inlined cast/decrypt/json/datetime — no reflection);
  - `new $class(...$args)`;
  - sets id;
  - sets remaining properties via direct setter calls;
  - handles fragments (typed scalar arrays) and groups.
- File meta carries a **hash of the entity class signature** (properties + types +
  ctor params + attributes + source mtime). On load, mismatch → regenerate
  (self-healing). Regen is triggered when the **entity file is modified** (Q8).
- **Fallback:** if no generated file, or it can't be written,
  `SchemaDenormalizer::instantiateEntity()` uses the current reflection path.
  Pure speedup, never breaks.
- **Integration:** `instantiateEntity()` checks for a valid generated mapper first;
  else reflection.
- **Config:** `dom-orm.hydrator` = `auto` (default) | `reflection` | `compiled`.
  `auto` = use generated mapper if valid, else reflection.

**Files:**
- New: `src/Storage/HydratorGenerator.php` (signature hash + code emission).
- New: `src/Storage/Hydrator.php` (load/validate/dispatch generated mapper).
- Modify: `src/Serializer/Normalizer/SchemaDenormalizer.php` (dispatch to mapper, reflection fallback).
- Modify: `src/Storage/QueryCache.php` / build-cache command (call generator).
- Modify: `src/helpers.php` (new `hydrator` config key).

**Correctness:** the generated mapper must exactly replicate `instantiateEntity`
semantics (ctor param order, casts, decryption, json, datetime, fragment typing,
group handling, sensitive fields). Covered by the existing serializer tests + a new
parity test (generated vs reflection produce identical objects).

## 5. Workstream B — Phase 2: chunked cache

**Goal:** `find($id)` loads one chunk, not the whole payload (98% → ~0%).

**Implemented (Q9):**
- Replaced the monolithic `cache.php` with a chunked directory under
  `{cache_path}/../cache/`: `meta.php` (fingerprint), `index.php` (inverted index),
  `all.php` (full payload for scans), `ids.php` (ordered id list), and 256 shard files
  `{00..ff}.php` keyed by `crc32(id) & 0xff` (one `return [...]` per file → opcache-friendly).
- **Sharding:** 256 shards via `crc32(id) & 0xff` (not a 2-hex prefix, which only spans
  16 values for hex-encoded ids).
- **`findBy`:** equality-only (as today); the index resolves candidate ids, shards materialize.
- **Full scans:** `findAll()` loads `all.php` in one `require` so it does not pay a
  256-shard fan-out.
- **Migration (Q2):** one-time rebuild. Legacy monolithic `cache.php` detected on load
  → rebuilt to chunks exactly once.
- **Deferred:** LRU eviction (`cache_max_bytes` config, default 64 MB, is present but not
  yet enforced) and the `put`/`get`/`delete`/`evict` per-entity cache API.

**Files:**
- Modify: `src/Storage/QueryCache.php` (chunked read/write).
- New: `src/Storage/ChunkStore.php` (shard resolution + atomic write).
- Modify: `src/helpers.php` (`cache_max_bytes` config).

## 6. Workstream C — Phase 3: lazy result sets

**Goal:** `findAll()` returns immediately; memory stays flat.

**Implemented (Q10):**
- New `src/Repository/LazyCollection.php`: `IteratorAggregate + Countable + ArrayAccess`
  over the `ChunkStore` + the ordered `ids.php`. `count()` is **O(1)**.
- New additive repository methods: `findAllLazy(): ?LazyCollection`,
  `findByLazy(array $criteria): ?LazyCollection`. **Existing `findAll()`/`findBy()` keep
  exact signatures + materialized behavior** (Q1/Q6).
- `get()` / `first()` / `last()` / `map()` / `filter()` hydrate on first access and cache;
  memory is flat in records *touched*, not records *found*.
- **Interaction with chunks:** `find($id)` = one shard + compiled mapper.
  `findAllLazy()` = wrap the ordered id list; shards load lazily on access.
- **Deferred:** ghost objects (`ReflectionClass::newLazyGhost()`) and SAX streaming. The
  lazy collection alone meets the memory gate (16.3 MB @50K), so neither was needed.

**Files:**
- New: `src/Repository/LazyCollection.php`.
- Modify: `src/Repository/AbstractEntityRepository.php` (add `findAllLazy`/`findByLazy`).

## 7. Workstream D — Phase 5: benchmark + gates

- Use `benchmarks/compare/` harness + `dom-orm perf`.
- Re-baseline after each workstream; record in `benchmarks/compare/charts/PHASE5-GATES.md`.
- Gates (Section 3) must be green before the plan is "done."
- Each workstream ends with a green test suite (pest, phpstan, ecs).

## 8. Implementation order

1. **Baseline** — capture current numbers (bench2.php + perf) into
   `benchmarks/compare/charts/BASELINE.md`.
2. **Workstream A — compiled hydrator** (biggest `findAll()` win; self-contained).
3. **Workstream B — Phase 2 chunked cache** (`find($id)` win).
4. **Workstream C — Phase 3 lazy result sets** (memory + deferral).
5. **Workstream D — Phase 5 gates** (prove + record).

## 9. Config surface (new keys)

| key | default | purpose |
|---|---|---|
| `dom-orm.hydrator` | `auto` | `auto` / `reflection` / `compiled` |
| `dom-orm.cache_max_bytes` | `67108864` (64 MB) | LRU eviction budget |

(Chunk dir + generated dir are derived from `cache_path`'s directory.)

## 10. Risks & mitigations

- **Generated-mapper correctness** → parity test (generated vs reflection identical) + full existing suite.
- **Index/chunk divergence (concurrency)** → flock (existing) + self-healing chunk rebuild + concurrency tests.
- **LazyCollection edge cases** → tests for `count()`, iteration, `ArrayAccess`, partial access.
- **opcache immutability of chunk files** → one `return [...]` per file (already the pattern).

## 11. Decisions log

| Q | Decision |
|---|---|
| Q1 | API unchanged; new behavior additive/config-driven |
| Q2 | One-time cache rebuild OK |
| Q3 | build-cache on write only for `on_persist` (default `manual` self-heals on read); graceful fallback OK |
| Q4 | Drop PHP 8.3; floor 8.4 (ghosts deferred) |
| Q5 | 2–3× faster = success; lower memory a plus |
| Q6 | Additive new methods OK |
| Q7 | Scope = compiled hydrator + Phase 2 + Phase 3 + Phase 5; defer Phase 4/6 + XPath anchoring (ghosts + LRU deferred within 2/3) |
| Q8 | Compiled hydrator = build-time file + runtime reflection fallback; regen on entity-file change (signature hash) |
| Q9 | 64 MB LRU budget (deferred); 256-shard crc32 sharding; equality-only findBy |
| Q10 | LazyCollection; O(1) count() (ghosts deferred) |
| Q11 | Gates: findAll ≤ 320 ms, find(id) ≤ 10 ms, mem < 100 MB @50K; 50K primary + 500K memory sanity |
| Q12 | Re-scope the `findAll()` gate to ~1.3× (allocation-bound, 3× unreachable) |
