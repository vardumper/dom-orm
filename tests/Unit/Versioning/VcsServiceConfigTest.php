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

it('commit catches adapter failure and resolves a callsite commit message', function (): void {
    $oldVersioning = getenv('DOM_ORM_VERSIONING');
    $oldControl = getenv('DOM_ORM_VERSION_CONTROL');
    $oldPath = getenv('PATH');
    $warnings = [];

    $tempDir = sys_get_temp_dir() . '/dom_orm_vcs_' . uniqid('', true);
    $binDir = $tempDir . '/bin';
    $repoDir = $tempDir . '/repo';
    $logFile = $tempDir . '/hg.log';

    mkdir($binDir, 0777, true);
    mkdir($repoDir, 0777, true);

    $fakeHg = $binDir . '/hg';
    $scriptTemplate = <<<'SH'
#!/bin/sh
echo "$@" >> "__LOG_FILE__"

case "$1" in
    --version)
        echo "Mercurial Distributed SCM (version 6.8)"
        exit 0
        ;;
    addremove)
        exit 0
        ;;
    status)
        echo "M storage/data.xml"
        exit 0
        ;;
    commit)
        echo "simulated commit failure" >&2
        exit 1
        ;;
    push)
        exit 0
        ;;
esac

exit 0
SH;

    $script = str_replace('__LOG_FILE__', $logFile, $scriptTemplate);
    file_put_contents($fakeHg, $script);
    chmod($fakeHg, 0755);

    set_error_handler(static function (int $errno, string $errstr) use (&$warnings): bool {
        if ($errno === E_USER_WARNING) {
            $warnings[] = $errstr;

            return true;
        }

        return false;
    });

    try {
        if (!class_exists('DOM\\ORM\\Command\\VcsCommitInvoker', false)) {
            eval(<<<'PHP'
namespace DOM\ORM\Command;

final class VcsCommitInvoker
{
    public static function invoke(string $repoPath): void
    {
        \DOM\ORM\Vcs\VcsService::commit($repoPath);
    }
}
PHP);
        }

        putenv('DOM_ORM_VERSIONING=true');
        putenv('DOM_ORM_VERSION_CONTROL=hg');
        putenv('PATH=' . $binDir);

        \DOM\ORM\Command\VcsCommitInvoker::invoke($repoDir);
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

    $loggedCommands = file_exists($logFile) ? (string)file_get_contents($logFile) : '';

    expect($warnings)->toHaveCount(1)
        ->and($warnings[0])->toContain('VCS operation failed')
        ->and($warnings[0])->toContain('simulated commit failure')
        ->and($loggedCommands)->toContain('commit -m')
        ->and($loggedCommands)->toContain('DOM-ORM: ');
});

it('commit returns immediately when versioning is disabled', function (): void {
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
        putenv('DOM_ORM_VERSIONING=false');
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

    expect($warnings)->toBe([]);
});

it('commit pushes when on_persist is configured', function (): void {
    $oldVersioning = getenv('DOM_ORM_VERSIONING');
    $oldControl = getenv('DOM_ORM_VERSION_CONTROL');
    $oldPush = getenv('DOM_ORM_VERSION_CONTROL_PUSH');
    $oldPath = getenv('PATH');
    $warnings = [];

    $tempDir = sys_get_temp_dir() . '/dom_orm_vcs_' . uniqid('', true);
    $binDir = $tempDir . '/bin';
    $repoDir = $tempDir . '/repo';
    $logFile = $tempDir . '/hg.log';

    mkdir($binDir, 0777, true);
    mkdir($repoDir, 0777, true);

    $fakeHg = $binDir . '/hg';
    $scriptTemplate = <<<'SH'
#!/bin/sh
echo "$@" >> "__LOG_FILE__"

case "$1" in
    --version)
        echo "Mercurial Distributed SCM (version 6.8)"
        exit 0
        ;;
    addremove)
        exit 0
        ;;
    status)
        echo "M storage/data.xml"
        exit 0
        ;;
    commit)
        exit 0
        ;;
    push)
        exit 0
        ;;
esac

exit 0
SH;

    $script = str_replace('__LOG_FILE__', $logFile, $scriptTemplate);
    file_put_contents($fakeHg, $script);
    chmod($fakeHg, 0755);

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
        putenv('DOM_ORM_VERSION_CONTROL_PUSH=on_persist');
        putenv('PATH=' . $binDir);

        VcsService::commit($repoDir);
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

        if ($oldPush === false) {
            putenv('DOM_ORM_VERSION_CONTROL_PUSH');
        } else {
            putenv('DOM_ORM_VERSION_CONTROL_PUSH=' . $oldPush);
        }

        if ($oldPath === false) {
            putenv('PATH');
        } else {
            putenv('PATH=' . $oldPath);
        }
    }

    $loggedCommands = file_exists($logFile) ? (string)file_get_contents($logFile) : '';

    expect($warnings)->toBe([])
        ->and($loggedCommands)->toContain('addremove')
        ->and($loggedCommands)->toContain('commit -m')
        ->and($loggedCommands)->toContain('push');
});

it('resolveCommitMessage falls back to generic message with only internal frames', function (): void {
    if (!class_exists('DOM\\ORM\\Traits\\InternalFrameDriver', false)) {
        eval(<<<'PHP'
namespace DOM\ORM\Traits;

final class InternalFrameDriver
{
    public static function resolveThroughInternalFrames(int $depth): string
    {
        if ($depth > 0) {
            return self::resolveThroughInternalFrames($depth - 1);
        }

        $resolver = \Closure::bind(static function (): string {
            return self::resolveCommitMessage();
        }, null, \DOM\ORM\Vcs\VcsService::class);

        return $resolver();
    }
}
PHP);
    }

    $message = \DOM\ORM\Traits\InternalFrameDriver::resolveThroughInternalFrames(25);

    expect($message)->toBe('DOM-ORM: write operation');
});
