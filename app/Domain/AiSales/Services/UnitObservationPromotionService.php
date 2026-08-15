<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\Enums\DataClassification;
use App\Domain\AiSales\Enums\ObservationVerificationStatus;
use App\Models\Unit;
use App\Models\UnitObservation;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UnitObservationPromotionService
{
    public function __construct(private readonly UnitDossierAuditLogger $audit) {}

    public function promote(Unit $unit, UnitObservation $observation, User $actor): Unit
    {
        abort_unless((int) $observation->unit_id === (int) $unit->id, 404);

        if ($observation->verification_status !== ObservationVerificationStatus::Verified) {
            throw ValidationException::withMessages([
                'observation' => 'Only a verified observation may be promoted.',
            ]);
        }

        if ($observation->observation_key !== 'unit.name') {
            throw ValidationException::withMessages([
                'observation_key' => 'Stage 03 only supports explicit promotion to Unit name.',
            ]);
        }

        if (in_array($observation->data_classification, [DataClassification::Secret, DataClassification::PersonalData], true)) {
            throw ValidationException::withMessages([
                'data_classification' => 'Secret or personal data cannot be promoted to Unit name.',
            ]);
        }

        $value = trim((string) $observation->normalized_value);

        if ($value === '') {
            throw ValidationException::withMessages([
                'normalized_value' => 'A normalized value is required for promotion.',
            ]);
        }

        return DB::transaction(function () use ($unit, $observation, $actor, $value): Unit {
            $before = $unit->name;
            $unit->update(['name' => mb_substr($value, 0, 255)]);

            $this->audit->record(
                $unit,
                'unit.observation.promoted',
                'Verified observation promoted to canonical Unit name by an authorized human.',
                $actor,
                $observation->businessContext,
                'unit_observation',
                $observation->id,
                ['field' => 'unit.name', 'before' => $before, 'after' => $unit->name],
            );

            return $unit->fresh();
        }, 3);
    }
}
