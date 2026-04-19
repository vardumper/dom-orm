<?php
declare(strict_types=1);

use League\Flysystem\Local\LocalFilesystemAdapter;
use function DOM\ORM\getConfig;

const DOM_ORM_ENV_KEYS = [
    'DOM_ORM_FLYSYSTEM_ADAPTER',
    'DOM_ORM_FLYSYSTEM_LOCATION',
    'DOM_ORM_FILENAME',
    'DOM_ORM_LOCK_FILE',
    'DOM_ORM_ENCRYPTION_KEY',
    'DOM_ORM_CACHE_PATH',
    'DOM_ORM_CACHE_STRATEGY',
    'DOM_ORM_VERSIONING',
    'DOM_ORM_VERSION_CONTROL',
    'DOM_ORM_VERSION_CONTROL_PUSH',
    'DOM_ORM_EXPORT_ON_PERSIST_FILE',
    'DOM_ORM_EXPORT_ON_PERSIST_XML',
    'DOM_ORM_EXPORT_ON_PERSIST_YAML',
    'DOM_ORM_EXPORT_ON_PERSIST_JSON',
    'DOM_ORM_EXPORT_ON_PERSIST_PHP',
];

/** @var array<string, string|false> $domOrmOriginalEnv */
$domOrmOriginalEnv = [];

beforeEach(function () use (&$domOrmOriginalEnv): void {
    $domOrmOriginalEnv = [];

    foreach (DOM_ORM_ENV_KEYS as $key) {
        $domOrmOriginalEnv[$key] = \getenv($key);
        \putenv($key);
        unset($_ENV[$key], $_SERVER[$key]);
    }
});

afterEach(function () use (&$domOrmOriginalEnv): void {
    foreach (DOM_ORM_ENV_KEYS as $key) {
        $original = $domOrmOriginalEnv[$key] ?? false;

        if ($original === false) {
            \putenv($key);
            unset($_ENV[$key], $_SERVER[$key]);

            continue;
        }

        \putenv($key . '=' . $original);
        $_ENV[$key] = $original;
        $_SERVER[$key] = $original;
    }
});

it('returns the default dom orm configuration', function (): void {
    $config = getConfig();

    expect($config->get('dom-orm.flysystem.adapter'))->toBe(LocalFilesystemAdapter::class)
        ->and($config->get('dom-orm.flysystem.config'))->toBe([
            'location' => getcwd() . '/storage',
        ])
        ->and($config->get('dom-orm.filename'))->toBe('data.xml');
});

it('supports environment-only dom orm configuration', function (): void {
    \putenv('DOM_ORM_FLYSYSTEM_LOCATION=/tmp/dom-orm-env-storage');
    \putenv('DOM_ORM_FILENAME=env-data.xml');
    \putenv('DOM_ORM_ENCRYPTION_KEY=env-key');
    \putenv('DOM_ORM_VERSIONING=true');
    \putenv('DOM_ORM_EXPORT_ON_PERSIST_JSON=1');

    $config = getConfig();

    expect($config->get('dom-orm.flysystem.config'))->toBe([
        'location' => '/tmp/dom-orm-env-storage',
    ])
        ->and($config->get('dom-orm.filename'))->toBe('env-data.xml')
        ->and($config->get('dom-orm.encryption_key'))->toBe('env-key')
        ->and($config->get('dom-orm.versioning'))->toBeTrue()
        ->and($config->get('dom-orm.export_on_persist.json'))->toBeTrue();
});

it('allows environment variables to override file configuration values', function (): void {
    \putenv('DOM_ORM_FILENAME=env-overrides.xml');

    $config = getConfig();

    expect($config->get('dom-orm.filename'))->toBe('env-overrides.xml');
});
