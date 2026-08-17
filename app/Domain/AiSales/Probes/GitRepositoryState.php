<?php

namespace App\Domain\AiSales\Probes;

final readonly class GitRepositoryState
{
    /**
     * @param  list<array{hash: string, subject: string}>  $commitsAfterBase
     */
    public function __construct(
        public string $branch,
        public string $head,
        public bool $baseIsAncestor,
        public array $commitsAfterBase,
        public int $stagedChanges,
        public int $modifiedChanges,
        public int $untrackedChanges,
    ) {}
}
