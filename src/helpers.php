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
        $envConfig = getEnvConfig();
        if ($envConfig !== []) {
            $config->merge($envConfig);
        }

        return $config;
    }

    $config->merge(require $file);

    $envConfig = getEnvConfig();
    if ($envConfig !== []) {
        $config->merge($envConfig);
    }

    return $config;
}

/**
 * Builds an optional configuration fragment from DOM_ORM_* environment variables.
 *
 * Supported variables:
 * - DOM_ORM_FLYSYSTEM_ADAPTER
 * - DOM_ORM_FLYSYSTEM_LOCATION
 * - DOM_ORM_FILENAME
 * - DOM_ORM_LOCK_FILE
 * - DOM_ORM_ENCRYPTION_KEY
 * - DOM_ORM_CACHE_PATH
 * - DOM_ORM_CACHE_STRATEGY (manual|on_persist)
 * - DOM_ORM_VERSIONING (bool)
 * - DOM_ORM_VERSION_CONTROL (git|hg)
 * - DOM_ORM_VERSION_CONTROL_PUSH (manual|on_persist)
 * - DOM_ORM_EXPORT_ON_PERSIST_FILE
 * - DOM_ORM_EXPORT_ON_PERSIST_XML
 * - DOM_ORM_EXPORT_ON_PERSIST_YAML
 * - DOM_ORM_EXPORT_ON_PERSIST_JSON
 * - DOM_ORM_EXPORT_ON_PERSIST_PHP
 *
 * @return array<string, mixed>
 */
function getEnvConfig(): array
{
    $domOrm = [];

    $flysystemAdapter = envValue('DOM_ORM_FLYSYSTEM_ADAPTER');
    $flysystemLocation = envValue('DOM_ORM_FLYSYSTEM_LOCATION');
    if ($flysystemAdapter !== null || $flysystemLocation !== null) {
        $domOrm['flysystem'] = [];
        if ($flysystemAdapter !== null) {
            $domOrm['flysystem']['adapter'] = $flysystemAdapter;
        }
        if ($flysystemLocation !== null) {
            $domOrm['flysystem']['config'] = [
                'location' => $flysystemLocation,
            ];
        }
    }

    $filename = envValue('DOM_ORM_FILENAME');
    if ($filename !== null) {
        $domOrm['filename'] = $filename;
    }

    $lockFile = envValue('DOM_ORM_LOCK_FILE');
    if ($lockFile !== null) {
        $domOrm['lock_file'] = $lockFile;
    }

    $encryptionKey = envValue('DOM_ORM_ENCRYPTION_KEY');
    if ($encryptionKey !== null) {
        $domOrm['encryption_key'] = $encryptionKey;
    }

    $cachePath = envValue('DOM_ORM_CACHE_PATH');
    if ($cachePath !== null) {
        $domOrm['cache_path'] = $cachePath;
    }

    $cacheStrategy = envValue('DOM_ORM_CACHE_STRATEGY');
    if ($cacheStrategy !== null) {
        $domOrm['cache_strategy'] = $cacheStrategy;
    }

    $versioning = envBool('DOM_ORM_VERSIONING');
    if ($versioning !== null) {
        $domOrm['versioning'] = $versioning;
    }

    $versionControl = envValue('DOM_ORM_VERSION_CONTROL');
    if ($versionControl !== null) {
        $domOrm['version_control'] = $versionControl;
    }

    $versionControlPush = envValue('DOM_ORM_VERSION_CONTROL_PUSH');
    if ($versionControlPush !== null) {
        $domOrm['version_control_push'] = $versionControlPush;
    }

    $exportOnPersist = [];
    $exportFile = envValue('DOM_ORM_EXPORT_ON_PERSIST_FILE');
    if ($exportFile !== null) {
        $exportOnPersist['file'] = $exportFile;
    }

    $exportXml = envBoolOrString('DOM_ORM_EXPORT_ON_PERSIST_XML');
    if ($exportXml !== null) {
        $exportOnPersist['xml'] = $exportXml;
    }

    $exportYaml = envBoolOrString('DOM_ORM_EXPORT_ON_PERSIST_YAML');
    if ($exportYaml !== null) {
        $exportOnPersist['yaml'] = $exportYaml;
    }

    $exportJson = envBoolOrString('DOM_ORM_EXPORT_ON_PERSIST_JSON');
    if ($exportJson !== null) {
        $exportOnPersist['json'] = $exportJson;
    }

    $exportPhp = envBoolOrString('DOM_ORM_EXPORT_ON_PERSIST_PHP');
    if ($exportPhp !== null) {
        $exportOnPersist['php'] = $exportPhp;
    }

    if ($exportOnPersist !== []) {
        $domOrm['export_on_persist'] = $exportOnPersist;
    }

    return $domOrm === [] ? [] : [
        'dom-orm' => $domOrm,
    ];
}

function envValue(string $name): ?string
{
    $value = \getenv($name);
    if ($value === false) {
        $value = $_ENV[$name] ?? $_SERVER[$name] ?? null;
    }

    if (!\is_string($value)) {
        return null;
    }

    $value = \trim($value);

    return $value === '' ? null : $value;
}

function envBool(string $name): ?bool
{
    $value = envValue($name);
    if ($value === null) {
        return null;
    }

    $normalized = \strtolower($value);

    if (\in_array($normalized, ['1', 'true', 'yes', 'on'], true)) {
        return true;
    }

    if (\in_array($normalized, ['0', 'false', 'no', 'off'], true)) {
        return false;
    }

    return null;
}

function envBoolOrString(string $name): bool|string|null
{
    $value = envValue($name);
    if ($value === null) {
        return null;
    }

    $bool = envBool($name);
    if ($bool !== null) {
        return $bool;
    }

    return $value;
}
