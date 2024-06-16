<?php
declare(strict_types=1);

namespace DOM\ORM;

use League\Config\Configuration;
use League\Flysystem\Local\LocalFilesystemAdapter;
use Nette\Schema\Expect;

function getConfig(): Configuration
{
    // set defaults
    $config = new Configuration([
        'dom-orm' => Expect::structure([
            'flysystem' => Expect::structure([
                'adapter' => Expect::string()->default(LocalFilesystemAdapter::class),
                'config' => Expect::array()->default([
                    'location' => getcwd() . '/storage',
                ]),
            ]),
            'filename' => Expect::string()->default('data.xml'),
        ]),
    ]);

    // override the default config
    $possibleFiles = [
        getcwd() . '/config/dom-orm.php',
        getcwd() . '/../config/dom-orm.php',
        getcwd() . '/dom-orm.php',
        getcwd() . '/../dom-orm.php',
    ];

    $file = current(array_filter($possibleFiles, 'file_exists'));

    if (empty($file)) {
        return $config;
    }

    // splat a custom config file into the default config
    $config->merge(require_once $file);

    return $config;
}
