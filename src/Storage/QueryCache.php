<?php

declare(strict_types=1);

namespace DOM\ORM\Storage;

use function DOM\ORM\getConfig;

/**
 * Read-only query cache backed by a PHP opcache-friendly file.
 *
 * Cache format (written to cache_path):
 *
 *   <?php return [
 *       'user' => [
 *           '__idx' => [
 *               'name' => ['Alice' => ['uuid1'], 'Bob' => ['uuid2']],
 *               'city' => ['Berlin' => ['uuid1', 'uuid3'], ...],
 *           ],
 *           'uuid1' => ['@id' => 'uuid1', '@type' => 'user', 'name' => 'Alice', ...],
 *       ],
 *   ];
 *
 * The '__idx' sub-key holds per-field inverted indexes (non-encrypted fields only).
 * The inner item arrays match the shape produced by SchemaDecoder::decodeItem(),
 * so they can be fed directly back into SchemaDenormalizer after wrapping:
 *   ['data' => [['item-{id}' => $itemData]]]
 */
final class QueryCache
{
    /**
     * Returns the configured cache file path, or null if cache is not configured.
     */
    public static function getCachePath(): ?string
    {
        return getConfig()->get('dom-orm.cache_path');
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
     * Returns true when the cache file exists on disk.
     */
    public static function exists(): bool
    {
        $path = self::getCachePath();

        return $path !== null && \file_exists($path);
    }

    /**
     * Loads and returns the full cache array, or null if the file does not exist.
     *
     * @return array<string, array<string, array<string, mixed>>>|null
     */
    public static function load(): ?array
    {
        $path = self::getCachePath();
        if ($path === null || !\file_exists($path)) {
            return null;
        }

        /** @var array<string, array<string, array<string, mixed>>> */
        return require $path;
    }

    /**
     * Scans the XML data file and writes a fresh PHP cache file to cache_path.
     *
     * The generated file is a plain PHP return statement, making it eligible for
     * opcache compilation on first load.
     */
    public static function build(): void
    {
        $path = self::getCachePath();
        if ($path === null) {
            throw new \RuntimeException('cache_path is not configured. Set it in dom-orm.php to use the query cache.');
        }

        $storage = StorageService::fromConfig();

        $xml = $storage->read();
        $dom = new \DOMDocument('1.0', 'UTF-8');
        if (!$dom->loadXML($xml)) {
            throw new \RuntimeException('Failed to parse the XML data file.');
        }

        $cache = [];
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

            $itemData = [
                '@id' => $id,
                '@type' => $type,
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
                }
            }

            $cache[$type][$id] = $itemData;

            // Build inverted index for every non-encrypted, non-reserved fragment.
            foreach ($itemData as $field => $value) {
                if ($field === '@id' || $field === '@type') {
                    continue;
                }
                // Encrypted fields are arrays — skip them.
                if (\is_array($value)) {
                    continue;
                }
                $cache[$type]['__idx'][$field][(string)$value][] = $id;
            }
        }

        $dir = \dirname($path);
        if (!\is_dir($dir)) {
            \mkdir($dir, 0755, true);
        }

        $exported = \var_export($cache, true);
        \file_put_contents($path, "<?php\n\nreturn {$exported};\n");
    }

    /**
     * Deletes the cache file if it exists.
     */
    public static function flush(): void
    {
        $path = self::getCachePath();
        if ($path !== null && \file_exists($path)) {
            \unlink($path);
        }
    }

    // -----------------------------------------------------------------------
    // Query helpers — return arrays shaped for SchemaDenormalizer
    // -----------------------------------------------------------------------

    /**
     * Find a single item by entity type and ID.
     *
     * @param array<string, array<string, mixed>> $cache
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
     * @param array<string, array<string, mixed>> $cache
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
            // Skip the internal index bucket.
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
     * Find items matching all criteria using the per-field inverted index where possible.
     *
     * Strategy:
     *  1. For each non-encrypted criterion, look up the '__idx' sub-key to get a candidate
     *     set of IDs (O(1) hash lookup).  Intersect candidate sets across all criteria.
     *  2. If any criterion maps to an encrypted field (array value), return null so the
     *     caller falls back to XPath which can match on searchable-hash.
     *  3. If a criterion field has no index entry at all (no matching value), return an
     *     empty-result immediately without scanning any items.
     *
     * @param array<string, array<string, mixed>> $cache
     * @param array<string, scalar> $criteria
     * @return array{data: list<array<string, array<string, mixed>>>}|null  null = fall back to XML
     */
    public static function findBy(array $cache, string $type, array $criteria): ?array
    {
        $typeData = $cache[$type] ?? null;
        if ($typeData === null) {
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

        $idx = $typeData['__idx'] ?? [];

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
}
