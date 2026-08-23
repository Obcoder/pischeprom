<?php

namespace App\Domain\AiSales\Outreach\Canary;

use App\Domain\AiSales\Contracts\GitRepositoryStateInspectorInterface;
use App\Domain\AiSales\Exceptions\PolicyViolation;

final class OutreachCanaryRepositoryGuard
{
    public const EXPECTED_BRANCH = 'feature/ai-sales-agents';

    public const STAGE_12_COMMIT = '8d89d8acc46879fd8cf7399d8622195ea518455b';

    public const STAGE_12B_SUBJECT = 'test(ai-sales): add bounded live synthetic outreach canary';

    public function __construct(
        private readonly GitRepositoryStateInspectorInterface $repositoryState,
    ) {}

    public function assertExpectedWorktree(): string
    {
        $state = $this->repositoryState->inspect(self::STAGE_12_COMMIT);

        if (! hash_equals(self::EXPECTED_BRANCH, $state->branch)) {
            throw new PolicyViolation('stage12b_branch_mismatch', 'Stage 12B requires the exact approved branch.');
        }
        if (! $state->baseIsAncestor) {
            throw new PolicyViolation('stage12b_stage12_not_ancestor', 'The accepted Stage 12 commit is not an ancestor of HEAD.');
        }
        if ($state->stagedChanges !== 0) {
            throw new PolicyViolation('stage12b_staged_changes_blocked', 'Staged files block the live canary.');
        }
        if ($state->modifiedChanges !== 0) {
            throw new PolicyViolation('stage12b_modified_changes_blocked', 'Modified files block the live canary.');
        }
        if ($state->untrackedChanges !== 0) {
            throw new PolicyViolation('stage12b_untracked_changes_blocked', 'Untracked files block the live canary.');
        }
        if (count($state->commitsAfterBase) !== 1) {
            throw new PolicyViolation('stage12b_commit_count_invalid', 'Exactly one Stage 12B canary commit is required after Stage 12.');
        }

        $commit = $state->commitsAfterBase[0];
        if (! hash_equals(self::STAGE_12B_SUBJECT, $commit['subject'])) {
            throw new PolicyViolation('stage12b_commit_subject_invalid', 'The Stage 12B commit subject is not approved.');
        }
        if (! hash_equals($commit['hash'], $state->head)) {
            throw new PolicyViolation('stage12b_head_not_canary_commit', 'HEAD must be the single approved Stage 12B canary commit.');
        }

        return 'clean_committed_stage12b';
    }
}
