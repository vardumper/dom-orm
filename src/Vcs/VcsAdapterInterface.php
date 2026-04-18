<?php

declare(strict_types=1);

namespace DOM\ORM\Vcs;

interface VcsAdapterInterface
{
    /**
     * Returns true when the VCS binary is available on the system PATH.
     */
    public function checkInstalled(): bool;

    /**
     * Stages all changes in the given repository path.
     */
    public function addAll(string $repoPath): void;

    /**
     * Creates a commit with the given message in the given repository path.
     */
    public function commit(string $repoPath, string $message): void;

    /**
     * Pushes committed changes to the configured remote.
     */
    public function push(string $repoPath): void;
}
