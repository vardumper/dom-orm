<?php
declare(strict_types=1);

use League\Flysystem\Local\LocalFilesystemAdapter;
use function DOM\ORM\getConfig;

it('returns the default dom orm configuration', function (): void {
    $config = getConfig();

    expect($config->get('dom-orm.flysystem.adapter'))->toBe(LocalFilesystemAdapter::class)
        ->and($config->get('dom-orm.flysystem.config'))->toBe([
            'location' => getcwd() . '/storage',
        ])
        ->and($config->get('dom-orm.filename'))->toBe('data.xml');
});
