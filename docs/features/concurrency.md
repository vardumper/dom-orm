# Concurrency

DOM ORM uses a read-modify-write workflow: it loads the XML file into memory, changes the DOM,
and writes the whole document back. Without coordination, concurrent writers can overwrite each
other.

## Local storage

When you use the default local Flysystem adapter, DOM ORM automatically uses a blocking
`flock()` lock file next to the XML store:

```text
storage/data.xml
storage/data.xml.lock
```

The lock is held across the full read-modify-write cycle, so concurrent PHP processes queue
behind the active writer instead of corrupting the XML or silently dropping updates.

Every write operation reloads the latest XML while holding that lock, applies the mutation,
then writes the updated document back. That prevents a long-lived in-memory DOM from
overwriting changes another PHP process committed between two writes.

You can override the lock path explicitly:

```php
<?php return [
  'dom-orm' => [
    'flysystem' => [
      'adapter' => League\Flysystem\Local\LocalFilesystemAdapter::class,
      'config' => [__DIR__ . '/storage'],
    ],
    'filename' => 'data.xml',
    'lock_file' => __DIR__ . '/storage/data.xml.lock',
  ],
];
```

## Remote storage

For remote adapters such as S3, Azure Blob Storage, Google Cloud Storage, or SFTP, DOM ORM does
**not** provide a distributed lock. PHP `flock()` only works on local filesystems, so DOM ORM
cannot make cross-process or cross-host write safety guarantees for remote storage by itself.

That means remote storage remains vulnerable to last-write-wins races unless you add external
coordination.

Use one of these patterns when running against remote storage:

- Route all writes through a single worker or queue.
- Guard writes with an external distributed lock such as Redis, PostgreSQL advisory locks, or a cloud-native lease mechanism.
- Treat remote adapters as read-mostly snapshots and perform writes on a single local authority.

## Built-in in-memory adapter

When using `DOM\\ORM\\Storage\\InMemoryFilesystemAdapter`, XML is kept only in PHP process memory.
The adapter **never writes to disk** — the "file" only exists as a string in memory. It is a
scratchpad for a unit of work, not a persistent store: you load, operate, and flush it yourself.

The intended workflow:

1. Load existing XML from your own durable store (e.g. a database column) into the adapter.
2. Run any number of `persist()`/read operations in-process — no disk I/O, no lock file.
3. Read the final XML back out and store it in your durable store yourself — you control the
   transaction/locking (e.g. database row/advisory lock).
4. Call `InMemoryFilesystemAdapter::reset($location)` to release the memory.

```php
// at the end of the request/job
$xml = DOM\ORM\Storage\StorageService::fromConfig()->read();
file_put_contents('/path/to/real-storage/data.xml', $xml); // or a DB column
DOM\ORM\Storage\InMemoryFilesystemAdapter::reset('pagebuilder-runtime');
```

Notes:

- Data is process-local: it survives multiple `StorageService` instantiations within one PHP
  process (console commands, queue workers, a single request with many writes), but disappears
  when the process ends and is never shared across processes.
- No cross-process locking or shared state is provided.
- Good fit: page builders assembling a tree in memory, long-running console/queue jobs, and
  tests that should not touch disk.

## Practical guidance

- Local storage with the built-in lock is suitable for multi-process PHP workloads on a single host.
- Remote storage is still fine for portability and backups, but not as a high-concurrency primary write store unless you add your own locking layer.
- The PHP query cache improves read performance only. It does not provide write coordination or transactional guarantees.
