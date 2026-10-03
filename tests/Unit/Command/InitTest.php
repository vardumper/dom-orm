<?php

declare(strict_types=1);

use DOM\ORM\Command\Init;

$storageFile = \getcwd() . '/storage/data.xml';
$backupFile = $storageFile . '.initbak';

beforeEach(function () use ($storageFile, $backupFile): void {
    if (!\is_dir(\dirname($storageFile))) {
        \mkdir(\dirname($storageFile), 0755, true);
    }
    if (\file_exists($storageFile)) {
        \rename($storageFile, $backupFile);
    }
});

afterEach(function () use ($storageFile, $backupFile): void {
    if (\file_exists($storageFile)) {
        \unlink($storageFile);
    }
    if (\file_exists($backupFile)) {
        \rename($backupFile, $storageFile);
    }
});

it('creates the storage file when the database is not initialized', function () use ($storageFile): void {
    $result = Init::run();

    expect($result)->toBeNull();
    expect(\file_exists($storageFile))->toBeTrue();
});

it('reports when the database is already initialized', function () use ($storageFile): void {
    \file_put_contents($storageFile, '<data />');

    $result = Init::run();

    expect($result)->toBe('Database is already initialized. Exiting.');
});
