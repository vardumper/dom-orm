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
 *           'uuid1' => ['@id' => 'uuid1', '@type' => 'user', 'name' => 'Alice', ...],
 *       ],
 *   ];
 *
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
     * @param array<string, array<string, array<string, mixed>>> $cache
     * @return array{data: list<array<string, array<string, mixed>>>}|null
     */
    public static function findById(array $cache, string $type, string $id): ?array
    {
        $itemData = $cache[$type][$id] ?? null;
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
        $items = $cache[$type] ?? null;
        if ($items === null || \count($items) === 0) {
            return null;
        }

        $data = [];
        foreach ($items as $id => $itemData) {
            $data[] = [
                'item-' . $id => $itemData,
            ];
        }

        return [
            'data' => $data,
        ];
    }

    /**
     * Find items matching all criteria (equality, non-sensitive fields only).
     *
     * Criteria values are compared as strings against cached fragment values.
     * If a criterion key maps to an encrypted fragment (array with 'searchable-hash'),
     * this method falls back to returning null so the caller can use XPath instead.
     *
     * @param array<string, array<string, array<string, mixed>>> $cache
     * @param array<string, scalar> $criteria
     * @return array{data: list<array<string, array<string, mixed>>>}|null null = fall back to XML
     */
    public static function findBy(array $cache, string $type, array $criteria): ?array
    {
        $items = $cache[$type] ?? null;
        if ($items === null) {
            return null;
        }

        // Handle id as a special key (maps to @id in the item data)
        $idFilter = null;
        if (isset($criteria['id'])) {
            $idFilter = (string)$criteria['id'];
            unset($criteria['id']);
        }

        $data = [];
        foreach ($items as $id => $itemData) {
            if ($idFilter !== null && $id !== $idFilter) {
                continue;
            }

            $match = true;
            foreach ($criteria as $key => $value) {
                $cached = $itemData[$key] ?? null;

                // If the value is an array it's an encrypted field — signal caller to fall back.
                if (\is_array($cached)) {
                    return null;
                }

                if ((string)$cached !== (string)$value) {
                    $match = false;
                    break;
                }
            }
            if ($match) {
                $data[] = [
                    'item-' . $id => $itemData,
                ];
            }
        }

        if (\count($data) === 0) {
            return null;
        }

        return [
            'data' => $data,
        ];
    }
}
