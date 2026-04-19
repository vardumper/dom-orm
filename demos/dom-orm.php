<?php

declare(strict_types=1);

use League\Flysystem\Local\LocalFilesystemAdapter;

return [
    'dom-orm' => [
        'flysystem' => [
            'adapter' => LocalFilesystemAdapter::class,
            'config' => [
                'location' => __DIR__ . '/virtual-filesystem/storage',
            ],
        ],
        'filename' => 'data.xml',
    ],
];
