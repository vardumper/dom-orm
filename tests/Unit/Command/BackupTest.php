<?php

declare(strict_types=1);

use DOM\ORM\Command\Backup;

$storageFile = \getcwd() . '/storage/data.xml';
$backupDir = \getcwd() . '/storage/backups';

beforeEach(function () use ($storageFile, $backupDir): void {
    if (!\is_dir(\dirname($storageFile))) {
        \mkdir(\dirname($storageFile), 0755, true);
    }
    \file_put_contents($storageFile, '<data />');
    foreach (\glob($backupDir . '/*') ?: [] as $file) {
        \unlink($file);
    }
});

afterEach(function () use ($storageFile, $backupDir): void {
    foreach (\glob($backupDir . '/*') ?: [] as $file) {
        \unlink($file);
    }
    if (\is_dir($backupDir)) {
        \rmdir($backupDir);
    }
    if (\file_exists($storageFile)) {
        \unlink($storageFile);
    }
});

it('copies the storage file into the backups directory', function () use ($backupDir): void {
    Backup::run();

    $backups = \glob($backupDir . '/data-*.xml') ?: [];
    expect($backups)->toHaveCount(1);
});
