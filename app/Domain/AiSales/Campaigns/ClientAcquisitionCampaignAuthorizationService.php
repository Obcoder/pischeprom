<?php

namespace App\Domain\AiSales\Campaigns;

use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Services\UnitContextAuthorizationService;
use App\Models\ClientAcquisitionCampaign;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Throwable;

final class ClientAcquisitionCampaignAuthorizationService
{
    public const VIEW = 'ai_sales.campaigns.view';

    public const MANAGE = 'ai_sales.campaigns.manage';

    public const REVIEW = 'ai_sales.campaigns.review';

    public const OPERATE = 'ai_sales.campaigns.operate';

    public const MANAGE_AUTOMATION = 'ai_sales.campaigns.automation.manage';

    public const VIEW_METRICS = 'ai_sales.campaigns.metrics.view';

    public function __construct(private readonly UnitContextAuthorizationService $contexts) {}

    public function can(User $actor, string $permission): bool
    {
        if (! $this->contexts->canViewLane($actor, BusinessLane::Sales)
            || ! $this->contexts->hasPermission($actor, $permission)) {
            return false;
        }
        if ($permission !== self::MANAGE_AUTOMATION) {
            return true;
        }

        try {
            return $actor->hasRole('admin', 'crm');
        } catch (Throwable) {
            return false;
        }
    }

    public function authorize(User $actor, string $permission): void
    {
        if (! $this->can($actor, $permission)) {
            throw new AuthorizationException('Client-acquisition campaign action is not authorized.');
        }
    }

    public function canAccess(User $actor, ClientAcquisitionCampaign $campaign): bool
    {
        return $this->isAdmin($actor)
            || (int) $campaign->owner_user_id === (int) $actor->id
            || (int) $campaign->reviewer_user_id === (int) $actor->id
            || (int) $campaign->approved_by === (int) $actor->id;
    }

    public function isAdmin(User $actor): bool
    {
        try {
            return $actor->hasRole('admin', 'crm');
        } catch (Throwable) {
            return false;
        }
    }
}
