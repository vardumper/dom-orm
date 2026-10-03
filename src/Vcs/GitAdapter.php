<?php

declare(strict_types=1);

namespace DOM\ORM\Vcs;

use CzProject\GitPhp\Git;

final class GitAdapter implements VcsAdapterInterface
{
    public function __construct(
        private readonly string $binary = 'git',
    ) {
    }

    public function checkInstalled(): bool
    {
        $result = @\shell_exec(\escapeshellarg($this->binary) . ' --version 2>&1');

        return $result !== null && \str_starts_with(\trim($result), $this->binary . ' version');
    }

    public function addAll(string $repoPath): void
    {
        $git = new Git();
        $repo = $git->open($repoPath);
        $repo->addAllChanges();
    }

    public function commit(string $repoPath, string $message): void
    {
        $git = new Git();
        $repo = $git->open($repoPath);

        if (!$repo->hasChanges()) {
            return;
        }

        $repo->commit($message);
    }

    public function push(string $repoPath): void
    {
        $git = new Git();
        $repo = $git->open($repoPath);
        $repo->push();
    }
}
