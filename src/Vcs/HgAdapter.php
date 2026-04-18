<?php

declare(strict_types=1);

namespace DOM\ORM\Vcs;

final class HgAdapter implements VcsAdapterInterface
{
    public function checkInstalled(): bool
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = \proc_open(['hg', '--version'], $descriptors, $pipes);
        if (!\is_resource($process)) {
            return false;
        }

        \fclose($pipes[0]);
        $output = \stream_get_contents($pipes[1]);
        \fclose($pipes[1]);
        \fclose($pipes[2]);
        $exitCode = \proc_close($process);

        return $exitCode === 0 && $output !== false && \str_contains($output, 'Mercurial');
    }

    public function addAll(string $repoPath): void
    {
        $this->run($repoPath, ['hg', 'addremove']);
    }

    public function commit(string $repoPath, string $message): void
    {
        $status = $this->captureOutput($repoPath, ['hg', 'status']);
        if (\trim($status) === '') {
            return;
        }

        $this->run($repoPath, ['hg', 'commit', '-m', $message]);
    }

    public function push(string $repoPath): void
    {
        $this->run($repoPath, ['hg', 'push']);
    }

    /**
     * @param list<string> $command
     */
    private function run(string $repoPath, array $command): void
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = \proc_open($command, $descriptors, $pipes, $repoPath);
        if (!\is_resource($process)) {
            throw new \RuntimeException(\sprintf('Failed to start process: %s', \implode(' ', $command)));
        }

        \fclose($pipes[0]);
        $stderr = \stream_get_contents($pipes[2]);
        \fclose($pipes[1]);
        \fclose($pipes[2]);
        $exitCode = \proc_close($process);

        if ($exitCode !== 0) {
            throw new \RuntimeException(\sprintf(
                'hg command failed (exit %d): %s',
                $exitCode,
                \trim((string)$stderr),
            ));
        }
    }

    /**
     * @param list<string> $command
     */
    private function captureOutput(string $repoPath, array $command): string
    {
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = \proc_open($command, $descriptors, $pipes, $repoPath);
        if (!\is_resource($process)) {
            return '';
        }

        \fclose($pipes[0]);
        $output = \stream_get_contents($pipes[1]);
        \fclose($pipes[1]);
        \fclose($pipes[2]);
        \proc_close($process);

        return $output !== false ? $output : '';
    }
}
