<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\DataClassification;
use App\Domain\AiSales\Enums\UnitRoleCode;
use App\Domain\AiSales\Enums\UnitVisibilityScope;
use App\Models\Unit;
use App\Models\UnitBusinessContext;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

class UnitContextAuthorizationService
{
    public const VIEW = 'ai_sales.view';

    public const MANAGE_ROLES = 'ai_sales.unit_roles.manage';

    public const MANAGE_CONTEXTS = 'ai_sales.contexts.manage';

    public const MANAGE_ALIASES = 'ai_sales.aliases.manage';

    public const MANAGE_OBSERVATIONS = 'ai_sales.observation.manage';

    public const VERIFY_OBSERVATIONS = 'ai_sales.observation.verify';

    public const PROMOTE_OBSERVATIONS = 'ai_sales.observation.promote';

    public const PROPOSE_ENTITY = 'ai_sales.entity.propose';

    public const CREATE_ENTITY = 'ai_sales.entity.create';

    public const LINK_ENTITY = 'ai_sales.entity.link';

    public const MERGE_ENTITY = 'ai_sales.entity.merge';

    public const VIEW_CLASSIFICATIONS = 'ai_sales.classifications.view_internal';

    public const VIEW_SALES = 'ai_sales.sales.view';

    public const VIEW_PROCUREMENT = 'ai_sales.procurement.view';

    public const VIEW_AUDIT = 'ai_sales.audit.view';

    public function hasPermission(?User $user, string $permission): bool
    {
        if (! $user || ($user->status ?? 'active') !== 'active') {
            return false;
        }

        try {
            return $user->hasRole('admin', 'crm') || $user->hasPermissionTo($permission, 'crm');
        } catch (Throwable) {
            return false;
        }
    }

    public function canViewLane(?User $user, BusinessLane $lane): bool
    {
        if (! $this->hasPermission($user, self::VIEW)) {
            return false;
        }

        return match ($lane) {
            BusinessLane::Sales => $this->hasPermission($user, self::VIEW_SALES),
            BusinessLane::Procurement => $this->hasPermission($user, self::VIEW_PROCUREMENT),
            BusinessLane::Logistics, BusinessLane::Service, BusinessLane::Internal => $this->hasPermission($user, self::VIEW_CLASSIFICATIONS),
        };
    }

    public function canViewRole(?User $user, UnitRoleCode $role): bool
    {
        return collect(BusinessLane::cases())->contains(
            fn (BusinessLane $lane) => $role->allowsLane($lane) && $this->canViewLane($user, $lane),
        );
    }

    public function canViewField(
        ?User $user,
        UnitVisibilityScope $scope,
        DataClassification $classification,
    ): bool {
        if ($classification === DataClassification::Secret) {
            return false;
        }

        $scopeAllowed = match ($scope) {
            UnitVisibilityScope::SharedPublic => $this->hasPermission($user, self::VIEW),
            UnitVisibilityScope::SalesLane => $this->canViewLane($user, BusinessLane::Sales),
            UnitVisibilityScope::ProcurementLane => $this->canViewLane($user, BusinessLane::Procurement),
            UnitVisibilityScope::InternalOnly => $this->hasPermission($user, self::VIEW_CLASSIFICATIONS),
        };

        if (! $scopeAllowed) {
            return false;
        }

        return match ($classification) {
            DataClassification::Public, DataClassification::Internal => true,
            DataClassification::CommercialConfidential, DataClassification::PersonalData => $this->hasPermission($user, self::VIEW_CLASSIFICATIONS),
            DataClassification::Secret => false,
        };
    }

    public function assertContextBelongsToUnit(Unit $unit, UnitBusinessContext $context): void
    {
        if ((int) $context->unit_id !== (int) $unit->id) {
            throw new NotFoundHttpException('Unit business context not found.');
        }
    }

    public function authorizeLane(?User $user, BusinessLane $lane): void
    {
        if (! $this->canViewLane($user, $lane)) {
            throw new AuthorizationException('The requested Unit lane is not authorized.');
        }
    }

    public function capabilities(?User $user, Unit $unit): array
    {
        $canView = $this->hasPermission($user, self::VIEW);

        return [
            'view' => $canView,
            'view_sales_lane' => $canView && $this->canViewLane($user, BusinessLane::Sales),
            'view_procurement_lane' => $canView && $this->canViewLane($user, BusinessLane::Procurement),
            'view_internal_classifications' => $canView && $this->hasPermission($user, self::VIEW_CLASSIFICATIONS),
            'view_audit' => $canView
                && $this->hasPermission($user, self::VIEW_AUDIT)
                && $this->hasPermission($user, self::VIEW_SALES)
                && $this->hasPermission($user, self::VIEW_PROCUREMENT),
            'manage_roles' => $canView && $this->hasPermission($user, self::MANAGE_ROLES),
            'manage_contexts' => $canView && $this->hasPermission($user, self::MANAGE_CONTEXTS),
            'manage_aliases' => $canView && $this->hasPermission($user, self::MANAGE_ALIASES),
            'manage_observations' => $canView && $this->hasPermission($user, self::MANAGE_OBSERVATIONS),
            'verify_observations' => $canView && $this->hasPermission($user, self::VERIFY_OBSERVATIONS),
            'promote_observations' => $canView && $this->hasPermission($user, self::PROMOTE_OBSERVATIONS),
            'propose_entity' => $canView && $this->hasPermission($user, self::PROPOSE_ENTITY),
            'entity_create_requires_review' => true,
            'entity_link_requires_review' => true,
            'unit_id' => $unit->id,
        ];
    }
}
