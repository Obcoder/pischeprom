<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\Enums\DataClassification;
use App\Domain\AiSales\Enums\UnitVisibilityScope;
use App\Models\Unit;
use App\Models\UnitBusinessContext;
use App\Models\UnitSource;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class UnitSourceService
{
    public function __construct(
        private readonly UnitVisibilityInvariant $visibility,
        private readonly UnitDossierAuditLogger $audit,
    ) {}

    public function create(Unit $unit, array $attributes, User $actor): UnitSource
    {
        $context = $this->context($unit, $attributes['unit_business_context_id'] ?? null);
        $classification = DataClassification::from($attributes['data_classification']);
        $scope = UnitVisibilityScope::from($attributes['visibility_scope']);
        $this->visibility->assert($unit, $context, $classification, $scope);

        $sourceKey = hash('sha256', implode('|', [
            $unit->id,
            $context?->id ?? 'shared',
            mb_strtolower(trim($attributes['source_type'])),
            mb_strtolower(trim((string) ($attributes['source_reference'] ?? ''))),
            mb_strtolower(trim((string) ($attributes['source_url'] ?? ''))),
        ]));

        return DB::transaction(function () use ($unit, $attributes, $actor, $context, $classification, $scope, $sourceKey): UnitSource {
            $source = UnitSource::query()->firstOrCreate(
                ['source_key' => $sourceKey],
                [
                    'unit_id' => $unit->id,
                    'unit_business_context_id' => $context?->id,
                    'source_type' => $attributes['source_type'],
                    'source_label' => $attributes['source_label'] ?? null,
                    'source_reference' => $attributes['source_reference'] ?? null,
                    'source_url' => $attributes['source_url'] ?? null,
                    'data_classification' => $classification,
                    'visibility_scope' => $scope,
                    'observed_at' => $attributes['observed_at'] ?? now(),
                    'last_checked_at' => $attributes['last_checked_at'] ?? null,
                    'created_by_type' => 'human',
                    'created_by_user_id' => $actor->id,
                ],
            );

            if ($source->wasRecentlyCreated) {
                $this->audit->record(
                    $unit,
                    'unit.source.created',
                    'Добавлен источник provenance.',
                    $actor,
                    $context,
                    'unit_source',
                    $source->id,
                    ['source_type' => $source->source_type, 'source_key' => $source->source_key],
                );
            }

            $created = $source->wasRecentlyCreated;
            $fresh = $source->fresh();
            $fresh->wasRecentlyCreated = $created;

            return $fresh;
        }, 3);
    }

    private function context(Unit $unit, mixed $contextId): ?UnitBusinessContext
    {
        if (! $contextId) {
            return null;
        }

        return $unit->businessContexts()->findOrFail((int) $contextId);
    }
}
