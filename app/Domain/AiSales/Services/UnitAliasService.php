<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\Enums\DataClassification;
use App\Domain\AiSales\Enums\ObservationVerificationStatus;
use App\Domain\AiSales\Enums\UnitAliasType;
use App\Domain\AiSales\Enums\UnitVisibilityScope;
use App\Models\Unit;
use App\Models\UnitAlias;
use App\Models\UnitBusinessContext;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Normalizer;

class UnitAliasService
{
    public function __construct(
        private readonly UnitVisibilityInvariant $visibility,
        private readonly UnitDossierAuditLogger $audit,
    ) {}

    public function create(Unit $unit, array $attributes, User $actor): UnitAlias
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
        $this->assertSourceMatchesContext($source?->unit_business_context_id, $context);

        $alias = trim($attributes['alias']);
        $normalized = $this->normalize($alias);
        $type = UnitAliasType::from($attributes['alias_type']);

        return DB::transaction(function () use ($unit, $attributes, $actor, $context, $source, $classification, $scope, $alias, $normalized, $type): UnitAlias {
            $unitAlias = UnitAlias::query()->firstOrCreate(
                [
                    'unit_id' => $unit->id,
                    'unit_business_context_id' => $context?->id,
                    'unit_source_id' => $source?->id,
                    'normalized_alias' => $normalized,
                    'alias_type' => $type->value,
                ],
                [
                    'alias' => $alias,
                    'confidence' => $attributes['confidence'] ?? null,
                    'verification_status' => ObservationVerificationStatus::Unverified,
                    'data_classification' => $classification,
                    'visibility_scope' => $scope,
                    'created_by' => $actor->id,
                ],
            );

            if ($unitAlias->wasRecentlyCreated) {
                $this->audit->record(
                    $unit,
                    'unit.alias.created',
                    'Добавлен alias Unit.',
                    $actor,
                    subjectType: 'unit_alias',
                    subjectId: $unitAlias->id,
                    metadata: ['alias_type' => $type->value, 'normalized_alias' => $normalized],
                );
            }

            $created = $unitAlias->wasRecentlyCreated;
            $fresh = $unitAlias->fresh(['businessContext', 'source']);
            $fresh->wasRecentlyCreated = $created;

            return $fresh;
        }, 3);
    }

    private function assertSourceMatchesContext(?int $sourceContextId, ?UnitBusinessContext $context): void
    {
        if ($sourceContextId !== null && $sourceContextId !== $context?->id) {
            throw ValidationException::withMessages([
                'unit_source_id' => 'The provenance source belongs to another Unit business context.',
            ]);
        }
    }

    public function normalize(string $alias): string
    {
        $value = class_exists(Normalizer::class)
            ? (Normalizer::normalize($alias, Normalizer::FORM_KC) ?: $alias)
            : $alias;
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/[^\pL\pN.\-]+/u', ' ', $value) ?? $value;

        return mb_substr(trim(preg_replace('/\s+/u', ' ', $value) ?? $value), 0, 255);
    }
}
