<?php

namespace App\Domain\AiSales\Services;

use App\Models\AiAgentRun;
use App\Models\Unit;
use App\Models\UnitBusinessContext;
use App\Models\User;

class AiControlPlaneAuthorizationService
{
    public const VIEW = 'ai_sales.control.view';

    public const MANAGE = 'ai_sales.control.manage';

    public const RUN = 'ai_sales.research.run';

    public const VIEW_RUNS = 'ai_sales.runs.view';

    public const CANCEL_RUNS = 'ai_sales.runs.cancel';

    public const VIEW_CAPABILITIES = 'ai_sales.capabilities.view';

    public const VIEW_TOOLING = 'ai_sales.tools.view';

    public function __construct(private readonly UnitContextAuthorizationService $units) {}

    public function canView(?User $user): bool
    {
        return $this->units->hasPermission($user, self::VIEW)
            && $this->units->hasPermission($user, UnitContextAuthorizationService::VIEW);
    }

    public function canRun(?User $user, Unit $unit, UnitBusinessContext $context): bool
    {
        return $this->canView($user)
            && $this->units->hasPermission($user, self::RUN)
            && (int) $context->unit_id === (int) $unit->id
            && $this->units->canViewLane($user, $context->lane);
    }

    public function canViewRun(?User $user, AiAgentRun $run): bool
    {
        if (! $this->canView($user) || ! $this->units->hasPermission($user, self::VIEW_RUNS)) {
            return false;
        }

        return $run->businessContext !== null
            && $run->businessContext->lane === $run->lane
            && $this->units->canViewLane($user, $run->businessContext->lane);
    }

    public function canCancelRun(?User $user, AiAgentRun $run): bool
    {
        return $this->canViewRun($user, $run)
            && $this->units->hasPermission($user, self::CANCEL_RUNS);
    }

    public function canManage(?User $user): bool
    {
        return $this->canView($user) && $this->units->hasPermission($user, self::MANAGE);
    }

    public function canViewCapabilities(?User $user): bool
    {
        return $this->canView($user) && $this->units->hasPermission($user, self::VIEW_CAPABILITIES);
    }

    public function canViewTooling(?User $user): bool
    {
        return $this->canView($user) && $this->units->hasPermission($user, self::VIEW_TOOLING);
    }

    public function capabilities(?User $user, Unit $unit): array
    {
        return [
            'view_control_plane' => $this->canView($user),
            'view_ai_runs' => $this->canView($user) && $this->units->hasPermission($user, self::VIEW_RUNS),
            'create_ai_run' => $this->canView($user) && $this->units->hasPermission($user, self::RUN),
            'cancel_ai_run' => $this->canView($user) && $this->units->hasPermission($user, self::CANCEL_RUNS),
            'manage_ai_kill_switches' => $this->canManage($user),
            'view_ai_capabilities' => $this->canViewCapabilities($user),
            'view_ai_tooling' => $this->canViewTooling($user),
            'unit_id' => $unit->id,
        ];
    }
}
