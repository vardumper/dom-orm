<?php return [
    'dom-orm' =>
     [
        'flysystem' =>
          [
            'adapter' => 'League\\Flysystem\\Local\\LocalFilesystemAdapter',
              'config' =>
               [
                'location' => '/var/folders/3f/kt_5qv5s3yjd3z5cms84j9q80000gn/T/dom_orm_perf',
            ],
        ],
         'filename' => 'perf_data.xml',
         'encryption_key' => null,
         'cache_path' => '/var/folders/3f/kt_5qv5s3yjd3z5cms84j9q80000gn/T/dom_orm_perf/perf_cache.php',
         'cache_strategy' => 'manual',
    ],
];
