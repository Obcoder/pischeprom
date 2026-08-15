<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\Enums\UnitRoleCode;
use App\Models\MarketRole;
use App\Models\Unit;
use App\Models\UnitMarketRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UnitMarketRoleService
{
    public function __construct(private readonly UnitDossierAuditLogger $audit) {}

    public function assign(Unit $unit, UnitRoleCode $code, ?User $actor, string $source = 'manual'): UnitMarketRole
    {
        return DB::transaction(function () use ($unit, $code, $actor, $source): UnitMarketRole {
            $role = MarketRole::query()->where('code', $code->value)->where('is_active', true)->firstOrFail();
            $assignment = UnitMarketRole::query()->firstOrNew([
                'unit_id' => $unit->id,
                'market_role_id' => $role->id,
            ]);
            $wasActive = $assignment->exists && $assignment->archived_at === null;

            $assignment->fill([
                'source' => mb_substr($source, 0, 64),
                'assigned_by' => $actor?->id,
                'removed_by' => null,
                'archived_at' => null,
            ])->save();

            if (! $wasActive) {
                $this->audit->record(
                    $unit,
                    'unit.role.assigned',
                    "Назначена роль {$code->value}.",
                    $actor,
                    subjectType: 'market_role',
                    subjectId: $role->id,
                    metadata: ['role_code' => $code->value, 'source' => $source],
                );
            }

            return $assignment->fresh('role');
        }, 3);
    }

    public function archive(Unit $unit, MarketRole $role, User $actor): UnitMarketRole
    {
        return DB::transaction(function () use ($unit, $role, $actor): UnitMarketRole {
            if ($unit->businessContexts()->where('role_code', $role->code)->whereNull('archived_at')->exists()) {
                throw ValidationException::withMessages([
                    'role_code' => 'Archive all active contexts using this role first.',
                ]);
            }

            $assignment = UnitMarketRole::query()
                ->where('unit_id', $unit->id)
                ->where('market_role_id', $role->id)
                ->firstOrFail();

            if ($assignment->archived_at === null) {
                $assignment->update([
                    'removed_by' => $actor->id,
                    'archived_at' => now(),
                ]);

                $this->audit->record(
                    $unit,
                    'unit.role.archived',
                    "Архивирована роль {$role->code}.",
                    $actor,
                    subjectType: 'market_role',
                    subjectId: $role->id,
                    metadata: ['role_code' => $role->code],
                );
            }

            return $assignment->fresh('role');
        }, 3);
    }
}
