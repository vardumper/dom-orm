<?php

declare(strict_types=1);

namespace DOM\ORM\Storage;

use DOM\ORM\{Encryption\EncryptionService, Entity\EntityInterface, Mapping\EntityClassResolver};
use function DOM\ORM\getConfig;

/**
 * Runtime loader/dispatcher for compiled hydrator mappers.
 *
 * For a given entity type, Hydrator::get() returns a hydration callable
 * (backed by the generated dom_orm_hydrate_{type}() function) or null when no
 * valid mapper is available — in which case the denormalizer falls back to the
 * reflection path. The mapper is (re)generated on demand when the file is
 * missing or its signature hash no longer matches the entity class, so the
 * compiled path is self-healing and a pure speedup (it never breaks hydration).
 */
final class Hydrator
{
    /**
     * @var array<string, bool>
     */
    private static array $loaded = [];

    private static ?string $generatedDir = null;

    private static bool $functionsLoaded = false;

    /**
     * Directory holding generated mapper files (derived from the flysystem
     * location, i.e. the storage dir).
     */
    public static function getGeneratedDir(): string
    {
        if (self::$generatedDir === null) {
            $location = getConfig()->get('dom-orm.flysystem.config.location');
            self::$generatedDir = \rtrim($location, '/\\') . \DIRECTORY_SEPARATOR . 'generated';
        }

        return self::$generatedDir;
    }

    public static function pathFor(string $entityType): string
    {
        return self::getGeneratedDir() . \DIRECTORY_SEPARATOR . $entityType . '.php';
    }

    /**
     * Load the shared mapper helper functions once.
     */
    public static function loadFunctions(): void
    {
        if (self::$functionsLoaded) {
            return;
        }
        self::$functionsLoaded = true;
        require_once __DIR__ . '/HydratorFunctions.php';
    }

    /**
     * Return a hydration callable for the entity type, or null to fall back to
     * reflection. The callable takes a row (['item-{id}' => $itemData]) and
     * returns an EntityInterface.
     */
    public static function get(string $entityType, ?EncryptionService $enc, callable $fallback): ?callable
    {
        self::loadFunctions();

        $fnName = 'dom_orm_hydrate_' . $entityType;

        if (isset(self::$loaded[$entityType])) {
            return \function_exists($fnName)
                ? static fn (array $row): EntityInterface => $fnName($row, $enc, $fallback)
                : null;
        }

        $class = EntityClassResolver::classForEntityType($entityType);
        if ($class === null) {
            return null;
        }

        $path = self::pathFor($entityType);
        $currentHash = HydratorGenerator::signatureHash($class, HydratorGenerator::metaForClass($class));

        if (self::readFileHash($path) !== $currentHash) {
            /** Missing or stale: (re)generate, then re-check. */
            try {
                HydratorGenerator::generateForClass($class, self::getGeneratedDir());
            } catch (\Throwable) {
                return null;
            }
            if (self::readFileHash($path) !== $currentHash) {
                return null;
            }
        }

        try {
            require $path; /** defines the global mapper function */
        } catch (\Throwable) {
            return null;
        }

        self::$loaded[$entityType] = true;

        return \function_exists($fnName)
            ? static fn (array $row): EntityInterface => $fnName($row, $enc, $fallback)
            : null;
    }

    /**
     * Read the signature hash from a generated file's header without executing
     * it (so the global function is not defined before we know it is current).
     */
    private static function readFileHash(string $path): ?string
    {
        if (!\is_file($path)) {
            return null;
        }

        $head = \file_get_contents($path, false, null, 0, 1024);
        if ($head === false) {
            return null;
        }

        if (\preg_match('/hash=([a-f0-9]{8,})/', $head, $m)) {
            return $m[1];
        }

        return null;
    }
}
