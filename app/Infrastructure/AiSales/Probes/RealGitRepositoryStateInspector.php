<?php

namespace App\Infrastructure\AiSales\Probes;

use App\Domain\AiSales\Contracts\GitRepositoryStateInspectorInterface;
use App\Domain\AiSales\Probes\GitRepositoryState;
use App\Domain\AiSales\Search\SearchProviderException;
use Symfony\Component\Process\Process;

final class RealGitRepositoryStateInspector implements GitRepositoryStateInspectorInterface
{
    public function inspect(string $baseCommit): GitRepositoryState
    {
        $branch = trim($this->git(['branch', '--show-current']));
        $head = trim($this->git(['rev-parse', 'HEAD']));
        $baseIsAncestor = $this->isAncestor($baseCommit);
        $commits = $baseIsAncestor ? $this->commitsAfter($baseCommit) : [];
        [$stagedChanges, $modifiedChanges, $untrackedChanges] = $this->changeCounts();

        return new GitRepositoryState(
            branch: $branch,
            head: $head,
            baseIsAncestor: $baseIsAncestor,
            commitsAfterBase: $commits,
            stagedChanges: $stagedChanges,
            modifiedChanges: $modifiedChanges,
            untrackedChanges: $untrackedChanges,
        );
    }

    private function isAncestor(string $baseCommit): bool
    {
        $process = $this->process(['merge-base', '--is-ancestor', $baseCommit, 'HEAD']);
        $process->run();

        return match ($process->getExitCode()) {
            0 => true,
            1 => false,
            default => throw new SearchProviderException('probe_policy', 'stage09b_git_preflight_failed'),
        };
    }

    /** @return list<array{hash: string, subject: string}> */
    private function commitsAfter(string $baseCommit): array
    {
        $hashes = array_values(array_filter(preg_split(
            '/\R/u',
            trim($this->git(['rev-list', '--reverse', $baseCommit.'..HEAD'])),
        ) ?: []));

        return array_map(fn (string $hash): array => [
            'hash' => $hash,
            'subject' => rtrim($this->git(['show', '-s', '--format=%s', $hash])),
        ], $hashes);
    }

    /** @return array{0: int, 1: int, 2: int} */
    private function changeCounts(): array
    {
        $staged = 0;
        $modified = 0;
        $untracked = 0;
        $records = explode("\0", $this->git([
            'status', '--porcelain=v1', '-z', '--untracked-files=all',
        ]));

        foreach ($records as $record) {
            if (strlen($record) < 3) {
                continue;
            }
            $indexStatus = $record[0];
            $worktreeStatus = $record[1];
            if ($indexStatus === '?' && $worktreeStatus === '?') {
                $untracked++;

                continue;
            }
            if ($indexStatus !== ' ') {
                $staged++;
            }
            if ($worktreeStatus !== ' ') {
                $modified++;
            }
        }

        return [$staged, $modified, $untracked];
    }

    /** @param list<string> $arguments */
    private function git(array $arguments): string
    {
        $process = $this->process($arguments);
        $process->run();
        if (! $process->isSuccessful()) {
            throw new SearchProviderException('probe_policy', 'stage09b_git_preflight_failed');
        }

        return $process->getOutput();
    }

    /** @param list<string> $arguments */
    private function process(array $arguments): Process
    {
        $process = new Process(['git', ...$arguments], base_path());
        $process->setTimeout(15);

        return $process;
    }
}
