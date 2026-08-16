<?php

namespace App\Domain\AiSales\Tools\Handlers;

use App\Domain\AiSales\DTO\Units\VerifiedPublicObservationEvidence;
use App\Domain\AiSales\Enums\DataClassification;
use App\Domain\AiSales\Enums\ObservationVerificationStatus;
use App\Domain\AiSales\Enums\UnitVisibilityScope;
use App\Domain\AiSales\Tools\AiToolExecutionContext;
use App\Domain\AiSales\Tools\AiToolHandlerInterface;
use App\Domain\AiSales\Tools\AiToolHandlerResult;
use Illuminate\Support\Facades\DB;

class GetVerifiedPublicObservationEvidenceToolHandler implements AiToolHandlerInterface
{
    private const ALLOWED_KEYS = [
        'unit.public_fact',
        'unit.profile_fact',
        'unit.description',
        'unit.business_summary',
        'unit.capability',
        'unit.certification',
        'unit.location_summary',
    ];

    public function handle(AiToolExecutionContext $context, array $input): AiToolHandlerResult
    {
        $rows = DB::table('unit_observations')
            ->join('unit_sources', 'unit_sources.id', '=', 'unit_observations.unit_source_id')
            ->where('unit_observations.unit_id', $context->unitId)
            ->whereNull('unit_observations.unit_business_context_id')
            ->whereNull('unit_sources.unit_business_context_id')
            ->whereIn('unit_observations.observation_key', self::ALLOWED_KEYS)
            ->where('unit_observations.verification_status', ObservationVerificationStatus::Verified->value)
            ->where('unit_observations.data_classification', DataClassification::Public->value)
            ->where('unit_observations.visibility_scope', UnitVisibilityScope::SharedPublic->value)
            ->where('unit_sources.data_classification', DataClassification::Public->value)
            ->where('unit_sources.visibility_scope', UnitVisibilityScope::SharedPublic->value)
            ->select([
                'unit_observations.id',
                'unit_observations.observation_key',
                'unit_observations.summary',
                'unit_observations.observed_at',
                'unit_sources.source_label',
                'unit_sources.source_reference',
            ])
            ->orderBy('unit_observations.id')
            ->limit(20)
            ->get();

        return new AiToolHandlerResult(
            $rows->map(static fn (object $row): VerifiedPublicObservationEvidence => new VerifiedPublicObservationEvidence(
                $row->observation_key,
                $row->summary,
                $row->source_label,
                $row->source_reference,
                $row->observed_at,
            ))->all(),
            'verified_public_observation_evidence',
            $context->unitId,
        );
    }
}
