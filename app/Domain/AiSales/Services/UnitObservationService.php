<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\Enums\DataClassification;
use App\Domain\AiSales\Enums\ObservationVerificationStatus;
use App\Domain\AiSales\Enums\UnitVisibilityScope;
use App\Models\Unit;
use App\Models\UnitObservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UnitObservationService
{
    public function __construct(
        private readonly UnitVisibilityInvariant $visibility,
        private readonly UnitDossierAuditLogger $audit,
    ) {}

    public function create(Unit $unit, array $attributes, User $actor): UnitObservation
    {
        $context = filled($attributes['unit_business_context_id'] ?? null)
            ? $unit->businessContexts()->findOrFail((int) $attributes['unit_business_context_id'])
            : null;
        $source = filled($attributes['unit_source_id'] ?? null)
            ? $unit->sources()->findOrFail((int) $attributes['unit_source_id'])
            : null;
        $classification = DataClassification::from($attributes['data_classification']);
        $scope = UnitVisibilityScope::from($attributes['visibility_scope']);
        $this->visibility->assert($unit, $context, $classification, $scope);

        if ($source?->unit_business_context_id !== null && $source->unit_business_context_id !== $context?->id) {
            throw ValidationException::withMessages([
                'unit_source_id' => 'The provenance source belongs to another Unit business context.',
            ]);
        }

        return DB::transaction(function () use ($unit, $attributes, $actor, $context, $source, $classification, $scope): UnitObservation {
            $observation = UnitObservation::query()->create([
                'unit_id' => $unit->id,
                'unit_business_context_id' => $context?->id,
                'unit_source_id' => $source?->id,
                'observation_key' => $attributes['observation_key'],
                'normalized_value' => $attributes['normalized_value'] ?? null,
                'summary' => $attributes['summary'],
                'source_reference' => $attributes['source_reference'] ?? null,
                'verification_status' => ObservationVerificationStatus::Unverified,
                'confidence' => $attributes['confidence'] ?? null,
                'data_classification' => $classification,
                'visibility_scope' => $scope,
                'observed_at' => $attributes['observed_at'] ?? now(),
                'last_checked_at' => $attributes['last_checked_at'] ?? null,
                'created_by_type' => 'human',
                'created_by_user_id' => $actor->id,
                'rules_version' => $attributes['rules_version'] ?? 'stage03-v1',
                'model_version' => null,
            ]);

            $this->audit->record(
                $unit,
                'unit.observation.created',
                'Добавлено observation без изменения canonical Unit.',
                $actor,
                $context,
                'unit_observation',
                $observation->id,
                [
                    'observation_key' => $observation->observation_key,
                    'classification' => $classification->value,
                    'visibility_scope' => $scope->value,
                ],
            );

            return $observation->fresh(['source', 'businessContext']);
        }, 3);
    }

    public function review(Unit $unit, UnitObservation $observation, ObservationVerificationStatus $status, User $actor): UnitObservation
    {
        $this->assertBelongsToUnit($unit, $observation);

        return DB::transaction(function () use ($unit, $observation, $status, $actor): UnitObservation {
            $observation->update([
                'verification_status' => $status,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'last_checked_at' => now(),
            ]);

            $this->audit->record(
                $unit,
                'unit.observation.reviewed',
                "Observation отмечено как {$status->value}.",
                $actor,
                $observation->businessContext,
                'unit_observation',
                $observation->id,
                ['verification_status' => $status->value],
            );

            return $observation->fresh(['source', 'reviewer:id,name']);
        }, 3);
    }

    private function assertBelongsToUnit(Unit $unit, UnitObservation $observation): void
    {
        abort_unless((int) $observation->unit_id === (int) $unit->id, 404);
    }
}
