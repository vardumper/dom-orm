<?php

declare(strict_types=1);

/**
 * DOM-ORM findAll()/find() phase profiler — 50 K records.
 *
 * Splits the wall time of findAll() into:
 *   (a) parse      = storage->read() + DOMDocument::loadXML() + new DOMXPath()
 *                    (exact replica of EntityManagerTrait::loadData(),
 *                     src/Traits/EntityManagerTrait.php:233-247)
 *   (b) xpath      = DOMXPath::query('//item[@type="bench_user"]')
 *                    (AbstractEntityRepository::queryNodes(),
 *                     src/Repository/AbstractEntityRepository.php:290-298)
 *   (c) hydration  = SchemaDecoder::decode() (DOM -> array)
 *                    + SchemaDenormalizer::denormalize() (reflection,
 *                      setters, object construction)
 *                    (AbstractEntityRepository::findAll(), lines 70-72)
 *
 * The phases in findAll() are strictly sequential (no interleaving), so each
 * is measured directly on the same 50 K document; the separately measured
 * total (fresh repository, real findAll() call) is reported alongside and the
 * residual (total - a - b - c) is the repository overhead (cache check,
 * null checks, Collection bookkeeping).
 *
 * Usage:
 *   XDEBUG_MODE=off php .profile/bench.php [N_findAll=10] [N_find=30]
 */

// ---------------------------------------------------------------------------
// Environment — point the library at .profile/data, NO query cache (so
// findAll() is forced through the XML path: parse -> XPath -> hydration).
// ---------------------------------------------------------------------------
$profileDir = __DIR__;
$dataDir = $profileDir . '/data';

\putenv('DOM_ORM_FLYSYSTEM_ADAPTER=League\\Flysystem\\Local\\LocalFilesystemAdapter');
\putenv('DOM_ORM_FLYSYSTEM_LOCATION=' . $dataDir);
\putenv('DOM_ORM_FILENAME=data.xml');
\putenv('DOM_ORM_CACHE_PATH='); // empty -> null -> QueryCache disabled
\putenv('DOM_ORM_CACHE_STRATEGY=manual');
\putenv('DOM_ORM_VERSIONING=false');

require $profileDir . '/../vendor/autoload.php';
require $profileDir . '/../benchmarks/compare/BenchUser.php';
require $profileDir . '/ProfiledRepository.php';

use DOM\ORM\Serializer\Encoder\SchemaEncoder;

$nAll = (int)($argv[1] ?? 10);
$nOne = (int)($argv[2] ?? 30);

$entityType = 'bench_user';
$allQuery = \sprintf('//item[@type="%s"]', $entityType);

// Pick the id of item #25000 from the dataset for the point lookup.
$lookupId = null;
$fh = \fopen($dataDir . '/data.xml', 'r');
$n = 0;
while (($line = \fgets($fh)) !== false) {
    if (\str_starts_with(\trim($line), '<item ')) {
        $n++;
        if ($n === 25000) {
            \preg_match('/id="([^"]+)"/', $line, $m);
            $lookupId = $m[1];
            break;
        }
    }
}
\fclose($fh);
if ($lookupId === null) {
    \fwrite(\STDERR, "could not extract lookup id\n");
    exit(1);
}
$oneQuery = \sprintf('//item[@type="%s" and @id="%s"]', $entityType, $lookupId);

// ---------------------------------------------------------------------------
// Warm-up: first repository construction (cold reflection cache, shared
// storage + serializer) and one full findAll() so every code path is
// compiled/loaded before the measured runs.
// ---------------------------------------------------------------------------
$memStart = \memory_get_usage(true);
$t0 = \hrtime(true);
$warm = new ProfiledRepository(BenchUser::class);
$coldConstructUs = \hrtime(true) - $t0;
$t0 = \hrtime(true);
$warm->findAll();
$coldFindAllUs = \hrtime(true) - $t0;
$sharedStorage = $warm->storage();
$sharedSerializer = $warm->serializer();
unset($warm);
gc_collect_cycles();

// ---------------------------------------------------------------------------
// Measurement helpers
// ---------------------------------------------------------------------------
function median(array $xs): float
{
    $xs = $xs;
    \sort($xs);
    $n = \count($xs);

    return $n === 0 ? 0.0 : $xs[(int)\floor($n / 2)];
}

function p95(array $xs): float
{
    $xs = $xs;
    \sort($xs);
    $n = \count($xs);

    return $n === 0 ? 0.0 : $xs[(int)\floor($n * 0.95)];
}

/**
 * Measures the three phases of one query by replicating the exact internal
 * call sequence on a fresh repository (whose DOM is not loaded yet), then
 * measures the real total via a second fresh repository's public method.
 *
 * @return array<string, float> microseconds
 */
function measurePhases(
    string $query,
    string $totalMethod,
    ProfiledRepository $repo,
    string $entityClass,
    array $args = [],
): array {
    // Phase pass — same calls the library makes, in the same order.
    $dom = $repo->getEmptyDom(); // EntityManagerTrait::getEmptyDom()

    $t = \hrtime(true);
    $xml = $repo->storage()->read();
    $tRead = \hrtime(true);
    $dom->loadXML($xml);
    $tLoad = \hrtime(true);
    $xpath = new \DOMXPath($dom);
    $tNewXp = \hrtime(true);
    $nodes = $xpath->query($query);
    $tXpath = \hrtime(true);
    $array = $repo->serializer()->decode($nodes, SchemaEncoder::FORMAT);
    $tDecode = \hrtime(true);
    $repo->serializer()->denormalize($array, $entityClass);
    $tEnd = \hrtime(true);

    // Total pass — the real public API on a fresh repository.
    $repo2 = new ProfiledRepository($entityClass);
    $t = \hrtime(true);
    $repo2->{$totalMethod}(...$args);
    $tTotal = \hrtime(true);

    return [
        'read_us' => $tRead - $t,
        'loadXml_us' => $tLoad - $tRead,
        'newXPath_us' => $tNewXp - $tLoad,
        'xpath_us' => $tXpath - $tNewXp,
        'decode_us' => $tDecode - $tXpath,
        'denorm_us' => $tEnd - $tDecode,
        'total_us' => $tTotal - $t,
    ];
}

// ---------------------------------------------------------------------------
// findAll() — N iterations
// ---------------------------------------------------------------------------
$repo = new ProfiledRepository(BenchUser::class);
$allRuns = [];
for ($i = 0; $i < $nAll; $i++) {
    $allRuns[] = measurePhases($allQuery, 'findAll', $repo, BenchUser::class);
}
$memAfterAll = \memory_get_usage(true);
$peakAfterAll = \memory_get_peak_usage(true);

// Warm repository construction cost (explains the fresh-process total).
$t0 = \hrtime(true);
$c = new ProfiledRepository(BenchUser::class);
$warmConstructUs = \hrtime(true) - $t0;
unset($c);

// ---------------------------------------------------------------------------
// find($id) — M iterations
// ---------------------------------------------------------------------------
$oneRuns = [];
for ($i = 0; $i < $nOne; $i++) {
    $oneRuns[] = measurePhases($oneQuery, 'find', $repo, BenchUser::class, [$lookupId]);
}
$memAfterOne = \memory_get_usage(true);
$peakAfterOne = \memory_get_peak_usage(true);

// ---------------------------------------------------------------------------
// Aggregate + report
// ---------------------------------------------------------------------------
function summarize(array $runs, int $records): array
{
    $keys = ['read_us', 'loadXml_us', 'newXPath_us', 'xpath_us', 'decode_us', 'denorm_us', 'total_us'];
    $out = [
        'runs' => \count($runs),
    ];
    foreach ($keys as $k) {
        $vals = \array_map(fn (array $r) => $r[$k], $runs);
        $out[$k] = [
            'median_ms' => \round(median($vals) / 1000, 3),
            'p95_ms' => \round(p95($vals) / 1000, 3),
            'min_ms' => \round(\min($vals) / 1000, 3),
            'max_ms' => \round(\max($vals) / 1000, 3),
            'per_record_us' => \round(median($vals) / $records, 3),
        ];
    }
    // Bucket sums (medians of the sums, to stay consistent with per-run data).
    $parse = \array_map(fn (array $r) => $r['read_us'] + $r['loadXml_us'] + $r['newXPath_us'], $runs);
    $xpath = \array_map(fn (array $r) => $r['xpath_us'], $runs);
    $hydration = \array_map(fn (array $r) => $r['decode_us'] + $r['denorm_us'], $runs);
    $total = \array_map(fn (array $r) => $r['total_us'], $runs);
    foreach ([
        'parse' => $parse,
        'xpath' => $xpath,
        'hydration' => $hydration,
        'total' => $total,
    ] as $name => $vals) {
        $out['bucket_' . $name] = [
            'median_ms' => \round(median($vals) / 1000, 3),
            'p95_ms' => \round(p95($vals) / 1000, 3),
            'min_ms' => \round(\min($vals) / 1000, 3),
            'max_ms' => \round(\max($vals) / 1000, 3),
            'per_record_us' => \round(median($vals) / $records, 3),
        ];
    }
    $resid = \array_map(fn (array $r) => $r['total_us'] - ($r['read_us'] + $r['loadXml_us'] + $r['newXPath_us'] + $r['xpath_us'] + $r['decode_us'] + $r['denorm_us']), $runs);
    $out['residual_total_minus_phases'] = [
        'median_ms' => \round(median($resid) / 1000, 3),
        'p95_ms' => \round(p95($resid) / 1000, 3),
    ];

    return $out;
}

$records = 50000;
$env = [
    'php_version' => \PHP_VERSION,
    'libxml_version' => \LIBXML_DOTTED_VERSION,
    'xdebug' => \extension_loaded('xdebug') ? \ini_get('xdebug.mode') : 'not loaded',
    'opcache_loaded' => \extension_loaded('Zend OPcache'),
    'opcache_enabled' => \function_exists('opcache_get_status') ? (bool)(\opcache_get_status(false) !== false) : false,
    'opcache_validate_timestamps' => \ini_get('opcache.validate_timestamps'),
    'opcache_enable_cli' => \ini_get('opcache.enable_cli'),
    'query_cache' => 'disabled (DOM_ORM_CACHE_PATH unset)',
    'dataset' => $dataDir . '/data.xml',
    'dataset_bytes' => \filesize($dataDir . '/data.xml'),
    'records' => $records,
    'lookup_id' => $lookupId,
];

$result = [
    'env' => $env,
    'warmup' => [
        'cold_repo_construct_ms' => \round($coldConstructUs / 1000, 3),
        'cold_first_findall_ms' => \round($coldFindAllUs / 1000, 3),
        'warm_repo_construct_ms' => \round($warmConstructUs / 1000, 3),
    ],
    'findall' => summarize($allRuns, $records),
    'find_by_id' => summarize($oneRuns, 1),
    'memory' => [
        'start_bytes' => $memStart,
        'after_findall_bytes' => $memAfterAll,
        'peak_after_findall_bytes' => $peakAfterAll,
        'after_find_bytes' => $memAfterOne,
        'peak_after_find_bytes' => $peakAfterOne,
        'peak_after_findall_mb' => \round($peakAfterAll / 1048576, 2),
        'peak_after_find_mb' => \round($peakAfterOne / 1048576, 2),
    ],
];

$outFile = $profileDir . '/results/profile-50k.json';
\file_put_contents($outFile, \json_encode($result, \JSON_PRETTY_PRINT));

// ---------------------------------------------------------------------------
// Human-readable table
// ---------------------------------------------------------------------------
function printTable(string $title, array $s, int $records): void
{
    $totalMs = $s['bucket_total']['median_ms'];
    $fmt = fn (array $b): string => \sprintf(
        '%9.3f ms %6.2f%% %10.3f us/rec',
        $b['median_ms'],
        $totalMs > 0 ? $b['median_ms'] / $totalMs * 100 : 0.0,
        $b['per_record_us'],
    );
    echo "== {$title} ({$s['runs']} runs, median) ==\n";
    echo '  (a) parse (read+loadXML+new DOMXPath)  ' . $fmt($s['bucket_parse']) . "\n";
    echo '      - storage->read()                  ' . $fmt($s['read_us']) . "\n";
    echo '      - DOMDocument::loadXML()           ' . $fmt($s['loadXml_us']) . "\n";
    echo '      - new DOMXPath()                   ' . $fmt($s['newXPath_us']) . "\n";
    echo '  (b) xpath  DOMXPath::query()           ' . $fmt($s['bucket_xpath']) . "\n";
    echo '  (c) hydration (decode+denormalize)     ' . $fmt($s['bucket_hydration']) . "\n";
    echo '      - SchemaDecoder::decode()          ' . $fmt($s['decode_us']) . "\n";
    echo '      - SchemaDenormalizer::denormalize() ' . $fmt($s['denorm_us']) . "\n";
    echo '  TOTAL (real findAll()/find() call)     ' . $fmt($s['bucket_total']) . "\n";
    echo '  residual (total - a - b - c)           ' . \sprintf('%9.3f ms', $s['residual_total_minus_phases']['median_ms']) . "\n";
    echo "\n";
}

printTable('findAll() — 50,000 records', $result['findall'], $records);
printTable("find({$lookupId}) — point lookup", $result['find_by_id'], 1);

echo "env: PHP {$env['php_version']}, libxml2 {$env['libxml_version']}, xdebug=" . ($env['xdebug'] ?? 'n/a')
    . ', opcache enabled in this SAPI: ' . ($env['opcache_enabled'] ? 'yes' : 'NO')
    . ' (opcache.enable_cli=' . $env['opcache_enable_cli'] . ', validate_timestamps=' . $env['opcache_validate_timestamps'] . ")\n";
echo 'memory: peak after findAll loop = ' . $result['memory']['peak_after_findall_mb'] . " MB, peak after find loop = {$result['memory']['peak_after_find_mb']} MB\n";
echo "JSON written to {$outFile}\n";
