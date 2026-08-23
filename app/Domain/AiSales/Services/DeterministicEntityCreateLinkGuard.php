<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\Contracts\EntityCreateLinkGuard;
use App\Domain\AiSales\Enums\EntityProposalAction;
use App\Domain\AiSales\Enums\EntityProposalStatus;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Models\Entity;
use App\Models\EntityCandidateProposal;
use App\Models\User;

class DeterministicEntityCreateLinkGuard implements EntityCreateLinkGuard
{
    public function __construct(
        private readonly UnitContextAuthorizationService $authorization,
        private readonly EntityDuplicateCheckService $duplicates,
    ) {}

    public function assertCreateAllowed(User $actor, EntityCandidateProposal $proposal): void
    {
        $this->assertHumanReviewBoundary($actor, $proposal);

        if ($proposal->action !== EntityProposalAction::Create) {
            throw new PolicyViolation('entity_action_mismatch', 'Create and link are separate review actions.');
        }

        if (! $this->authorization->hasPermission($actor, UnitContextAuthorizationService::CREATE_ENTITY)) {
            throw new PolicyViolation('entity_create_forbidden', 'A separate Entity create permission is required.');
        }

        if (trim($proposal->proposed_name) === '') {
            throw new PolicyViolation('entity_required_fields_missing', 'The reviewed Entity name is required.');
        }

        $duplicates = $this->duplicates->candidateIds([
            'name' => $proposal->proposed_name,
            ...($proposal->proposed_attributes ?? []),
        ]);

        if ($duplicates !== []) {
            throw new PolicyViolation('entity_duplicate_review_required', 'Duplicate candidates must be resolved before Entity creation.');
        }
    }

    public function assertLinkAllowed(User $actor, EntityCandidateProposal $proposal, Entity $entity): void
    {
        $this->assertHumanReviewBoundary($actor, $proposal);

        if ($proposal->action !== EntityProposalAction::LinkExisting) {
            throw new PolicyViolation('entity_action_mismatch', 'Create and link are separate review actions.');
        }

        if (! $this->authorization->hasPermission($actor, UnitContextAuthorizationService::LINK_ENTITY)) {
            throw new PolicyViolation('entity_link_forbidden', 'A separate Entity link permission is required.');
        }

        if ((int) $proposal->existing_entity_id !== (int) $entity->id) {
            throw new PolicyViolation('entity_link_subject_mismatch', 'The reviewed Entity differs from the proposal subject.');
        }
    }

    private function assertHumanReviewBoundary(User $actor, EntityCandidateProposal $proposal): void
    {
        if (($actor->status ?? 'active') !== 'active') {
            throw new PolicyViolation('inactive_reviewer', 'An active human reviewer is required.');
        }

        if ($proposal->status !== EntityProposalStatus::ReviewRequired || $proposal->reviewed_at !== null) {
            throw new PolicyViolation('proposal_not_reviewable', 'The proposal is not in a reviewable state.');
        }

        if ((int) $proposal->unit_id !== (int) $proposal->businessContext->unit_id) {
            throw new PolicyViolation('proposal_context_mismatch', 'The proposal context belongs to another Unit.');
        }
    }
}
