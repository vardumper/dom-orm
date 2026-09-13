# Opcache & Cold Lookups

The benchmark model for the [performance comparison](../compare/) is a **cold
per-request** measurement: every query runs in a fresh PHP process, so every
run pays disk read + compile for the cache files. A real web deployment
(php-fpm / Apache) is different: **opcache persists across requests**, so only
the first request after a deploy/restart is cold — every later request serves
the compiled cache files from shared memory. The benchmark suite measures both
models (see the `opcache-warm` rows in `benchmarks/compare/analysis/`).

This page explains how to configure opcache so the warm path works correctly,
and what it does and does not fix.

## What opcache does (and does not) fix

For a `require` of `cache.php` / `cache-index.php`:

| Cost | Without opcache | With opcache (warm) |
|---|---|---|
| Disk read of the file | every request | never (after first request) |
| Compile to opcodes | every request | once (first request, or at restart with `file_cache`) |
| Build the returned array in process memory | every request | **still every request** |
| Per-process memory for the array | every request | **still every request** |

Two honest caveats:

1. **The array is rebuilt per request.** Opcache stores the *compiled script*,
   not the evaluated return value. `require` still executes the script, so a
   monolithic 500K payload costs ~100s of ms + ~300 MB per worker even when
   "warm". Chunking (Phase 2) is what removes this residual.
2. **The shared memory holds the compiled dataset.** For a whole-dataset file
   the opcache entry is roughly the size of the file itself (the constant pool
   holds every field value). That is the all-or-nothing in-memory model —
   acceptable while the dataset fits, and the reason `opcache.preload` is
   *not* recommended here: preloading the whole dataset at FPM startup is just
   the same model moved into shared memory, with no eviction.

## Measured impact (5K–50K benchmark)

From the benchmark suite (fresh process per request; the opcache-warm model
runs one long-lived parent that forks one child per request, because opcache's
shared memory is only inherited across `fork()` — see [Verifying](#verifying-opcache-holds-the-cache-file)):

| DOM-ORM cached `find_by_id` | cold (opcache off) | opcache-warm | Δ |
|---|---|---|---|
| 5K | 19.0 ms | 2.04 ms | 9.3× |
| 10K | 29.1 ms | 2.08 ms | 14× |
| 50K | 109.3 ms | 2.22 ms | 49× |

Point lookups become **size-independent (~2 ms flat from 5K to 50K)** — the
same shape as the databases, and at 50K faster than Doctrine+MariaDB (3.4 ms)
and Doctrine+PostgreSQL (9.0 ms); only SQLite is quicker (0.36 ms). `findAll()`
improves only ~1.3× (624 → 487 ms @50K) because of caveat 1 above (per-request
array rebuild). Full data: `benchmarks/compare/analysis/`.

## Recommended settings

Drop-in file: [`deploy/opcache.ini`](../../deploy/opcache.ini). The important
dials:

| Setting | Value | Why |
|---|---|---|
| `opcache.enable` | `1` | The whole point. |
| `opcache.memory_consumption` | ≥ payload size (e.g. `256`, in MB) | If the compiled payload does not fit, opcache LRU-evicts it and requests go cold at random. |
| `opcache.max_file_size` | `0` (default) | In **bytes**; `0` = no limit — correct for a monolithic payload. Only set it to exclude files above a size. |
| `opcache.validate_timestamps` | `1` | Stat the file on every require. |
| `opcache.revalidate_freq` | `0` | Recompile **only when mtime/size changed**, checked on every request. The PHP default (`2`) skips the stat for 2 s — a rebuilt cache can be invisible for up to 2 s. |
| `opcache.interned_strings_buffer` | `16` (MB) | The cache files are string-heavy. |
| `opcache.file_cache` | path (optional) | Persist compiled scripts to disk; after a restart, workers load from the file cache instead of recompiling. |
| `opcache.file_cache_only` | `0` | `1` = never read scripts from disk, only from the file cache. Aggressive; not recommended while the payload is monolithic. |

### Gotchas (found while verifying)

- **Docker's `/dev/shm` is 64 MB by default.** Opcache's shared memory lives in
  `/dev/shm`; if the segment cannot be allocated, opcache silently degrades and
  *nothing* gets cached. Run the container with `--shm-size=1g` (or size
  `memory_consumption` to fit the default shm).
- **`memory_consumption` must fit the compiled payload** (roughly its on-disk
  size, plus your app code). A 150 MB payload needs ≥ 256 MB.
- **`max_file_size` is in bytes** — a value like `1024` means a 1 KB limit, not
  1 GB. Leave it at the default `0` (no limit).

With `validate_timestamps=1` + `revalidate_freq=0` you get exactly
"re-read only when changes happen": one `stat()` per require (microseconds),
recompile only when the file actually changed.

### Two staleness layers

Opcache's mtime check and DOM-ORM's own fingerprint check are complementary:

- **Opcache (mtime/size of the cache file):** a `build-cache` or `on_persist`
  rebuild bumps the cache file's mtime → opcache recompiles automatically. No
  action needed.
- **DOM-ORM fingerprint (hash of `data.xml`):** catches the case where the
  *data* file changed while the cache file is untouched (`manual` strategy,
  external edits). Since the stat-first optimization, this check is a single
  `stat()` when size + mtime are unchanged — the full SHA-256 only runs when
  the stat differs.

## Deploy flow (pre-warming)

```bash
# 1. Rebuild the cache files from the current data
./dom-orm build-cache

# 2. Warm opcache (and opcache.file_cache, when configured):
#    compiles both cache files into shared memory.
php -d opcache.enable_cli=1 \
    -d opcache.memory_consumption=256 \
    -d opcache.file_cache=/var/cache/php-opcache \
    ./dom-orm warm-cache

# 3. Restart the web SAPI (php-fpm). With file_cache configured, workers load
#    the opcodes from disk instead of recompiling — no first-request penalty.
```

`warm-cache` builds the cache if it is missing, requires both cache files, and
prints per-file opcache status (`cached: ... (KB, hits)` or
`NOT cached: ... — check opcache.max_file_size`) so you can verify the warmup
actually worked.

## Verifying opcache holds the cache file

**Important:** freshly *spawned* PHP processes do **not** share opcache. The
default shared-memory allocator creates a per-process private segment, and PHP
8.5 has no attach-to-existing-segment path — so "run the script twice, expect
`hits=1` on the second run" does **not** work with two separate `php`
invocations (each compiles its own copy). Sharing happens via `fork()`
inheritance — which is exactly how php-fpm workers share it (all workers are
forked from the master, which initialized opcache).

The correct proof is a parent that compiles the file and forks children — the
children must see the parent's opcodes and the hit counter must climb:

```bash
# benchmarks/compare/opcache-forktest.php (parent compiles, forks 3 children)
php -d opcache.enable_cli=1 -d opcache.memory_consumption=256 \
    -d opcache.validate_timestamps=1 -d opcache.revalidate_freq=0 \
    benchmarks/compare/opcache-forktest.php
# → child 0: hits=1
# → child 1: hits=2
# → child 2: hits=3
```

To inspect the entry in the current process (e.g. inside `warm-cache` output),
use `opcache_get_status(true)` after the `require` — the per-script map gives
`hits` and the memory used (`memory_consumption` on PHP 8.5+,
`memory_consumed` on older versions). A `NOT CACHED` result means the file did
not fit: check `opcache.memory_consumption` and `opcache.max_file_size`.

## When opcache is not enough

If the warm per-request cost (array rebuild + per-process memory) is still too
high for your dataset, the fix is structural, not configurational:

- **Phase 2** (chunked cache + LRU): a cold `find()` loads the index + one
  small chunk → cold ≈ warm (ms range), and shared memory holds only what is
  hot.
- **Phase 3** (SAX streaming + lazy result sets): `findAll()` without
  materializing the dataset.

See [roadmap.md](roadmap.md).
