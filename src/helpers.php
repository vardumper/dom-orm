<?php
declare(strict_types=1);

namespace DOM\ORM;

use League\Config\Configuration;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Nette\Schema\Expect;

function getConfig(): Configuration
{
    $config = new Configuration([
        'dom-orm' => Expect::structure([
            'flysystem' => Expect::structure([
                'adapter' => Expect::string()->default(LocalFilesystemAdapter::class),
                'config' => Expect::array()->default([
                    'location' => \getcwd() . '/storage',
                ]),
            ]),
            'filename' => Expect::string()->default('data.xml'),
            'lock_file' => Expect::string()->nullable()->default(null),
            'encryption_key' => Expect::string()->nullable()->default(null),
            'cache_path' => Expect::string()->nullable()->default(null),
            'cache_strategy' => Expect::anyOf('manual', 'on_persist')->default('manual'),
            'versioning' => Expect::bool()->default(false),
            'version_control' => Expect::anyOf('git', 'hg')->default('git'),
            'version_control_push' => Expect::anyOf('manual', 'on_persist')->default('manual'),
            'export_on_persist' => Expect::structure([
                'file' => Expect::string()->nullable()->default(null),
                'xml' => Expect::anyOf(true, false, Expect::string())->default(false),
                'yaml' => Expect::anyOf(true, false, Expect::string())->default(false),
                'json' => Expect::anyOf(true, false, Expect::string())->default(false),
                'php' => Expect::anyOf(true, false, Expect::string())->default(false),
            ]),
        ]),
    ]);

    $possibleFiles = [
        \getcwd() . '/config/dom-orm.php',
        \getcwd() . '/../config/dom-orm.php',
        \getcwd() . '/dom-orm.php',
        \getcwd() . '/../dom-orm.php',
    ];

    $file = \current(\array_filter($possibleFiles, 'file_exists'));

    if (empty($file)) {
        return $config;
    }

    $config->merge(require $file);

    return $config;
}
