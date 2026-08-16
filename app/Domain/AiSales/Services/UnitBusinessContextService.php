<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\UnitContextStage;
use App\Domain\AiSales\Enums\UnitContextStatus;
use App\Domain\AiSales\Enums\UnitRoleCode;
use App\Models\Unit;
use App\Models\UnitBusinessContext;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UnitBusinessContextService
{
    public function __construct(
        private readonly UnitMarketRoleService $roles,
        private readonly UnitDossierAuditLogger $audit,
    ) {}

    public function upsert(Unit $unit, array $attributes, User $actor): UnitBusinessContext
    {
        $lane = $attributes['lane'] instanceof BusinessLane
            ? $attributes['lane']
            : BusinessLane::from($attributes['lane']);
        $role = $attributes['role_code'] instanceof UnitRoleCode
            ? $attributes['role_code']
            : UnitRoleCode::from($attributes['role_code']);

        if (! $role->allowsLane($lane)) {
            throw ValidationException::withMessages([
                'role_code' => 'The selected market role is not valid for this lane.',
            ]);
        }

        return DB::transaction(function () use ($unit, $attributes, $actor, $lane, $role): UnitBusinessContext {
            $this->roles->assign($unit, $role, $actor, $attributes['source'] ?? 'manual');

            $context = UnitBusinessContext::query()->firstOrNew([
                'unit_id' => $unit->id,
                'lane' => $lane->value,
                'role_code' => $role->value,
            ]);
            $created = ! $context->exists;

            $stage = $attributes['stage'] instanceof UnitContextStage
                ? $attributes['stage']
                : UnitContextStage::from($attributes['stage'] ?? UnitContextStage::New->value);
            $status = $attributes['status'] instanceof UnitContextStatus
                ? $attributes['status']
                : UnitContextStatus::from($attributes['status'] ?? UnitContextStatus::Active->value);

            $context->fill([
                'stage' => $stage,
                'status' => $status,
                'confidence' => $attributes['confidence'] ?? null,
                'owner_user_id' => $attributes['owner_user_id'] ?? $actor->id,
                'reviewer_user_id' => $attributes['reviewer_user_id'] ?? null,
                'primary_good_id' => array_key_exists('primary_good_id', $attributes)
                    ? $attributes['primary_good_id']
                    : $context->primary_good_id,
                'primary_segment' => $attributes['primary_segment'] ?? null,
                'source' => mb_substr((string) ($attributes['source'] ?? 'manual'), 0, 64),
                'first_activity_at' => $attributes['first_activity_at'] ?? ($context->first_activity_at ?? now()),
                'last_activity_at' => $attributes['last_activity_at'] ?? now(),
                'archived_at' => $status === UnitContextStatus::Archived || $stage === UnitContextStage::Archived
                    ? ($context->archived_at ?? now())
                    : null,
                'created_by' => $context->created_by ?? $actor->id,
                'updated_by' => $actor->id,
            ]);
            $context->save();

            if (in_array($role, [UnitRoleCode::Customer, UnitRoleCode::ProspectiveCustomer], true) && ! $unit->is_customer) {
                $unit->update(['is_customer' => true]);
            }
            if (in_array($role, [UnitRoleCode::Supplier, UnitRoleCode::ProspectiveSupplier], true) && ! $unit->is_supplier) {
                $unit->update(['is_supplier' => true]);
            }

            $this->audit->record(
                $unit,
                $created ? 'unit.context.created' : 'unit.context.updated',
                $created ? 'Создан business context.' : 'Обновлён business context.',
                $actor,
                $context,
                'unit_business_context',
                $context->id,
                [
                    'lane' => $lane->value,
                    'role_code' => $role->value,
                    'stage' => $stage->value,
                    'status' => $status->value,
                ],
            );

            $fresh = $context->fresh(['marketRole', 'owner:id,name', 'reviewer:id,name', 'primaryGood:id,name']);
            $fresh->wasRecentlyCreated = $created;

            return $fresh;
        }, 3);
    }
}
