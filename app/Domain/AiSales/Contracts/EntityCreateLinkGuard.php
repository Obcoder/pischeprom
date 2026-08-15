<?php

namespace App\Domain\AiSales\Contracts;

use App\Models\Entity;
use App\Models\EntityCandidateProposal;
use App\Models\User;

interface EntityCreateLinkGuard
{
    public function assertCreateAllowed(User $actor, EntityCandidateProposal $proposal): void;

    public function assertLinkAllowed(User $actor, EntityCandidateProposal $proposal, Entity $entity): void;
}
