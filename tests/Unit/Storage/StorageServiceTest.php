<?php

declare(strict_types=1);

use DOM\ORM\Storage\InMemoryFilesystemAdapter;
use DOM\ORM\Storage\StorageService;
use League\Flysystem\Filesystem;
use League\Flysystem\Local\LocalFilesystemAdapter;
use League\Flysystem\UnableToReadFile;

$tmpDir = null;
$lockFile = null;
$storage = null;

// Each test gets its own isolated temp directory
beforeEach(function () use (&$tmpDir, &$lockFile, &$storage): void {
    $tmpDir = \sys_get_temp_dir() . '/dom-orm-test-' . \uniqid('', true);
    \mkdir($tmpDir, 0755, true);
    $lockFile = $tmpDir . '/test.xml.lock';
    $storage = new StorageService(
        new Filesystem(new LocalFilesystemAdapter($tmpDir)),
        'test.xml',
        $lockFile,
    );
});

afterEach(function () use (&$tmpDir, &$storage): void {
    $storage = null;

    if ($tmpDir === null) {
        return;
    }

    \array_map('unlink', (array)\glob($tmpDir . '/*'));
    if (\is_dir($tmpDir)) {
        \rmdir($tmpDir);
    }

    $tmpDir = null;
});

it('write creates a file that read returns', function () use (&$storage): void {
    if (!$storage instanceof StorageService) {
        throw new RuntimeException('Storage test fixture was not initialized.');
    }

    $storage->write('<data />');
    expect($storage->read())->toBe('<data />');
});

it('write overwrites existing content', function () use (&$storage): void {
    if (!$storage instanceof StorageService) {
        throw new RuntimeException('Storage test fixture was not initialized.');
    }

    $storage->write('<data>first</data>');
    $storage->write('<data>second</data>');
    expect($storage->read())->toBe('<data>second</data>');
});

it('read throws when file does not exist', function () use (&$storage): void {
    if (!$storage instanceof StorageService) {
        throw new RuntimeException('Storage test fixture was not initialized.');
    }

    \set_error_handler(static fn () => true, E_WARNING);

    try {
        expect(fn () => $storage->read())->toThrow(UnableToReadFile::class);
    } finally {
        \restore_error_handler();
    }
});

it('fromConfig returns a StorageService instance', function (): void {
    // fromConfig uses getcwd()/storage – just verify the return type
    expect(StorageService::fromConfig())->toBeInstanceOf(StorageService::class);
});

it('fromConfig supports the built-in in-memory adapter via env', function (): void {
    \putenv('DOM_ORM_FLYSYSTEM_ADAPTER=' . InMemoryFilesystemAdapter::class);

    try {
        $storage = StorageService::fromConfig();
        $storage->write('<data><item /></data>');

        expect($storage->read())->toBe('<data><item /></data>');
    } finally {
        \putenv('DOM_ORM_FLYSYSTEM_ADAPTER');
    }
});

it('lock creates a lock file and unlock releases it', function () use (&$storage, &$lockFile): void {
    if (!$storage instanceof StorageService) {
        throw new RuntimeException('Storage test fixture was not initialized.');
    }

    if (!is_string($lockFile)) {
        throw new RuntimeException('Lock file test fixture was not initialized.');
    }

    $storage->lock();

    expect($lockFile)
        ->toBeFile();

    $storage->unlock();
    $storage->lock();
    $storage->unlock();

    expect($lockFile)
        ->toBeFile();
});

it('unlock is a no-op when no lock is held', function () use (&$storage): void {
    if (!$storage instanceof StorageService) {
        throw new RuntimeException('Storage test fixture was not initialized.');
    }

    $storage->unlock();

    expect(true)->toBeTrue();
});

it('serializes concurrent lock acquisition across processes', function () use (&$tmpDir, &$lockFile): void {
    $xdebugMode = \getenv('XDEBUG_MODE');
    $iniXdebugMode = \ini_get('xdebug.mode');
    $coverageEnabled =
        (\is_string($xdebugMode) && \str_contains($xdebugMode, 'coverage'))
        || (\is_string($iniXdebugMode) && \str_contains($iniXdebugMode, 'coverage'));

    if ($coverageEnabled) {
        // Forked child shutdown can interfere with coverage drivers; make this
        // a deterministic no-op in coverage mode.
        expect(true)->toBeTrue();

        return;
    }

    if (!\function_exists('pcntl_fork') || !\function_exists('stream_socket_pair')) {
        test()->markTestSkipped('pcntl and stream_socket_pair are required for concurrency lock testing.');
    }

    if (!is_string($tmpDir)) {
        throw new RuntimeException('Temp directory test fixture was not initialized.');
    }

    if (!is_string($lockFile)) {
        throw new RuntimeException('Lock file test fixture was not initialized.');
    }

    $parentStorage = new StorageService(
        new Filesystem(new LocalFilesystemAdapter($tmpDir)),
        'test.xml',
        $lockFile,
    );
    $childStorage = new StorageService(
        new Filesystem(new LocalFilesystemAdapter($tmpDir)),
        'test.xml',
        $lockFile,
    );

    $sockets = \stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
    if ($sockets === false) {
        throw new RuntimeException('Failed to create socket pair for concurrency test.');
    }

    [$parentSocket, $childSocket] = $sockets;

    $parentStorage->lock();
    $start = \microtime(true);
    $pid = \pcntl_fork();

    if ($pid === -1) {
        $parentStorage->unlock();

        throw new RuntimeException('Failed to fork for concurrency test.');
    }

    if ($pid === 0) {
        \fclose($parentSocket);
        $childStart = \microtime(true);
        $childStorage->lock();
        $elapsed = \microtime(true) - $childStart;
        \fwrite($childSocket, (string)$elapsed);
        $childStorage->unlock();
        \fclose($childSocket);
        exit(0);
    }

    \fclose($childSocket);
    \usleep(250000);
    $parentStorage->unlock();

    $elapsed = (float)\stream_get_contents($parentSocket);
    \fclose($parentSocket);
    \pcntl_waitpid($pid, $status);

    expect($elapsed)
        ->toBeGreaterThanOrEqual(0.2);
    expect(\pcntl_wifexited($status))
        ->toBeTrue();
    expect(\pcntl_wexitstatus($status))
        ->toBe(0);
    expect(\microtime(true) - $start)
        ->toBeGreaterThanOrEqual(0.2);
});
