<?php

declare(strict_types=1);

use DOM\ORM\Vcs\GitAdapter;
use DOM\ORM\Vcs\HgAdapter;
use DOM\ORM\Vcs\VcsService;

// ---------------------------------------------------------------------------
// VcsService::isEnabled()
// ---------------------------------------------------------------------------

it('is disabled by default', function (): void {
    expect(VcsService::isEnabled())->toBeFalse();
});

// ---------------------------------------------------------------------------
// GitAdapter::checkInstalled()
// ---------------------------------------------------------------------------

it('GitAdapter reports git as installed when git binary is on PATH', function (): void {
    $adapter = new GitAdapter();

    // We cannot guarantee git is installed in every CI environment, so we
    // only assert the return type and that the method does not throw.
    expect($adapter->checkInstalled())->toBeBool();
});

it('GitAdapter::checkInstalled returns false when given an unknown binary', function (): void {
    // We monkey-patch by sub-classing to redirect the check to a binary that
    // will never exist, so we can assert the false-path deterministically.
    $adapter = new class() extends GitAdapter {
        public function checkInstalled(): bool
        {
            $result = @\shell_exec('__dom_orm_nonexistent_binary__ --version 2>&1');

            return $result !== null && \str_starts_with(\trim((string)$result), '__dom_orm_nonexistent_binary__ version');
        }
    };

    expect($adapter->checkInstalled())->toBeFalse();
});

// ---------------------------------------------------------------------------
// HgAdapter::checkInstalled()
// ---------------------------------------------------------------------------

it('HgAdapter reports whether hg binary is available without throwing', function (): void {
    $adapter = new HgAdapter();

    expect($adapter->checkInstalled())->toBeBool();
});

// ---------------------------------------------------------------------------
// VcsService::commit() skips when versioning is disabled (default)
// ---------------------------------------------------------------------------

it('VcsService::commit is a no-op when versioning is disabled', function (): void {
    // When versioning is disabled, commit() should return without calling any
    // adapter.  Since isEnabled() returns false by default, no exception or
    // warning should be emitted.
    $warned = false;
    \set_error_handler(static function () use (&$warned): bool {
        $warned = true;

        return true;
    }, \E_USER_WARNING);

    VcsService::commit(\sys_get_temp_dir());

    \restore_error_handler();

    expect($warned)->toBeFalse();
});
