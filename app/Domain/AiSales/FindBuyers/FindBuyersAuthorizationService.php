<?php

namespace App\Domain\AiSales\FindBuyers;

use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Services\ProspectingAuthorizationService;
use App\Models\User;

final class FindBuyersAuthorizationService
{
    public function __construct(private readonly ProspectingAuthorizationService $authorization) {}

    public function authorizeLaunch(User $actor): void
    {
        $this->authorization->authorize($actor, ProspectingAuthorizationService::MANAGE_JOBS, BusinessLane::Sales);
        $this->authorization->authorize($actor, ProspectingAuthorizationService::PLAN_SEARCH, BusinessLane::Sales);
    }

    public function authorizeView(User $actor): void
    {
        $this->authorization->authorize($actor, ProspectingAuthorizationService::VIEW, BusinessLane::Sales);
    }

    public function authorizeManage(User $actor): void
    {
        $this->authorization->authorize($actor, ProspectingAuthorizationService::MANAGE_JOBS, BusinessLane::Sales);
    }

    public function authorizePlan(User $actor): void
    {
        $this->authorizeManage($actor);
        $this->authorization->authorize($actor, ProspectingAuthorizationService::PLAN_SEARCH, BusinessLane::Sales);
    }

    public function canViewScoring(User $actor): bool
    {
        return $this->authorization->can($actor, ProspectingAuthorizationService::VIEW_SCORING, BusinessLane::Sales);
    }
}
