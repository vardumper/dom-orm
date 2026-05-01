<?php

declare(strict_types=1);

use DOM\ORM\Vcs\VcsService;

it('isEnabled follows env override for versioning', function (): void {
    $oldVersioning = getenv('DOM_ORM_VERSIONING');

    try {
        putenv('DOM_ORM_VERSIONING=true');
        expect(VcsService::isEnabled())->toBeTrue();

        putenv('DOM_ORM_VERSIONING=false');
        expect(VcsService::isEnabled())->toBeFalse();
    } finally {
        if ($oldVersioning === false) {
            putenv('DOM_ORM_VERSIONING');
        } else {
            putenv('DOM_ORM_VERSIONING=' . $oldVersioning);
        }
    }
});

it('commit warns and returns when configured git binary is not available', function (): void {
    $oldVersioning = getenv('DOM_ORM_VERSIONING');
    $oldControl = getenv('DOM_ORM_VERSION_CONTROL');
    $oldPath = getenv('PATH');
    $warnings = [];

    set_error_handler(static function (int $errno, string $errstr) use (&$warnings): bool {
        if ($errno === E_USER_WARNING) {
            $warnings[] = $errstr;

            return true;
        }

        return false;
    });

    try {
        putenv('DOM_ORM_VERSIONING=true');
        putenv('DOM_ORM_VERSION_CONTROL=git');
        putenv('PATH=/__dom_orm_missing_path__');

        VcsService::commit(getcwd());
    } finally {
        restore_error_handler();

        if ($oldVersioning === false) {
            putenv('DOM_ORM_VERSIONING');
        } else {
            putenv('DOM_ORM_VERSIONING=' . $oldVersioning);
        }

        if ($oldControl === false) {
            putenv('DOM_ORM_VERSION_CONTROL');
        } else {
            putenv('DOM_ORM_VERSION_CONTROL=' . $oldControl);
        }

        if ($oldPath === false) {
            putenv('PATH');
        } else {
            putenv('PATH=' . $oldPath);
        }
    }

    expect($warnings)->toHaveCount(1)
        ->and($warnings[0])->toContain('binary not found')
        ->and($warnings[0])->toContain('git');
});

it('commit warns and returns when configured hg binary is not available', function (): void {
    $oldVersioning = getenv('DOM_ORM_VERSIONING');
    $oldControl = getenv('DOM_ORM_VERSION_CONTROL');
    $oldPath = getenv('PATH');
    $warnings = [];

    set_error_handler(static function (int $errno, string $errstr) use (&$warnings): bool {
        if ($errno === E_USER_WARNING) {
            $warnings[] = $errstr;

            return true;
        }

        return false;
    });

    try {
        putenv('DOM_ORM_VERSIONING=true');
        putenv('DOM_ORM_VERSION_CONTROL=hg');
        putenv('PATH=/__dom_orm_missing_path__');

        VcsService::commit(getcwd());
    } finally {
        restore_error_handler();

        if ($oldVersioning === false) {
            putenv('DOM_ORM_VERSIONING');
        } else {
            putenv('DOM_ORM_VERSIONING=' . $oldVersioning);
        }

        if ($oldControl === false) {
            putenv('DOM_ORM_VERSION_CONTROL');
        } else {
            putenv('DOM_ORM_VERSION_CONTROL=' . $oldControl);
        }

        if ($oldPath === false) {
            putenv('PATH');
        } else {
            putenv('PATH=' . $oldPath);
        }
    }

    expect($warnings)->toHaveCount(1)
        ->and($warnings[0])->toContain('binary not found')
        ->and($warnings[0])->toContain('hg');
});
