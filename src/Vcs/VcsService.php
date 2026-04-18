<?php

declare(strict_types=1);

namespace DOM\ORM\Vcs;

use function DOM\ORM\getConfig;

/**
 * Orchestrates VCS commit (and optionally push) after every XML write.
 *
 * Enabled by:
 *   'versioning'          => true,
 *   'version_control'     => 'git',       // or 'hg'
 *   'version_control_push'=> 'on_persist', // or 'manual' (default) — skips push
 *
 * The commit message is derived from the first frame in the call stack whose
 * class lives outside of the DOM\ORM\* internal namespaces, giving a message
 * such as "DOM-ORM: App\Repository\UserRepository::save".
 */
final class VcsService
{
    /**
     * Internal DOM\ORM namespaces that should be skipped when resolving the
     * commit message from the call stack.
     */
    private const INTERNAL_PREFIXES = [
        'DOM\\ORM\\Traits\\',
        'DOM\\ORM\\Storage\\',
        'DOM\\ORM\\Vcs\\',
        'DOM\\ORM\\Command\\',
    ];

    public static function isEnabled(): bool
    {
        return (bool)getConfig()->get('dom-orm.versioning');
    }

    /**
     * Performs: checkInstalled → addAll → commit → push.
     *
     * Failures are non-fatal: a PHP E_USER_WARNING is emitted so the XML
     * write that already succeeded is not rolled back.
     */
    public static function commit(string $repoPath): void
    {
        if (!self::isEnabled()) {
            return;
        }

        $vcs = getConfig()->get('dom-orm.version_control');
        $adapter = self::createAdapter((string)$vcs);

        if (!$adapter->checkInstalled()) {
            \trigger_error(
                \sprintf('DOM-ORM versioning: "%s" binary not found on PATH. Skipping VCS commit.', $vcs),
                \E_USER_WARNING,
            );

            return;
        }

        $message = self::resolveCommitMessage();

        try {
            $adapter->addAll($repoPath);
            $adapter->commit($repoPath, $message);

            if (getConfig()->get('dom-orm.version_control_push') === 'on_persist') {
                $adapter->push($repoPath);
            }
        } catch (\Throwable $e) {
            \trigger_error(
                \sprintf('DOM-ORM versioning: VCS operation failed — %s', $e->getMessage()),
                \E_USER_WARNING,
            );
        }
    }

    private static function createAdapter(string $vcs): VcsAdapterInterface
    {
        return match ($vcs) {
            'hg' => new HgAdapter(),
            default => new GitAdapter(),
        };
    }

    /**
     * Walks the call stack and returns the first frame whose class is not an
     * internal DOM\ORM component.  Falls back to a generic message.
     */
    private static function resolveCommitMessage(): string
    {
        $frames = \debug_backtrace(\DEBUG_BACKTRACE_IGNORE_ARGS, 20);

        foreach ($frames as $frame) {
            $class = $frame['class'] ?? null;
            if ($class === null) {
                continue;
            }

            $internal = false;
            foreach (self::INTERNAL_PREFIXES as $prefix) {
                if (\str_starts_with($class, $prefix)) {
                    $internal = true;
                    break;
                }
            }

            if (!$internal) {
                return \sprintf('DOM-ORM: %s::%s', $class, $frame['function']);
            }
        }

        return 'DOM-ORM: write operation';
    }
}
