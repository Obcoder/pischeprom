<?php

namespace App\Domain\AiSales\FindBuyers\Canary;

use App\Domain\AiSales\Contracts\GitRepositoryStateInspectorInterface;
use App\Domain\AiSales\Search\SearchProviderException;

final class FindBuyersCanaryRepositoryGuard
{
    public const EXPECTED_BRANCH = 'feature/ai-sales-agents';

    public const STAGE_11_COMMIT = '94cd45665057a45345c8ae62b207c320676e0d2e';

    public const STAGE_11B_SUBJECT = 'test(ai-sales): add controlled Find Buyers staging canary';

    public function __construct(
        private readonly GitRepositoryStateInspectorInterface $repositoryState,
    ) {}

    public function assertExpectedWorktree(): string
    {
        $state = $this->repositoryState->inspect(self::STAGE_11_COMMIT);
        if (! hash_equals(self::EXPECTED_BRANCH, $state->branch)) {
            throw new SearchProviderException('canary_policy', 'stage11b_branch_mismatch');
        }
        if (! $state->baseIsAncestor) {
            throw new SearchProviderException('canary_policy', 'stage11b_stage11_not_ancestor');
        }
        if ($state->stagedChanges !== 0) {
            throw new SearchProviderException('canary_policy', 'stage11b_staged_changes_blocked');
        }
        if ($state->modifiedChanges !== 0) {
            throw new SearchProviderException('canary_policy', 'stage11b_modified_changes_blocked');
        }
        if ($state->untrackedChanges !== 0) {
            throw new SearchProviderException('canary_policy', 'stage11b_untracked_changes_blocked');
        }
        if (count($state->commitsAfterBase) !== 1) {
            throw new SearchProviderException('canary_policy', 'stage11b_commit_count_invalid');
        }

        $canaryCommit = $state->commitsAfterBase[0];
        if (! hash_equals(self::STAGE_11B_SUBJECT, $canaryCommit['subject'])) {
            throw new SearchProviderException('canary_policy', 'stage11b_commit_subject_invalid');
        }
        if (! hash_equals($canaryCommit['hash'], $state->head)) {
            throw new SearchProviderException('canary_policy', 'stage11b_head_not_canary_commit');
        }

        return 'clean_committed_stage11b';
    }
}
