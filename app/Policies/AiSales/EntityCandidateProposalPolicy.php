<?php

namespace App\Policies\AiSales;

use App\Domain\AiSales\Enums\EntityProposalAction;
use App\Domain\AiSales\Services\UnitContextAuthorizationService;
use App\Models\EntityCandidateProposal;
use App\Models\User;

class EntityCandidateProposalPolicy
{
    public function __construct(private readonly UnitContextAuthorizationService $authorization) {}

    public function view(User $user, EntityCandidateProposal $proposal): bool
    {
        return $this->authorization->hasPermission($user, UnitContextAuthorizationService::PROPOSE_ENTITY)
            && $this->authorization->canViewLane($user, $proposal->businessContext->lane);
    }

    public function approve(User $user, EntityCandidateProposal $proposal): bool
    {
        $permission = $proposal->action === EntityProposalAction::Create
            ? UnitContextAuthorizationService::CREATE_ENTITY
            : UnitContextAuthorizationService::LINK_ENTITY;

        return $this->view($user, $proposal)
            && $this->authorization->hasPermission($user, $permission);
    }
}
