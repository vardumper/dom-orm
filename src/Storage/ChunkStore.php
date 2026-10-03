<?php

declare(strict_types=1);

namespace DOM\ORM\Storage;

/**
 * Chunked query-cache store (Phase 2).
 *
 * The payload is split into 256 shard files (keyed by the first two hex chars
 * of the entity id) plus a small meta file and the inverted-index file. Point
 * lookups load a single shard (~1/256th of the data) instead of the whole
 * payload, which is what makes find($id) fast. Full scans load every shard.
 *
 * Layout (under {cache_path}/../cache/):
 *   meta.php    - the __meta fingerprint (source size/mtime/hash)
 *   index.php   - the inverted index (same shape as the old cache-index.php)
 *   {00..ff}.php - shard payloads: {type => {id => itemData}}
 */
final class ChunkStore
{
    public const SHARDS = 256;

    private string $dir;

    /**
     * @var array<string, array<string, array<string, array<string, mixed>>>>
     */
    private array $shardCache = [];

    /**
     * @var array<string, mixed>|null
     */
    private ?array $meta = null;

    /**
     * @var array<string, mixed>|null
     */
    private ?array $index = null;

    public function __construct(string $dir)
    {
        $this->dir = $dir;
    }

    public static function dirFor(string $cachePath): string
    {
        return \dirname($cachePath) . \DIRECTORY_SEPARATOR . 'cache';
    }

    /**
     * Shard key for an id: the low byte of crc32, as two hex chars (256 values).
     *
     * A plain substr($id, 0, 2) is not usable: DOM-ORM ids are hex-encoded
     * ASCII, so their first two chars only span 16 values. crc32 spreads any
     * id distribution across all 256 shards.
     */
    public static function shardFor(string $id): string
    {
        return \str_pad(\dechex(\crc32($id) & 0xff), 2, '0', \STR_PAD_LEFT);
    }

    /**
     * A cache "exists" when its directory is present. A missing meta (e.g. a
     * legacy cache predating the fingerprint feature) is not an absence — it is
     * staleness, so load() rebuilds rather than returning null.
     */
    public function exists(): bool
    {
        return \is_dir($this->dir);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function meta(): ?array
    {
        if ($this->meta === null) {
            $path = $this->dir . \DIRECTORY_SEPARATOR . 'meta.php';
            $this->meta = \is_file($path) ? require $path : null;
        }

        return $this->meta;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function index(): ?array
    {
        if ($this->index === null) {
            $path = $this->dir . \DIRECTORY_SEPARATOR . 'index.php';
            $this->index = \is_file($path) ? require $path : null;
        }

        return $this->index;
    }

    /**
     * Ordered id list for a type (from ids.php) — small, no payload load.
     *
     * @return list<string>
     */
    public function ids(string $type): array
    {
        $path = $this->dir . \DIRECTORY_SEPARATOR . 'ids.php';
        static $idsCache = [];
        if (!isset($idsCache[$this->dir])) {
            $idsCache[$this->dir] = \is_file($path) ? require $path : [];
        }

        return $idsCache[$this->dir][$type] ?? [];
    }

    /**
     * Load one shard (memoised). Returns [] for a missing shard.
     *
     * @return array<string, array<string, array<string, mixed>>>
     */
    public function loadShard(string $shard): array
    {
        if (!isset($this->shardCache[$shard])) {
            $path = $this->dir . \DIRECTORY_SEPARATOR . $shard . '.php';
            $this->shardCache[$shard] = \is_file($path) ? require $path : [];
        }

        return $this->shardCache[$shard];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findById(string $type, string $id): ?array
    {
        $shard = self::shardFor($id);
        $items = $this->loadShard($shard);

        return $items[$type][$id] ?? null;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function findAll(string $type): array
    {
        /** Full scans load the single monolith payload (one require) instead of */
        /** 256 shard requires — the shards exist for point lookups. */
        $path = $this->dir . \DIRECTORY_SEPARATOR . 'all.php';
        static $allCache = [];
        if (!isset($allCache[$this->dir])) {
            $allCache[$this->dir] = \is_file($path) ? require $path : [];
        }
        $payload = $allCache[$this->dir];

        return $payload[$type] ?? [];
    }

    /**
     * @param list<string> $ids
     * @return array<string, array<string, mixed>>
     */
    public function findByIds(string $type, array $ids): array
    {
        $result = [];
        $shards = [];
        foreach ($ids as $id) {
            $shards[] = self::shardFor($id);
        }

        foreach (\array_unique($shards) as $shard) {
            $shardData = $this->loadShard($shard);
            if (!isset($shardData[$type])) {
                continue;
            }
            foreach ($ids as $id) {
                if (self::shardFor($id) === $shard && isset($shardData[$type][$id])) {
                    $result[$id] = $shardData[$type][$id];
                }
            }
        }

        return $result;
    }

    /**
     * Write the chunked cache from a built payload + index.
     *
     * The payload may carry a '__meta' bucket (fingerprint); it is extracted
     * and written as meta.php, the rest becomes all.php + shards.
     *
     * @param array<string, array<string, array<string, mixed>>|array<string, mixed>> $payload
     * @param array<string, mixed> $index
     */
    public static function build(string $dir, array $payload, array $index): void
    {
        self::clearDir($dir);
        \mkdir($dir, 0o775, true);

        $meta = $payload['__meta'] ?? [];
        unset($payload['__meta']);

        self::writeFile($dir . \DIRECTORY_SEPARATOR . 'meta.php', $meta);
        self::writeFile($dir . \DIRECTORY_SEPARATOR . 'index.php', $index);
        /** Monolith payload for full scans (one require, opcache-friendly). */
        self::writeFile($dir . \DIRECTORY_SEPARATOR . 'all.php', $payload);

        /** Ordered id lists per type (small: ids only) so a lazy collection can */
        /** resolve index -> id and count() in O(1) without loading the payload. */
        $ids = [];
        $shards = [];
        foreach ($payload as $type => $typeData) {
            $ids[$type] = \array_keys($typeData);
            foreach ($typeData as $id => $itemData) {
                $shards[self::shardFor((string)$id)][$type][$id] = $itemData;
            }
        }
        self::writeFile($dir . \DIRECTORY_SEPARATOR . 'ids.php', $ids);

        foreach ($shards as $shard => $shardData) {
            self::writeFile($dir . \DIRECTORY_SEPARATOR . $shard . '.php', $shardData);
        }
    }

    /**
     * Delete the chunked cache directory (and its contents) if it exists.
     */
    public static function clearDir(string $dir): void
    {
        if (!\is_dir($dir)) {
            return;
        }

        foreach (\scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            \unlink($dir . \DIRECTORY_SEPARATOR . $entry);
        }

        \rmdir($dir);
    }

    /**
     * @param array<string, mixed> $data
     */
    private static function writeFile(string $path, array $data): void
    {
        $content = "<?php\n\nreturn " . \var_export($data, true) . ";\n";
        $tmp = $path . '.tmp';
        \file_put_contents($tmp, $content);
        \rename($tmp, $path);
    }
}
