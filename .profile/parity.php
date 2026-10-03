<?php

declare(strict_types=1);

/**
 * Parity check: hydrate all cached entities via the compiled mapper vs the
 * reflection path and print a canonical hash. Run twice (compiled vs
 * reflection) and the hashes must match.
 *
 * Usage: XDEBUG_MODE=off php .profile/parity.php <auto|compiled|reflection>
 */

$profileDir = __DIR__;
putenv('DOM_ORM_FLYSYSTEM_ADAPTER=League\Flysystem\Local\LocalFilesystemAdapter');
putenv('DOM_ORM_FLYSYSTEM_LOCATION=' . $profileDir . '/data');
putenv('DOM_ORM_FILENAME=data.xml');
putenv('DOM_ORM_CACHE_PATH=' . $profileDir . '/data/cache.php');
putenv('DOM_ORM_CACHE_STRATEGY=manual');
putenv('DOM_ORM_VERSIONING=false');
putenv('DOM_ORM_HYDRATOR=' . ($argv[1] ?? 'auto'));

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../benchmarks/compare/BenchUser.php';
require __DIR__ . '/ProfiledRepository.php';

use DOM\ORM\Storage\QueryCache;

$entityClass = BenchUser::class;
$entityType = 'bench_user';

$repo = new ProfiledRepository($entityClass);
$serializer = $repo->serializer();

$cache = QueryCache::load();
$array = QueryCache::findAll($cache, $entityType);
$entities = $serializer->denormalize($array, $entityClass);

$out = [];
foreach ($entities as $e) {
    $out[] = $e->getId() . '|' . $e->getName() . '|' . $e->getEmail() . '|' . $e->getCity() . '|' . ($e->getCreatedAt() ? $e->getCreatedAt()->format('c') : '');
}

echo count($entities) . " entities\n";
echo hash('sha256', implode("\n", $out)) . "\n";
