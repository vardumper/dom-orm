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
This is useful when another layer loads XML from a database and persists it back later.

- Data is process-local and disappears when the PHP process ends.
- No cross-process locking or shared state is provided.
- Use your own transaction/lock strategy in the external store (e.g. database row/advisory lock).

## Practical guidance

- Local storage with the built-in lock is suitable for multi-process PHP workloads on a single host.
- Remote storage is still fine for portability and backups, but not as a high-concurrency primary write store unless you add your own locking layer.
- The PHP query cache improves read performance only. It does not provide write coordination or transactional guarantees.
