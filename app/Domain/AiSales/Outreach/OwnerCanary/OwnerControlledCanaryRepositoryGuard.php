<?php

namespace App\Domain\AiSales\Outreach\OwnerCanary;

use App\Domain\AiSales\Contracts\GitRepositoryStateInspectorInterface;
use App\Domain\AiSales\Exceptions\PolicyViolation;

final class OwnerControlledCanaryRepositoryGuard
{
    public const EXPECTED_BRANCH = 'feature/ai-sales-agents';

    public const STAGE_13_COMMIT = '60865ca0040f78ca9b452453e79b0cde0221832a';

    public const STAGE_13B_SUBJECT = 'test(ai-sales): add one-message owner-controlled outreach canary';

    public function __construct(
        private readonly GitRepositoryStateInspectorInterface $repositoryState,
    ) {}

    public function assertExpectedWorktree(): string
    {
        $state = $this->repositoryState->inspect(self::STAGE_13_COMMIT);

        if (! hash_equals(self::EXPECTED_BRANCH, $state->branch)) {
            throw new PolicyViolation('stage13b_branch_mismatch', 'Stage 13B requires the exact approved branch.');
        }
        if (! $state->baseIsAncestor) {
            throw new PolicyViolation('stage13b_stage13_not_ancestor', 'The accepted Stage 13 commit is not an ancestor of HEAD.');
        }
        if ($state->stagedChanges !== 0) {
            throw new PolicyViolation('stage13b_staged_changes_blocked', 'Staged files block the owner-controlled canary.');
        }
        if ($state->modifiedChanges !== 0) {
            throw new PolicyViolation('stage13b_modified_changes_blocked', 'Modified files block the owner-controlled canary.');
        }
        if ($state->untrackedChanges !== 0) {
            throw new PolicyViolation('stage13b_untracked_changes_blocked', 'Untracked files block the owner-controlled canary.');
        }
        if (count($state->commitsAfterBase) !== 1) {
            throw new PolicyViolation('stage13b_commit_count_invalid', 'Exactly one Stage 13B canary commit is required after Stage 13.');
        }

        $commit = $state->commitsAfterBase[0];
        if (! hash_equals(self::STAGE_13B_SUBJECT, $commit['subject'])) {
            throw new PolicyViolation('stage13b_commit_subject_invalid', 'The Stage 13B commit subject is not approved.');
        }
        if (! hash_equals($commit['hash'], $state->head)) {
            throw new PolicyViolation('stage13b_head_not_canary_commit', 'HEAD must be the single approved Stage 13B canary commit.');
        }

        return 'clean_committed_stage13b';
    }
}
