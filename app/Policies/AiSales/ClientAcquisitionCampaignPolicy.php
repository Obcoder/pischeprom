<?php

namespace App\Policies\AiSales;

use App\Domain\AiSales\Campaigns\ClientAcquisitionCampaignAuthorizationService as Authorization;
use App\Models\ClientAcquisitionCampaign;
use App\Models\User;

final class ClientAcquisitionCampaignPolicy
{
    public function __construct(private readonly Authorization $authorization) {}

    public function viewAny(User $user): bool
    {
        return $this->authorization->can($user, Authorization::VIEW);
    }

    public function create(User $user): bool
    {
        return $this->authorization->can($user, Authorization::MANAGE);
    }

    public function view(User $user, ClientAcquisitionCampaign $campaign): bool
    {
        return $this->authorization->can($user, Authorization::VIEW)
            && $this->authorization->canAccess($user, $campaign);
    }

    public function update(User $user, ClientAcquisitionCampaign $campaign): bool
    {
        return $this->authorization->can($user, Authorization::MANAGE)
            && ($this->authorization->isAdmin($user) || (int) $campaign->owner_user_id === (int) $user->id);
    }

    public function review(User $user, ClientAcquisitionCampaign $campaign): bool
    {
        return $this->authorization->can($user, Authorization::REVIEW)
            && ($this->authorization->isAdmin($user)
                || $campaign->reviewer_user_id === null
                || (int) $campaign->reviewer_user_id === (int) $user->id);
    }

    public function operate(User $user, ClientAcquisitionCampaign $campaign): bool
    {
        return $this->authorization->can($user, Authorization::OPERATE)
            && $this->authorization->canAccess($user, $campaign);
    }

    public function manageAutomation(User $user, ClientAcquisitionCampaign $campaign): bool
    {
        return $this->operate($user, $campaign)
            && $this->authorization->can($user, Authorization::MANAGE_AUTOMATION);
    }

    public function viewMetrics(User $user, ClientAcquisitionCampaign $campaign): bool
    {
        return $this->authorization->can($user, Authorization::VIEW_METRICS)
            && $this->authorization->canAccess($user, $campaign);
    }
}
