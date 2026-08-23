<?php

namespace App\Policies\AiSales;

use App\Domain\AiSales\Services\ProspectingAuthorizationService;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ProspectingScoreSnapshotPolicy
{
    public function __construct(private readonly ProspectingAuthorizationService $authorization) {}

    public function view(User $user, Model $snapshot): bool
    {
        $context = $snapshot->businessContext()->select(['id', 'lane'])->first();

        return $context !== null
            && $this->authorization->can($user, ProspectingAuthorizationService::VIEW_SCORING, $context->lane);
    }

    public function review(User $user, Model $snapshot): bool
    {
        $context = $snapshot->businessContext()->select(['id', 'lane'])->first();

        return $context !== null
            && $this->authorization->can($user, ProspectingAuthorizationService::REVIEW_SCORING, $context->lane);
    }

    public function override(User $user, Model $snapshot): bool
    {
        $context = $snapshot->businessContext()->select(['id', 'lane'])->first();

        return $context !== null
            && $this->authorization->can($user, ProspectingAuthorizationService::OVERRIDE_SCORING, $context->lane);
    }
}
