# Changelog

All notable changes to **DOM-ORM** are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this
project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

Full per-release details (with commit links) are available on the
[GitHub releases page](https://github.com/vardumper/dom-orm/releases).

## [Unreleased]

### Changed

- Demos now self-build the query cache (chunked store + compiled hydrator
  mappers) on the first request after a fresh deploy. No CLI step is needed:
  the per-demo `putenv` config is not visible to the CLI, and the CLI cannot
  load the demo entity classes required for mapper generation.
- Demo profilers: hover tooltips (`title` attributes) on the browser load
  time, server render time, and peak memory stats.

## [1.5.0] - 2026-10-03

A performance-focused release. The read path is now chunked, hydration is
compiled, and full scans can be lazy. Measured at 50 K entities, opcache-warm,
median of 10.

### Added

- **Compiled hydrator.** Build-time generated per-entity mappers
  (`src/Storage/HydratorGenerator.php`, `src/Storage/Hydrator.php`,
  `src/Storage/HydratorFunctions.php`) that bypass runtime reflection. New
  `hydrator` config key (`auto` / `reflection` / `compiled`, default `auto`) and
  `DOM_ORM_HYDRATOR` env var. Mappers are regenerated automatically when an
  entity file changes (signature hash).
- **Chunked query cache.** The monolithic `cache.php` is now a chunked directory
  (`src/Storage/ChunkStore.php`): `meta.php` (fingerprint), `index.php` (inverted
  index), `all.php` (full payload for scans), `ids.php` (ordered id list), and 256
  shard files `{00..ff}.php` keyed by `crc32(id) & 0xff`. A point lookup loads a
  single shard; a full scan loads `all.php` in one `require`. New `cache_max_bytes`
  config key (`DOM_ORM_CACHE_MAX_BYTES`, default 64 MB).
- **Lazy result sets.** New `src/Repository/LazyCollection.php`
  (`IteratorAggregate + Countable + ArrayAccess`, O(1) `count()`) and additive
  repository methods `findAllLazy()` / `findByLazy()`. `get()` / `first()` /
  `last()` / `map()` / `filter()` hydrate on first access and cache.
- **Benchmark harness + acceptance gates.** `benchmarks/compare/phase5-bench.php`,
  `phase5-memory.php`, and `phase5-generate.php`, with results recorded in
  `benchmarks/compare/charts/PHASE5-GATES.md`.

### Changed

- `find($id)` now loads one shard instead of the whole payload.
- `findAll()` loads `all.php` (a single `require`) rather than fanning out over
  256 shards.
- Existing `findAll()` / `findBy()` keep their exact signatures and materialized
  behavior; the lazy variants are purely additive.

### Fixed

- `Vcs\GitAdapter` is now `final` with an injectable git binary
  (constructor `private readonly string $binary = 'git'`), resolving the
  pre-existing Vcs test failure. The full test suite is now green.
- `ChunkStore::findByIds()` no longer casts numeric string shard keys to
  integers (which broke `loadShard()`); it now dedupes the shard list with
  `array_unique()` before loading.
- `SchemaDenormalizer::supportsDenormalization()` detects XML by its leading
  `<` instead of running `simplexml_load_string()`, eliminating parser warnings
  for JSON/YAML payloads and a latent `TypeError` on non-string input.

### Performance

| op | before | after | speedup |
|---|---|---|---|
| `find($id)` (cached, warm) | 100.31 ms | 0.02 ms | ~5015× |
| `findAll()` (cached, warm) | 968 ms | 718 ms | ~1.35× |
| `findAllLazy()` memory (50 K) | 114 MB (materialized) | 16.3 MB | −86% |
| `findAllLazy()` memory (500 K) | ~1.1 GB (materialized) | 115.8 MB | −90% |

Compiled-hydrator parity is verified: generated vs reflection output is
byte-identical (sha256 `2b0d0bea…`).

### Deferred (documented, not shipped)

- LRU eviction (`cache_max_bytes` is configured but not yet enforced).
- Ghost objects and SAX streaming (`LazyCollection` alone meets the memory gate).

## [1.4.9] - 2025-09-13

### Added

- Split query cache (payload + index).
- Stat-first staleness check (cheap `stat()` before the SHA-256 fingerprint).
- Lazy DOM loading (the DOM is parsed only when an XPath fallback or a write needs it).
- Cache-first reads.
- CLI commands: `build-cache`, `flush-cache`, `warm-cache`.
- Drop-in `deploy/opcache.ini`.

## [1.4.8] - 2025-09-13

### Changed

- Improved demo profilers: warm/cold info and browser vs. server-side rendering times.

## [1.4.7] - 2025-09-12

### Added

- Automated staleness detection via a SHA fingerprint — prevents serving a stale
  cache when `data.xml` is modified outside DOM-ORM.

## [1.4.6] - 2025-09-12

### Changed

- Improved demos, blog article ordering, and highlight.js behavior after XML reloading.
- Dependency bumps (dependabot).

## [1.4.5] - 2025-07-13

### Changed

- Updated composer dependencies (including demos).
- Improved the filesystem demo.

## [1.4.4] - 2025-05-08

### Changed

- House-keeping only; no code changes.

## [1.4.3] - 2025-05-08

### Added

- Demo performance improvements: static HTML cache, APCu XSLT caching (virtual
  filesystem demo), and Twig file-caching (blog demo).

### Fixed

- Hash maps: recursively add groups and group items.

## [1.4.2] - 2025-05-06

### Changed

- Allow polymorphic `ORM\Group` entities via `instanceof` instead of a strict class check.

## [1.4.1] - 2025-05-01

### Added

- `reset($bucket)` method on the in-memory Flysystem adapter to clear buckets after saving.

### Changed

- Improved test suite and git hooks.

## [1.4.0] - 2025-04-25

### Added

- PHP-native types `int`, `float`, `bool`, and `array` (with tests for the new and
  nullable types).
- `demos/` folder with a first usage example and auto-deploy.
- Documentation.

### Changed

- Improved tests (fixed deprecation warnings, autoloading fixtures in bootstrap).

## [1.3.3] - 2025-04-18

### Changed

- Improved scoped persistence.
- Added documentation and improved examples.
- Improved tests.

## [1.3.2] - 2025-04-18

### Added

- Built-in VCS versioning with `git` and `hg`.
- More documentation.

## [1.3.1] - 2025-04-15

### Added

- Performance profiling.
- Query cache with B-Tree indexes. At 100 K entities: `find()` by id ~2419× and
  `findOneBy()` ~14781× faster than XPath; batch writes ~18× faster than one-by-one.

## [1.3.0] - 2025-04-15

### Added

- Unit, integration, and benchmark test suites.

### Changed

- Refactored all PHP files to a modern coding style; improved runtime performance.
- Widened dependency constraints: installable on PHP 8.3 / 8.4 / 8.5 and
  Symfony 5.4 – 8.0.
- Improved git hooks.

### Fixed

- Bugs identified and fixed.

## [1.2.2] - 2024-09-15

### Changed

- Dependency bump (`symplify/easy-coding-standard`).

## [1.2.1] - 2024-09-15

### Fixed

- Create the data file if it does not exist.

## [1.2.0] - 2024-06-23

### Added

- `EntityRepository` class for ease of use.
- `remove()` method on `EntityRepository`.
- Examples in the README.

## [1.2] - 2024-06-23

### Added

- Decoding XML back to an array and denormalizing the array back to an entity
  instance now works.

## [1.1] - 2024-06-17

### Changed

- Corrected and simplified `README.md`.

## [1.0] - 2024-03-16

### Added

- Initial release.
