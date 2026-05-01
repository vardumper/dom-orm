<?php

declare(strict_types=1);

namespace DOM\ORM\Command;

use DOM\ORM\Entity\AbstractEntity;
use DOM\ORM\Mapping\Fragment;
use DOM\ORM\Mapping\Item;
use DOM\ORM\Repository\EntityRepository;
use DOM\ORM\Storage\InMemoryFilesystemAdapter;
use DOM\ORM\Storage\QueryCache;
use DOM\ORM\Traits\EntityManagerTrait;
use Faker\Factory as FakerFactory;
use League\Flysystem\Local\LocalFilesystemAdapter;

// ---------------------------------------------------------------------------
// Inline entity — only exists for the perf run, lives alongside the command.
// ---------------------------------------------------------------------------

#[Item(entityType: 'perf_user')]
class PerfUser extends AbstractEntity
{
    #[Fragment]
    private string $name;

    #[Fragment]
    private string $email;

    #[Fragment]
    private string $city;

    public function __construct(
        string $name,
        string $email,
        string $city,
        ?string $id = null,
        ?\DateTimeInterface $createdAt = null,
    ) {
        parent::__construct($id, $createdAt);
        $this->name = $name;
        $this->email = $email;
        $this->city = $city;
    }

    public function getName(): string
    {
        return $this->name;
    }
    public function getEmail(): string
    {
        return $this->email;
    }
    public function getCity(): string
    {
        return $this->city;
    }

    public function setName(string $v): static
    {
        $this->name = $v;

        return $this;
    }
    public function setEmail(string $v): static
    {
        $this->email = $v;

        return $this;
    }
    public function setCity(string $v): static
    {
        $this->city = $v;

        return $this;
    }
}

// ---------------------------------------------------------------------------
// Repository — thin wrapper so EntityRepository can resolve PerfUser.
// ---------------------------------------------------------------------------

class PerfUserRepository extends EntityRepository
{
    public function __construct()
    {
        parent::__construct(PerfUser::class);
    }
}

// ---------------------------------------------------------------------------
// Helper trait for a self-contained manager inside the command.
// ---------------------------------------------------------------------------

class PerfManager
{
    use EntityManagerTrait;

    public function __construct()
    {
        $this->init();
    }

    /**
     * Expose persistBatch publicly so the command can call it.
     */
    public function batchInsert(iterable $entities): void
    {
        $this->persistBatch($entities);
    }

    /**
     * Expose single persist publicly.
     */
    public function insert(AbstractEntity $entity): void
    {
        $this->persist($entity);
    }
}

// ---------------------------------------------------------------------------
// The command itself.
// ---------------------------------------------------------------------------

class Perf
{
    /**
     * @param int  $count        Total entities to seed (default 100 000).
     * @param int  $sampleSize   How many to insert one-by-one for the baseline (default 50).
     * @param bool $keepStorage  When false (default) the perf storage file is deleted after the run.
     * @param bool $useInMemory  When true, run benchmark with InMemoryFilesystemAdapter.
     * @return array{
     *   adapter: string,
     *   count: int,
     *   sample_size: int,
     *   one_by_one_sample_ms: float,
     *   one_by_one_per_entity_ms: float,
     *   one_by_one_estimated_total_ms: float,
     *   batch_total_ms: float,
     *   batch_per_entity_ms: float,
     *   batch_xml_kb: int,
     *   find_all_ms: float,
     *   find_one_ms: float,
     *   find_one_by_ms: float,
     *   cache_build_ms: float|null,
     *   cache_find_all_ms: float|null,
     *   cache_find_one_ms: float|null,
     *   cache_find_one_by_ms: float|null,
     *   memory_start_mb: float,
     *   memory_end_mb: float,
     *   memory_peak_mb: float,
     *   memory_delta_mb: float,
     * }
     */
    public static function run(int $count = 100_000, int $sampleSize = 50, bool $keepStorage = false, bool $useInMemory = false): array
    {
        // Benchmarking 100k entities needs more RAM than the PHP default.
        \ini_set('memory_limit', '1G');
        $memoryStartBytes = \memory_get_usage(true);
        $storageDir = \sys_get_temp_dir() . '/dom_orm_perf';
        $filename = 'perf_data.xml';
        $storageFile = $storageDir . '/' . $filename;
        $cacheFile = $storageDir . '/perf_cache.php';
        $configFile = \getcwd() . '/dom-orm.php';
        $adapterClass = $useInMemory ? InMemoryFilesystemAdapter::class : LocalFilesystemAdapter::class;

        // ---- Write a temporary dom-orm.php config -------------------------
        $prevConfig = \file_exists($configFile) ? \file_get_contents($configFile) : null;

        if (!\is_dir($storageDir)) {
            \mkdir($storageDir, 0755, true);
        }

        $initializeStorage = static function () use ($useInMemory, $storageDir, $storageFile, $filename): void {
            $xml = '<?xml version="1.0" encoding="utf-8"?>' . "\n<data/>";
            if ($useInMemory) {
                InMemoryFilesystemAdapter::reset($storageDir);
                $adapter = new InMemoryFilesystemAdapter($storageDir);
                $adapter->write($filename, $xml, new \League\Flysystem\Config());

                return;
            }

            \file_put_contents($storageFile, $xml);
        };

        $initializeStorage();

        \file_put_contents($configFile, '<?php return ' . \var_export([
            'dom-orm' => [
                'flysystem' => [
                    'adapter' => $adapterClass,
                    'config' => [
                        'location' => $storageDir,
                    ],
                ],
                'filename' => $filename,
                'encryption_key' => null,
                'cache_path' => $cacheFile,
                'cache_strategy' => 'manual',
            ],
        ], true) . ';');

        // Reset shared singletons so the new config is picked up.
        self::resetSharedSingletons();

        $faker = FakerFactory::create();
        $faker->seed(42); // reproducible run

        // ---- 1. ONE-BY-ONE baseline (sample only) -------------------------
        $sample = [];
        $ids = [];
        $names = [];
        for ($i = 0; $i < $count; $i++) {
            $entity = new PerfUser(
                $faker->name(),
                $faker->safeEmail(),
                $faker->city(),
            );
            $ids[] = $entity->getId();
            $names[] = $entity->getName();
            $sample[] = $entity;
        }

        // Write a fresh empty XML for the one-by-one sample run.
        $initializeStorage();
        self::resetSharedSingletons();

        $mgr = new PerfManager();
        $t0 = \hrtime(true);
        foreach (\array_slice($sample, 0, $sampleSize) as $e) {
            $mgr->insert($e);
        }
        $oneByOneMs = (\hrtime(true) - $t0) / 1_000_000;
        $oneByOnePer = $oneByOneMs / $sampleSize;
        $oneByOneEstMs = $oneByOnePer * $count;

        // ---- 2. BATCH INSERT (all $count entities) ------------------------
        $initializeStorage();
        self::resetSharedSingletons();

        $mgr2 = new PerfManager();
        $t1 = \hrtime(true);
        $mgr2->batchInsert($sample);
        $batchMs = (\hrtime(true) - $t1) / 1_000_000;
        $batchPer = $batchMs / $count;
        $batchXmlKb = \file_exists($storageFile) ? (int)(\filesize($storageFile) / 1024) : 0;

        // ---- 3. QUERY — XPath (no cache) ---------------------------------
        self::resetSharedSingletons();

        $repo = new PerfUserRepository();

        $t2 = \hrtime(true);
        $all = $repo->findAll();
        $findAllMs = (\hrtime(true) - $t2) / 1_000_000;

        $lookupId = $ids[\array_rand($ids)];
        $lookupName = $names[\array_rand($names)];

        $t3 = \hrtime(true);
        $repo->find($lookupId);
        $findOneMs = (\hrtime(true) - $t3) / 1_000_000;

        $t4 = \hrtime(true);
        $repo->findOneBy([
            'name' => $lookupName,
        ]);
        $findOneByMs = (\hrtime(true) - $t4) / 1_000_000;

        // ---- 4. QUERY — PHP cache ----------------------------------------
        $t5 = \hrtime(true);
        QueryCache::build();
        $cacheBuildMs = (\hrtime(true) - $t5) / 1_000_000;

        self::resetSharedSingletons();
        $cachedRepo = new PerfUserRepository();

        $t6 = \hrtime(true);
        $cachedRepo->findAll();
        $cacheFindAllMs = (\hrtime(true) - $t6) / 1_000_000;

        $t7 = \hrtime(true);
        $cachedRepo->find($lookupId);
        $cacheFindOneMs = (\hrtime(true) - $t7) / 1_000_000;

        $t8 = \hrtime(true);
        $cachedRepo->findOneBy([
            'name' => $lookupName,
        ]);
        $cacheFindOneByMs = (\hrtime(true) - $t8) / 1_000_000;

        // ---- Cleanup -------------------------------------------------------
        if (!$keepStorage) {
            foreach ([$storageFile, $cacheFile] as $f) {
                if (\file_exists($f)) {
                    \unlink($f);
                }
            }
            if (\is_dir($storageDir)) {
                @\rmdir($storageDir);
            }

            if ($useInMemory) {
                InMemoryFilesystemAdapter::reset($storageDir);
            }
        }

        // Restore the previous config (or remove the temp one).
        if ($prevConfig !== null) {
            \file_put_contents($configFile, $prevConfig);
        } elseif (\file_exists($configFile)) {
            \unlink($configFile);
        }

        self::resetSharedSingletons();

        $memoryEndBytes = \memory_get_usage(true);
        $memoryPeakBytes = \memory_get_peak_usage(true);

        return [
            'adapter' => $useInMemory ? 'in_memory' : 'local',
            'count' => $count,
            'sample_size' => $sampleSize,
            'one_by_one_sample_ms' => \round($oneByOneMs, 2),
            'one_by_one_per_entity_ms' => \round($oneByOnePer, 4),
            'one_by_one_estimated_total_ms' => \round($oneByOneEstMs, 0),
            'batch_total_ms' => \round($batchMs, 2),
            'batch_per_entity_ms' => \round($batchPer, 4),
            'batch_xml_kb' => $batchXmlKb,
            'find_all_ms' => \round($findAllMs, 2),
            'find_one_ms' => \round($findOneMs, 2),
            'find_one_by_ms' => \round($findOneByMs, 2),
            'cache_build_ms' => \round($cacheBuildMs, 2),
            'cache_find_all_ms' => \round($cacheFindAllMs, 2),
            'cache_find_one_ms' => \round($cacheFindOneMs, 2),
            'cache_find_one_by_ms' => \round($cacheFindOneByMs, 2),
            'memory_start_mb' => \round($memoryStartBytes / 1_048_576, 2),
            'memory_end_mb' => \round($memoryEndBytes / 1_048_576, 2),
            'memory_peak_mb' => \round($memoryPeakBytes / 1_048_576, 2),
            'memory_delta_mb' => \round(($memoryEndBytes - $memoryStartBytes) / 1_048_576, 2),
        ];
    }

    private static function resetSharedSingletons(): void
    {
        $class = PerfManager::class;
        $rc = new \ReflectionClass($class);
        foreach (['sharedStorage', 'sharedSerializer'] as $prop) {
            $r = $rc;
            while ($r !== false) {
                if ($r->hasProperty($prop)) {
                    $p = $r->getProperty($prop);
                    $p->setValue(null, null);
                    break;
                }
                $r = $r->getParentClass();
            }
        }
    }
}
