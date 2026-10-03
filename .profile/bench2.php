<?php

declare(strict_types=1);

/**
 * Clean phase profiler for BOTH read paths, 50 K records.
 *  - no-cache: read + loadXML + xpath + decode + denormalize
 *  - cached:   require(payload) + QueryCache::findAll(array build) + denormalize
 * Times are hrtime(true) nanoseconds, reported as ms (ns / 1e6).
 *
 * Usage: XDEBUG_MODE=off php -d opcache.enable_cli=1 .profile/bench2.php [N=10]
 */

$profileDir = __DIR__;
$dataDir = $profileDir . '/data';

\putenv('DOM_ORM_FLYSYSTEM_ADAPTER=League\\Flysystem\\Local\\LocalFilesystemAdapter');
\putenv('DOM_ORM_FLYSYSTEM_LOCATION=' . $dataDir);
\putenv('DOM_ORM_FILENAME=data.xml');
\putenv('DOM_ORM_CACHE_PATH=' . $dataDir . '/cache.php');
\putenv('DOM_ORM_CACHE_STRATEGY=manual');
\putenv('DOM_ORM_VERSIONING=false');

require $profileDir . '/../vendor/autoload.php';
require $profileDir . '/../benchmarks/compare/BenchUser.php';
require $profileDir . '/ProfiledRepository.php';

use DOM\ORM\Serializer\Encoder\SchemaEncoder;
use DOM\ORM\Storage\QueryCache;

$n = (int)($argv[1] ?? 10);
$entityType = 'bench_user';
$entityClass = BenchUser::class;
$allQuery = \sprintf('//item[@type="%s"]', $entityType);

$ms = static fn (int $ns): float => $ns / 1_000_000;

// ---- Build the cache once (payload + index) --------------------------------
$t = \hrtime(true);
QueryCache::build();
$buildMs = $ms(\hrtime(true) - $t);

// ---- Warm-up (compile/prime) ------------------------------------------------
$repo = new ProfiledRepository($entityClass);
$repo->findAll();
$repo->find('35396637366236393334623937646261');

$sharedSerializer = $repo->serializer();
$sharedStorage = $repo->storage();

// ===========================================================================
// CACHED findAll() — require + array-build + denormalize
// ===========================================================================
$cacheRuns = [];
for ($i = 0; $i < $n; $i++) {
    $t0 = \hrtime(true);
    $cache = QueryCache::load();          // require payload + staleness stat
    $t1 = \hrtime(true);
    $array = QueryCache::findAll($cache, $entityType);  // build $data array
    $t2 = \hrtime(true);
    $sharedSerializer->denormalize($array, $entityClass); // hydration
    $t3 = \hrtime(true);
    $cacheRuns[] = [
        'require' => $ms($t1 - $t0),
        'arraybuild' => $ms($t2 - $t1),
        'denorm' => $ms($t3 - $t2),
        'total' => $ms($t3 - $t0),
    ];
}

// ---- CACHED find($id) — require + findById + denormalize -------------------
$oneRuns = [];
for ($i = 0; $i < $n; $i++) {
    $t0 = \hrtime(true);
    $cache = QueryCache::load();
    $t1 = \hrtime(true);
    $array = QueryCache::findById($cache, $entityType, '35396637366236393334623937646261');
    $t2 = \hrtime(true);
    $sharedSerializer->denormalize($array, $entityClass);
    $t3 = \hrtime(true);
    $oneRuns[] = [
        'require' => $ms($t1 - $t0),
        'findbyid' => $ms($t2 - $t1),
        'denorm' => $ms($t3 - $t2),
        'total' => $ms($t3 - $t0),
    ];
}

// ===========================================================================
// NO-CACHE findAll() — read + loadXML + xpath + decode + denormalize
// ===========================================================================
\putenv('DOM_ORM_CACHE_PATH='); // disable cache for this section

$noRuns = [];
for ($i = 0; $i < $n; $i++) {
    $repo2 = new ProfiledRepository($entityClass);
    $dom = $repo2->getEmptyDom();
    $t0 = \hrtime(true);
    $xml = $repo2->storage()->read();
    $t1 = \hrtime(true);
    $dom->loadXML($xml);
    $t2 = \hrtime(true);
    $xpath = new \DOMXPath($dom);
    $nodes = $xpath->query($allQuery);
    $t3 = \hrtime(true);
    $array = $repo2->serializer()->decode($nodes, SchemaEncoder::FORMAT);
    $t4 = \hrtime(true);
    $repo2->serializer()->denormalize($array, $entityClass);
    $t5 = \hrtime(true);
    $noRuns[] = [
        'read' => $ms($t1 - $t0),
        'loadxml' => $ms($t2 - $t1),
        'xpath' => $ms($t3 - $t2),
        'decode' => $ms($t4 - $t3),
        'denorm' => $ms($t5 - $t4),
        'total' => $ms($t5 - $t0),
    ];
}

// ---- aggregate -------------------------------------------------------------
function agg(array $runs, array $keys): array
{
    $out = [
        'runs' => \count($runs),
    ];
    foreach ($keys as $k) {
        $vals = \array_map(fn (array $r) => $r[$k], $runs);
        \sort($vals);
        $out[$k] = \round($vals[(int)\floor(\count($vals) / 2)], 2);
    }

    return $out;
}

$cacheAll = agg($cacheRuns, ['require', 'arraybuild', 'denorm', 'total']);
$cacheOne = agg($oneRuns, ['require', 'findbyid', 'denorm', 'total']);
$noAll = agg($noRuns, ['read', 'loadxml', 'xpath', 'decode', 'denorm', 'total']);

$op = \function_exists('opcache_get_status') ? \opcache_get_status(false) : false;

echo "=== ENV ===\n";
echo 'PHP ' . \PHP_VERSION . ', libxml2 ' . \LIBXML_DOTTED_VERSION
    . ', opcache enabled=' . ($op ? 'yes' : 'NO')
    . ', opcache.enable_cli=' . \ini_get('opcache.enable_cli')
    . ', validate_timestamps=' . \ini_get('opcache.validate_timestamps') . "\n";
echo 'cache build: ' . \round($buildMs, 1) . " ms\n\n";

echo "=== CACHED findAll() ({$n} runs, median ms) ===\n";
foreach ($cacheAll as $k => $v) {
    if ($k === 'runs') {
        continue;
    }
    $pct = $v / $cacheAll['total'] * 100;
    echo \sprintf("  %-11s %9.2f ms %6.1f%%\n", $k, $v, $pct);
}
echo "\n=== CACHED find(id) ({$n} runs, median ms) ===\n";
foreach ($cacheOne as $k => $v) {
    if ($k === 'runs') {
        continue;
    }
    $pct = $v / $cacheOne['total'] * 100;
    echo \sprintf("  %-11s %9.2f ms %6.1f%%\n", $k, $v, $pct);
}
echo "\n=== NO-CACHE findAll() ({$n} runs, median ms) ===\n";
foreach ($noAll as $k => $v) {
    if ($k === 'runs') {
        continue;
    }
    $pct = $v / $noAll['total'] * 100;
    echo \sprintf("  %-11s %9.2f ms %6.1f%%\n", $k, $v, $pct);
}

$result = [
    'env' => [
        'php' => \PHP_VERSION,
        'libxml' => \LIBXML_DOTTED_VERSION,
        'opcache_enabled' => (bool)$op,
    ],
    'cache_build_ms' => \round($buildMs, 1),
    'cached_findall' => $cacheAll,
    'cached_find_one' => $cacheOne,
    'nocache_findall' => $noAll,
];
\file_put_contents($profileDir . '/results/profile-50k-clean.json', \json_encode($result, \JSON_PRETTY_PRINT));
echo "\nJSON -> .profile/results/profile-50k-clean.json\n";
