# Performance

## No overhead
DOM-ORM can actually be faster than a regular database because it operates as an in-memory data structure for read operations. We are talking microseconds instead of milliseconds. This is achieved by eliminating network latency and disk I/O.

## Hash Maps and Query Cache
Under the hood, every DOM-ORM Repository method makes use of a pre-compiled in-memory PHP hash map. 
This array is serialized to a .php file that PHP's opcache can precompile. 
This greatly improves lookup performance because costly XPath queries are highly reduced. 

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

### Configuration
In your `config/dom-orm.php` file, there are two settings to control this feature:
```php
<?php return [
  'dom-orm' => [
    'cache_path' => null, # 'path/to/file.php' or null to use default location.
    'cache_strategy' => 'on_persist', # on_persist: automatically or manual: requires you to use the below CLI commands.
  ];
```
If you are writing a lot of data to the database (XML file), it can be beneficial not to write the file on every insert. That's why this setting exists.
You can also trigger a cache rebuild programmatically via `QueryCache::build()` if needed. When cache and XML are not in sync, you will get different results if the cache file is stale. Nonetheless, you are in control.  

### CLI Command
```bash
./dom-orm cache:build
./dom-orm cache:flush
```

## Batch Inserts
Persisting many entities can be slow, as the XML file has to be rewritten each time `persist($entity)` is called. To address this, there is a `persistBatch($entities)` method, which writes to the XML file only once.