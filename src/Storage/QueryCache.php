<?php

declare(strict_types=1);

namespace DOM\ORM\Storage;

use function DOM\ORM\getConfig;

/**
 * Read-only query cache backed by opcache-friendly PHP files.
 *
 * Format 2 (current) splits the cache into two files, both derived from the
 * configured cache_path:
 *
 *   payload  (cache_path)            : all entity data, no indexes
 *       <?php return [
 *           'user' => [
 *               'uuid1' => ['@id' => 'uuid1', '@type' => 'user', 'name' => 'Alice', ...],
 *           ],
 *           '__meta' => ['format' => 2, 'hash' => '...', ...],
 *       ];
 *
 *   index    (cache_path with "-index" before the extension, e.g. cache-index.php)
 *       <?php return [
 *           'user' => [
 *               'name' => ['Alice' => ['uuid1'], 'Bob' => ['uuid2']],
 *               'city' => ['Berlin' => ['uuid1', 'uuid3'], ...],
 *           ],
 *           '__meta' => ['format' => 2, 'hash' => '...', 'encrypted' => [...], ...],
 *       ];
 *
 * The index holds per-field inverted indexes (non-encrypted fields only), so
 * findBy()/findOneBy() can resolve candidate IDs — and answer non-matching
 * criteria with an empty result — without loading the payload file (or the
 * XML data file). Legacy format 1 (single file with '__idx' inside the payload)
 * is detected on load and rebuilt exactly once.
 *
 * Item arrays match the shape produced by SchemaDecoder::decodeItem(), so they
 * can be fed directly back into SchemaDenormalizer after wrapping:
 *   ['data' => [['item-{id}' => $itemData]]]
 */
final class QueryCache
{
    private const FORMAT = 2;

    /**
     * Returns the configured cache file path, or null if cache is not configured.
     */
    public static function getCachePath(): ?string
    {
        return getConfig()->get('dom-orm.cache_path');
    }

    /**
     * Returns the path of the inverted-index file, or null if cache is not configured.
     */
    public static function getIndexPath(): ?string
    {
        $path = self::getCachePath();
        if ($path === null) {
            return null;
        }

        $dir = \dirname($path);
        $name = \basename($path);
        if (\str_ends_with($name, '.php')) {
            $name = \substr($name, 0, -4) . '-index.php';
        } else {
            $name .= '-index.php';
        }

        return $dir . \DIRECTORY_SEPARATOR . $name;
    }

    /**
     * Returns the configured cache strategy: 'manual' (default) or 'on_persist'.
     */
    public static function getStrategy(): string
    {
        return getConfig()->get('dom-orm.cache_strategy');
    }

    /**
     * Returns true when a cache_path is configured (regardless of whether the file exists).
     */
    public static function isEnabled(): bool
    {
        return self::getCachePath() !== null;
    }

    /**
     * Returns true when the payload cache file exists on disk.
     */
    public static function exists(): bool
    {
        $path = self::getCachePath();

        return $path !== null && \file_exists($path);
    }

    /**
     * Loads and returns the payload cache array, or null if the file does not exist.
     *
     * @return array<string, array<string, array<string, mixed>>>|null
     */
    public static function load(): ?array
    {
        $path = self::getCachePath();
        if ($path === null || !\file_exists($path)) {
            return null;
        }

        /** @var array<string, mixed> $cache */
        $cache = require $path;

        // If the source data file changed since this cache was written (for example
        // a cron job replaced data.xml), rebuild from the current data so reads stay
        // correct while keeping subsequent requests served from the fresh cache.
        if (self::isStale($cache)) {
            self::build();

            /** @var array<string, mixed> $cache */
            $cache = require $path;
        }

        return $cache;
    }

    /**
     * Loads and returns the inverted-index array, or null if the file does not exist.
     *
     * The index is small compared to the payload: findBy()/findOneBy() use it to
     * resolve candidate IDs (or prove a non-match) without loading the payload.
     *
     * @return array<string, array<string, array<string, list<string>>>>|null
     */
    public static function loadIndex(): ?array
    {
        $path = self::getIndexPath();
        if ($path === null || !\file_exists($path)) {
            return null;
        }

        /** @var array<string, mixed> $index */
        $index = require $path;

        if (self::isStale($index)) {
            self::build();

            /** @var array<string, mixed> $index */
            $index = require $path;
        }

        return $index;
    }

    /**
     * Scans the XML data file and writes fresh payload + index cache files.
     *
     * The generated files are plain PHP return statements, making them eligible
     * for opcache compilation on first load.
     */
    public static function build(): void
    {
        $storage = StorageService::fromConfig();

        $xml = $storage->read();
        $dom = new \DOMDocument('1.0', 'UTF-8');
        if (!$dom->loadXML($xml)) {
            throw new \RuntimeException('Failed to parse the XML data file.');
        }

        self::buildFromDom($dom);
    }

    public static function buildFromDom(\DOMDocument $dom): void
    {
        $path = self::requireCachePath();
        $indexPath = self::getIndexPath();
        if ($indexPath === null) {
            throw new \RuntimeException('Could not derive the cache index path from the configured cache_path.');
        }

        $payload = [];
        $index = [];
        $encrypted = [];

        $xpath = new \DOMXPath($dom);
        /** @var \DOMNodeList<\DOMNode> $items */
        $items = $xpath->query('//item') ?: new \DOMNodeList();

        foreach ($items as $item) {
            if (!$item instanceof \DOMElement) {
                continue;
            }

            $id = $item->getAttribute('id');
            $type = $item->getAttribute('type');

            if ($id === '' || $type === '') {
                continue;
            }

            $itemData = self::decodeItemElement($item);
            $payload[$type][$id] = $itemData;

            // Build inverted index for every non-encrypted, non-group fragment.
            foreach ($itemData as $field => $value) {
                if ($field === '@id' || $field === '@type') {
                    continue;
                }
                if (\is_array($value)) {
                    // Encrypted values are ['value' => ..., 'searchable-hash' => ...];
                    // group collections are lists of items. Neither is indexed;
                    // encrypted fields are remembered so findBy() knows to fall
                    // back to XPath (searchable-hash matching) for them.
                    if (isset($value['value'])) {
                        $encrypted[$type][$field] = true;
                    }

                    continue;
                }
                $index[$type][$field][(string)$value][] = $id;
            }
        }

        // Record a fingerprint of the source data file so any external edit
        // to it (such as a cron-swapped data.xml) is detected on the next read
        // and the cache is rebuilt automatically instead of serving stale data.
        $fingerprint = StorageService::fromConfig()->fingerprint();
        $meta = [
            'format' => self::FORMAT,
            'data_file' => (string)getConfig()->get('dom-orm.filename'),
        ];
        if ($fingerprint !== null) {
            $meta['size'] = $fingerprint['size'];
            $meta['mtime'] = $fingerprint['mtime'];
            $meta['hash'] = $fingerprint['hash'];
        }

        $payload['__meta'] = $meta;
        $indexMeta = $meta;
        $indexMeta['encrypted'] = $encrypted;
        $index['__meta'] = $indexMeta;

        self::writeAtomic($path, "<?php\n\nreturn " . \var_export($payload, true) . ";\n");
        self::writeAtomic($indexPath, "<?php\n\nreturn " . \var_export($index, true) . ";\n");
    }

    /**
     * Deletes the cache files (payload + index) if they exist.
     */
    public static function flush(): void
    {
        foreach ([self::getCachePath(), self::getIndexPath()] as $path) {
            if ($path !== null && \file_exists($path)) {
                \unlink($path);
            }
        }
    }

    // -----------------------------------------------------------------------
    // Query helpers — return arrays shaped for SchemaDenormalizer
    // -----------------------------------------------------------------------

    /**
     * Find a single item by entity type and ID.
     *
     * @param array<string, array<string, array<string, mixed>>> $cache
     * @return array{data: list<array<string, array<string, mixed>>>}|null
     */
    public static function findById(array $cache, string $type, string $id): ?array
    {
        $typeData = $cache[$type] ?? null;
        if ($typeData === null) {
            return null;
        }

        $itemData = $typeData[$id] ?? null;
        if ($itemData === null) {
            return null;
        }

        return [
            'data' => [[
                'item-' . $id => $itemData,
            ]],
        ];
    }

    /**
     * Return all items for an entity type.
     *
     * @param array<string, array<string, array<string, mixed>>> $cache
     * @return array{data: list<array<string, array<string, mixed>>>}|null
     */
    public static function findAll(array $cache, string $type): ?array
    {
        $typeData = $cache[$type] ?? null;
        if ($typeData === null || \count($typeData) === 0) {
            return null;
        }

        $data = [];
        foreach ($typeData as $id => $itemData) {
            // Skip the internal index bucket (legacy format 1 payloads).
            if ($id === '__idx') {
                continue;
            }
            $data[] = [
                'item-' . $id => $itemData,
            ];
        }

        if (\count($data) === 0) {
            return null;
        }

        return [
            'data' => $data,
        ];
    }

    /**
     * Resolve the candidate IDs for a set of equality criteria using the
     * inverted index alone — no payload (and no XML) access needed.
     *
     * Returns:
     *  - a (possibly empty) list of candidate IDs, or
     *  - null when the index cannot answer (an encrypted criterion is present)
     *    and the caller must fall back to XPath.
     *
     * @param array<string, array<string, array<string, list<string>>>> $index
     * @param array<string, scalar> $criteria
     * @return list<string>|null
     */
    public static function findByIndex(array $index, string $type, array $criteria): ?array
    {
        $typeIdx = $index[$type] ?? null;
        if ($typeIdx === null) {
            // The index is fresh (stale files are rebuilt on load): a missing
            // type means the data file holds no items of this type.
            return [];
        }

        /** @var array<string, bool> $encrypted */
        $encrypted = $index['__meta']['encrypted'][$type] ?? [];

        // Handle id as a special key — no index needed.
        $idFilter = null;
        if (isset($criteria['id'])) {
            $idFilter = (string)$criteria['id'];
            unset($criteria['id']);
        }

        // Start with null = "all IDs" and narrow down with each criterion.
        $candidateIds = null;

        foreach ($criteria as $field => $value) {
            $strValue = (string)$value;

            if (!isset($typeIdx[$field])) {
                // Not indexed: encrypted fields need XPath (searchable-hash);
                // anything else (unknown field, group collection) matches nothing.
                if (isset($encrypted[$field])) {
                    return null;
                }

                return [];
            }

            // O(1) value lookup in the inverted index.
            $matchingIds = $typeIdx[$field][$strValue] ?? [];
            if (\count($matchingIds) === 0) {
                return [];
            }

            // Intersect with the running candidate set.
            if ($candidateIds === null) {
                $candidateIds = \array_flip($matchingIds);
            } else {
                $candidateIds = \array_intersect_key($candidateIds, \array_flip($matchingIds));
                if (\count($candidateIds) === 0) {
                    return [];
                }
            }
        }

        // Apply id filter.
        if ($idFilter !== null) {
            if ($candidateIds === null) {
                $candidateIds = [
                    $idFilter => true,
                ];
            } elseif (!isset($candidateIds[$idFilter])) {
                return [];
            } else {
                $candidateIds = [
                    $idFilter => true,
                ];
            }
        }

        if ($candidateIds === null) {
            // No criteria at all — degenerate case: every id of the type.
            $all = [];
            foreach ($typeIdx as $fieldBuckets) {
                foreach ($fieldBuckets as $ids) {
                    foreach ($ids as $id) {
                        $all[$id] = true;
                    }
                }
            }

            return \array_keys($all);
        }

        return \array_keys($candidateIds);
    }

    /**
     * Materialise the payload entries for a set of IDs (in the given order),
     * shaped for SchemaDenormalizer. Missing IDs are skipped.
     *
     * @param array<string, array<string, array<string, mixed>>> $cache
     * @param list<string> $ids
     * @return array{data: list<array<string, array<string, mixed>>>}
     */
    public static function findByIds(array $cache, string $type, array $ids): array
    {
        $typeData = $cache[$type] ?? null;
        if ($typeData === null) {
            return [
                'data' => [],
            ];
        }

        $data = [];
        foreach ($ids as $id) {
            $itemData = $typeData[$id] ?? null;
            if ($itemData === null) {
                continue;
            }
            $data[] = [
                'item-' . $id => $itemData,
            ];
        }

        return [
            'data' => $data,
        ];
    }

    /**
     * Find items matching all criteria using the per-field inverted index where possible.
     *
     * Kept for backwards compatibility with format 1 (monolithic) cache arrays
     * that still carry the '__idx' bucket. Format 2 payloads hold no index:
     * callers should use findByIndex() + findByIds() instead.
     *
     * @param array<string, array<string, array<string, mixed>>> $cache
     * @param array<string, scalar> $criteria
     * @return array{data: list<array<string, array<string, mixed>>>}|null  null = fall back to XML
     */
    public static function findBy(array $cache, string $type, array $criteria): ?array
    {
        $typeData = $cache[$type] ?? null;
        if ($typeData === null) {
            return null;
        }

        // Format 2 payloads carry no index — this method cannot answer.
        if (!isset($typeData['__idx'])) {
            return null;
        }

        // Handle id as a special key — direct hash lookup, no index needed.
        $idFilter = null;
        if (isset($criteria['id'])) {
            $idFilter = (string)$criteria['id'];
            unset($criteria['id']);
        }

        // Determine the candidate ID set via the inverted index.
        // Start with null = "all IDs" and narrow down with each criterion.
        $candidateIds = null; // null means "not yet restricted"

        $idx = $typeData['__idx'];

        foreach ($criteria as $field => $value) {
            $strValue = (string)$value;

            // Check whether this field is indexed at all.
            if (!isset($idx[$field])) {
                // Field not in index — could be encrypted or simply missing.
                // Peek at the first item to detect encrypted fields.
                foreach ($typeData as $peekId => $peekData) {
                    if ($peekId === '__idx') {
                        continue;
                    }
                    $peekField = $peekData[$field] ?? null;
                    if (\is_array($peekField)) {
                        // Encrypted — signal caller to use XPath.
                        return null;
                    }

                    // Non-encrypted but value not in index = no matches.
                    return [
                        'data' => [],
                    ];
                }

                // Empty type.
                return [
                    'data' => [],
                ];
            }

            // O(1) value lookup in the inverted index.
            $matchingIds = $idx[$field][$strValue] ?? [];
            if (\count($matchingIds) === 0) {
                return [
                    'data' => [],
                ];
            }

            // Intersect with the running candidate set.
            if ($candidateIds === null) {
                $candidateIds = \array_flip($matchingIds);
            } else {
                $candidateIds = \array_intersect_key($candidateIds, \array_flip($matchingIds));
                if (\count($candidateIds) === 0) {
                    return [
                        'data' => [],
                    ];
                }
            }
        }

        // Apply id filter.
        if ($idFilter !== null) {
            if ($candidateIds === null) {
                $candidateIds = [
                    $idFilter => true,
                ];
            } elseif (!isset($candidateIds[$idFilter])) {
                return [
                    'data' => [],
                ];
            } else {
                $candidateIds = [
                    $idFilter => true,
                ];
            }
        }

        // Materialise the result from the candidate set.
        $data = [];
        $source = ($candidateIds !== null) ? \array_keys($candidateIds) : \array_keys($typeData);
        foreach ($source as $id) {
            if ($id === '__idx') {
                continue;
            }
            $itemData = $typeData[$id] ?? null;
            if ($itemData === null) {
                continue;
            }
            $data[] = [
                'item-' . $id => $itemData,
            ];
        }

        if (\count($data) === 0) {
            return [
                'data' => [],
            ];
        }

        return [
            'data' => $data,
        ];
    }

    /**
     * Returns true when the cache was built from a different version of the data
     * file than the one currently on disk, or when it uses a legacy format.
     * A missing/legacy fingerprint (no stored hash) is treated as stale so it
     * is rebuilt exactly once.
     *
     * Stat-first fast path: when the stored size + mtime match the current data
     * file, the file is unchanged and the expensive full-file SHA-256
     * (fingerprint()) is skipped. This turns the per-request staleness check
     * from "read + hash the whole data file" into a single stat() call.
     *
     * Known trade-off: an external rewrite that keeps BOTH the same size and the
     * same mtime (i.e. happens within the same clock second) is not detected by
     * the fast path. The hash fallback below still runs whenever size or mtime
     * differ, so only that exact corner case can serve one stale response.
     *
     * @param array<string, mixed> $cache
     */
    private static function isStale(array $cache): bool
    {
        $stored = $cache['__meta'] ?? null;

        if (!\is_array($stored) || !isset($stored['hash']) || !\is_string($stored['hash'])) {
            return true;
        }

        // Format 2 = split payload/index files. Anything older is rebuilt once.
        if (($stored['format'] ?? 1) !== self::FORMAT) {
            return true;
        }

        // Stat-first: unchanged size + mtime => unchanged file, skip the hash.
        if (isset($stored['size'], $stored['mtime'])) {
            $stat = StorageService::fromConfig()->stat();
            if (
                $stat !== null
                && (int)$stored['size'] === $stat['size']
                && (int)$stored['mtime'] === (int)$stat['mtime']
            ) {
                return false;
            }
        }

        $current = self::currentDataFingerprint();
        if ($current === null) {
            // Cannot fingerprint the data file — prefer a fresh read over serving
            // potentially stale content.
            return true;
        }

        return $stored['hash'] !== $current['hash'];
    }

    /**
     * Fingerprint of the current data file, or null when it cannot be read.
     *
     * @return array{size: int, mtime: ?int, hash: string}|null
     */
    private static function currentDataFingerprint(): ?array
    {
        try {
            return StorageService::fromConfig()->fingerprint();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Writes $contents to $path atomically (temp file + rename) so readers
     * never observe a partially written cache file.
     */
    private static function writeAtomic(string $path, string $contents): void
    {
        $dir = \dirname($path);
        if (!\is_dir($dir) && !\mkdir($dir, 0755, true) && !\is_dir($dir)) {
            throw new \RuntimeException(\sprintf('Failed to create cache directory: %s', $dir));
        }

        $tmp = $path . '.tmp-' . \getmypid();
        if (\file_put_contents($tmp, $contents) === false) {
            throw new \RuntimeException(\sprintf('Failed to write cache file: %s', $path));
        }

        if (!\rename($tmp, $path)) {
            // rename() over an existing file fails on some platforms (e.g. Windows).
            if (!\copy($tmp, $path) || !\unlink($tmp)) {
                @\unlink($tmp);

                throw new \RuntimeException(\sprintf('Failed to write cache file: %s', $path));
            }
        }
    }

    /**
     * Decode one <item> element to the same associative shape produced by SchemaDecoder::decodeItem().
     *
     * @return array<string, mixed>
     */
    private static function decodeItemElement(\DOMElement $item): array
    {
        $itemData = [
            '@id' => $item->getAttribute('id'),
            '@type' => $item->getAttribute('type'),
        ];

        foreach ($item->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }

            if ($child->nodeName === 'fragment') {
                $name = $child->getAttribute('name');
                // Preserve searchable-hash attribute when present (encrypted fields)
                $hash = $child->getAttribute('searchable-hash');
                $value = $child->nodeValue;

                if ($hash !== '') {
                    $itemData[$name] = [
                        'value' => $value,
                        'searchable-hash' => $hash,
                    ];
                } else {
                    $itemData[$name] = $value;
                }

                continue;
            }

            if ($child->nodeName === 'group') {
                $groupType = $child->getAttribute('type');
                if ($groupType === '') {
                    continue;
                }

                $groupItems = [];
                foreach ($child->childNodes as $groupChild) {
                    if (!$groupChild instanceof \DOMElement || $groupChild->nodeName !== 'item') {
                        continue;
                    }

                    $groupId = $groupChild->getAttribute('id');
                    if ($groupId === '') {
                        continue;
                    }

                    $groupItems[] = [
                        'item-' . $groupId => self::decodeItemElement($groupChild),
                    ];
                }

                $itemData[$groupType] = $groupItems;
            }
        }

        return $itemData;
    }

    private static function requireCachePath(): string
    {
        $path = self::getCachePath();
        if ($path === null) {
            throw new \RuntimeException('cache_path is not configured. Set it in dom-orm.php to use the query cache.');
        }

        return $path;
    }
}
