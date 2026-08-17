<?php

namespace App\Domain\AiSales\Outreach;

use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\UnitRoleCode;
use App\Domain\AiSales\Services\UnitContextAuthorizationService;
use App\Models\Unit;
use App\Models\UnitBusinessContext;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class OutreachAuthorizationService
{
    public const VIEW = 'ai_sales.outreach.view';

    public const DRAFT = 'ai_sales.outreach.draft';

    public const REVIEW = 'ai_sales.outreach.review';

    public const REVIEW_CLAIMS = 'ai_sales.outreach.claims.review';

    public const VIEW_PERMISSIONS = 'ai_sales.communication_permissions.view';

    public const MANAGE_PERMISSIONS = 'ai_sales.communication_permissions.manage';

    public const MANAGE_SUPPRESSIONS = 'ai_sales.communication_suppressions.manage';

    public function __construct(private readonly UnitContextAuthorizationService $contexts) {}

    public function can(User $actor, string $permission, UnitBusinessContext $context): bool
    {
        return $context->lane === BusinessLane::Sales
            && in_array($context->role_code, [UnitRoleCode::Customer, UnitRoleCode::ProspectiveCustomer], true)
            && $this->contexts->canViewLane($actor, BusinessLane::Sales)
            && $this->contexts->hasPermission($actor, self::VIEW)
            && $this->contexts->hasPermission($actor, $permission);
    }

    public function authorize(User $actor, string $permission, Unit $unit, UnitBusinessContext $context): void
    {
        $this->contexts->assertContextBelongsToUnit($unit, $context);

        if (! $this->can($actor, $permission, $context)) {
            throw new AuthorizationException('Outreach action is not authorized for this Unit sales context.');
        }
    }
}
