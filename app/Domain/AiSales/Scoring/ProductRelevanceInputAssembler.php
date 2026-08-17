<?php

namespace App\Domain\AiSales\Scoring;

use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\DataClassification;
use App\Domain\AiSales\Enums\ObservationVerificationStatus;
use App\Domain\AiSales\Enums\UnitProductMatchStatus;
use App\Domain\AiSales\Enums\UnitVisibilityScope;
use App\Domain\AiSales\Queries\UnitTransactionAggregateQuery;
use App\Domain\AiSales\Services\DeterministicAiPayloadScanner;
use App\Domain\AiSales\Services\UnitContextAuthorizationService;
use App\Domain\AiSales\Web\RegistrableDomainResolver;
use App\Models\UnitBusinessContext;
use App\Models\UnitProductMatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ProductRelevanceInputAssembler
{
    private const MAX_OBSERVATIONS = 50;

    public function __construct(
        private readonly UnitContextAuthorizationService $authorization,
        private readonly UnitTransactionAggregateQuery $transactions,
        private readonly RegistrableDomainResolver $domains,
        private readonly DeterministicAiPayloadScanner $scanner,
    ) {}

    public function assemble(User $actor, UnitProductMatch $subject): ScoringInput
    {
        $match = UnitProductMatch::query()->without(['product'])->select([
            'id', 'unit_id', 'unit_business_context_id', 'product_id', 'unit_source_id',
            'prospecting_candidate_product_id', 'match_type', 'status', 'evidence_confidence',
            'evidence_reference', 'evidence_hash', 'reviewed_at', 'stale_after',
        ])->findOrFail($subject->id);
        $context = UnitBusinessContext::query()->select([
            'id', 'unit_id', 'lane', 'role_code', 'stage', 'status', 'last_activity_at',
        ])->findOrFail($match->unit_business_context_id);
        if ((int) $context->unit_id !== (int) $match->unit_id) {
            throw new NotFoundHttpException('Product match context binding is invalid.');
        }
        $this->authorization->authorizeLane($actor, $context->lane);
        $laneScope = $context->lane === BusinessLane::Sales
            ? UnitVisibilityScope::SalesLane->value : UnitVisibilityScope::ProcurementLane->value;
        $productPublished = DB::table('products')->where('id', $match->product_id)->where('is_published', true)->exists();

        $source = $match->unit_source_id ? DB::table('unit_sources')->select([
            'id', 'unit_id', 'unit_business_context_id', 'source_type', 'source_reference', 'source_url',
            'data_classification', 'visibility_scope', 'observed_at', 'last_checked_at',
        ])->where('id', $match->unit_source_id)->first() : null;
        $sourceAllowed = $source !== null
            && (int) $source->unit_id === (int) $match->unit_id
            && ((int) $source->unit_business_context_id === (int) $context->id
                || ($source->unit_business_context_id === null && $source->visibility_scope === UnitVisibilityScope::SharedPublic->value))
            && $source->data_classification === DataClassification::Public->value
            && in_array($source->visibility_scope, [UnitVisibilityScope::SharedPublic->value, $laneScope], true);
        $directionValid = match ($context->lane) {
            BusinessLane::Sales => in_array($match->match_type->value, ['potential_need', 'cross_sell'], true),
            BusinessLane::Procurement => $match->match_type->value === 'potential_offer',
            default => false,
        };
        $policyBlocked = ($source !== null && ! $sourceAllowed) || ! $directionValid
            || $context->status->value !== 'active' || ! $productPublished;

        $productKeys = [
            'product.direct_mention.'.$match->product_id,
            'product.process_use.'.$match->product_id,
            'product.industry_fit.'.$match->product_id,
            'product.geographic_fit.'.$match->product_id,
            'public_activity', 'prospecting_relevance',
        ];
        $observations = DB::table('unit_observations')
            ->leftJoin('unit_sources', 'unit_sources.id', '=', 'unit_observations.unit_source_id')
            ->where('unit_observations.unit_id', $match->unit_id)
            ->where(function ($query) use ($context): void {
                $query->where('unit_observations.unit_business_context_id', $context->id)
                    ->orWhere(function ($shared): void {
                        $shared->whereNull('unit_observations.unit_business_context_id')
                            ->where('unit_observations.visibility_scope', UnitVisibilityScope::SharedPublic->value);
                    });
            })
            ->whereIn('unit_observations.observation_key', $productKeys)
            ->where('unit_observations.data_classification', DataClassification::Public->value)
            ->whereIn('unit_observations.visibility_scope', [UnitVisibilityScope::SharedPublic->value, $laneScope])
            ->where(function ($query) use ($context, $laneScope, $match): void {
                $query->whereNull('unit_observations.unit_source_id')
                    ->orWhere(function ($bound) use ($context, $laneScope, $match): void {
                        $bound->where('unit_sources.unit_id', $match->unit_id)
                            ->where('unit_sources.data_classification', DataClassification::Public->value)
                            ->whereIn('unit_sources.visibility_scope', [UnitVisibilityScope::SharedPublic->value, $laneScope])
                            ->where(function ($sourceContext) use ($context): void {
                                $sourceContext->where('unit_sources.unit_business_context_id', $context->id)
                                    ->orWhere(function ($shared): void {
                                        $shared->whereNull('unit_sources.unit_business_context_id')
                                            ->where('unit_sources.visibility_scope', UnitVisibilityScope::SharedPublic->value);
                                    });
                            });
                    });
            })
            ->select([
                'unit_observations.id', 'unit_observations.observation_key', 'unit_observations.source_reference',
                'unit_observations.verification_status', 'unit_observations.confidence',
                'unit_observations.observed_at', 'unit_observations.last_checked_at',
                'unit_sources.source_type', 'unit_sources.source_url',
                'unit_sources.source_reference as provenance_reference',
            ])->orderByDesc('unit_observations.observed_at')->limit(self::MAX_OBSERVATIONS)->get();

        $candidateSources = collect();
        if ($match->prospecting_candidate_product_id) {
            $candidateSources = DB::table('prospecting_candidate_products as cp')
                ->join('prospecting_candidate_sources as cs', 'cs.prospecting_candidate_id', '=', 'cp.prospecting_candidate_id')
                ->where('cp.id', $match->prospecting_candidate_product_id)
                ->where('cp.product_id', $match->product_id)
                ->where('cp.status', 'approved')
                ->where('cs.data_classification', DataClassification::Public->value)
                ->whereIn('cs.visibility_scope', [UnitVisibilityScope::SharedPublic->value, $laneScope])
                ->select(['cs.source_type', 'cs.source_reference', 'cs.source_domain', 'cs.evidence_hash', 'cs.confidence', 'cs.accessed_at'])
                ->orderBy('cs.id')->limit(20)->get();
        }

        $evidence = [];
        $unsafeEvidence = false;
        $directReferencePresent = filled($match->evidence_reference) && filled($match->evidence_hash);
        $directReferenceSafe = $directReferencePresent && $this->referenceSafe((string) $match->evidence_reference);
        $unsafeEvidence = $directReferencePresent && ! $directReferenceSafe;
        $direct = $sourceAllowed && $directReferenceSafe;
        $safeObservations = $observations->filter(function ($observation) use (&$unsafeEvidence): bool {
            if (blank($observation->source_reference)) {
                return false;
            }
            if (! $this->referenceSafe((string) $observation->source_reference)) {
                $unsafeEvidence = true;

                return false;
            }

            return true;
        });
        $safeCandidateSources = $candidateSources->filter(function ($candidateSource) use (&$unsafeEvidence): bool {
            $reference = $candidateSource->source_reference ?: 'candidate-evidence:'.$candidateSource->evidence_hash;
            if (! $this->referenceSafe((string) $reference)) {
                $unsafeEvidence = true;

                return false;
            }

            return true;
        });
        if ($direct) {
            $evidence[] = $this->evidence('direct_product_mention', 'unit_product_match', (string) $match->evidence_reference, (string) $match->evidence_hash, (int) ($match->evidence_confidence ?? 39), $match->status !== UnitProductMatchStatus::Suggested, $source?->observed_at);
        }
        foreach ($safeObservations as $observation) {
            $positiveCode = match ($observation->observation_key) {
                'product.direct_mention.'.$match->product_id => 'direct_product_mention',
                'product.process_use.'.$match->product_id => 'process_or_end_product_use',
                'product.industry_fit.'.$match->product_id, 'public_activity', 'prospecting_relevance' => 'industry_activity_fit',
                'product.geographic_fit.'.$match->product_id => 'geographic_serviceability',
                default => null,
            };
            if ($positiveCode === null) {
                continue;
            }
            $code = match ($observation->verification_status) {
                ObservationVerificationStatus::Contradicted->value => 'contradictory_evidence',
                ObservationVerificationStatus::Stale->value => 'stale_evidence',
                default => $positiveCode,
            };
            $evidence[] = $this->evidence(
                $code, 'unit_observation', (string) $observation->source_reference,
                hash('sha256', 'unit-observation:'.$observation->id.':'.$observation->source_reference),
                (int) ($observation->confidence ?? 0),
                $observation->verification_status === ObservationVerificationStatus::Verified->value,
                $observation->last_checked_at ?: $observation->observed_at,
            );
        }
        foreach ($safeCandidateSources as $candidateSource) {
            $reference = $candidateSource->source_reference ?: 'candidate-evidence:'.$candidateSource->evidence_hash;
            $evidence[] = $this->evidence(
                'verified_public_product_evidence', 'public_candidate_source', (string) $reference,
                (string) $candidateSource->evidence_hash, (int) ($candidateSource->confidence ?? 70), true,
                $candidateSource->accessed_at,
            );
        }

        $verified = $safeObservations->where('verification_status', ObservationVerificationStatus::Verified->value);
        $contradictions = $safeObservations->where('verification_status', ObservationVerificationStatus::Contradicted->value)->count();
        $stale = $safeObservations->where('verification_status', ObservationVerificationStatus::Stale->value)->count()
            + ($match->stale_after && $match->stale_after->isPast() ? 1 : 0);
        $sourceFamilies = $safeCandidateSources->map(fn ($row): ?string => $this->sourceFamily($row->source_domain, null, null, $row->source_type))
            ->merge($safeObservations->map(fn ($row): ?string => $this->sourceFamily(null, $row->source_url, $row->provenance_reference ?: $row->source_reference, $row->source_type)))
            ->when($direct, fn ($families) => $families->push($this->sourceFamily(null, $source->source_url, $source->source_reference, $source->source_type)))
            ->filter()->unique()->count();
        $types = $safeCandidateSources->pluck('source_type')->merge($safeObservations->pluck('source_type'))
            ->when($direct, fn ($values) => $values->push($source->source_type))
            ->filter()->map(fn ($type) => mb_strtolower((string) $type));
        $hasPrimary = $types->contains(fn (string $type): bool => in_array($type, ['public_search', 'public_fetch', 'corporate_website', 'company_website'], true));
        $directoryOnly = $types->isNotEmpty() && $types->every(fn (string $type): bool => str_contains($type, 'directory') || str_contains($type, 'search_result'));
        $unresolvedDuplicate = DB::table('prospecting_candidate_unit_matches as pum')
            ->join('prospecting_candidates as pc', 'pc.id', '=', 'pum.prospecting_candidate_id')
            ->where('pum.unit_id', $match->unit_id)
            ->whereIn('pc.status', ['pending_resolution', 'probable_existing_review'])
            ->exists();

        return new ScoringInput('product_relevance', [
            'unit_product_match_id' => (int) $match->id,
            'unit_id' => (int) $match->unit_id,
            'unit_business_context_id' => (int) $context->id,
            'product_id' => (int) $match->product_id,
        ], [
            'lane' => $context->lane->value,
            'role_code' => $context->role_code->value,
            'direct_product_mention' => $direct || $verified->contains('observation_key', 'product.direct_mention.'.$match->product_id),
            'process_or_end_product_use' => $verified->contains('observation_key', 'product.process_use.'.$match->product_id),
            'industry_activity_fit' => $verified->contains(fn ($row): bool => in_array($row->observation_key, ['product.industry_fit.'.$match->product_id, 'public_activity', 'prospecting_relevance'], true)),
            'verified_public_product_evidence' => $safeCandidateSources->isNotEmpty(),
            'independent_source_count' => min(20, $sourceFamilies),
            'same_lane_transaction_count' => min(1000000, $this->transactions->transactionCount($actor, $context)),
            'geographic_serviceability' => $verified->contains('observation_key', 'product.geographic_fit.'.$match->product_id),
            'contradiction_count' => min(50, $contradictions),
            'stale_evidence_count' => min(50, $stale),
            'directory_only' => $directoryOnly,
            'has_primary_corporate_source' => $hasPrimary,
            'unresolved_duplicate' => $unresolvedDuplicate,
            'rejected' => $match->status === UnitProductMatchStatus::Rejected,
            'policy_blocked' => $policyBlocked || $unsafeEvidence
                || ! in_array($context->lane, [BusinessLane::Sales, BusinessLane::Procurement], true),
        ], array_slice($evidence, 0, 50));
    }

    private function evidence(string $factor, string $type, string $reference, string $hash, int $confidence, bool $verified, mixed $at): array
    {
        return [
            'factor_code' => $factor,
            'type' => $type,
            'reference' => mb_substr($reference, 0, 512),
            'hash' => preg_match('/^[a-f0-9]{64}$/', $hash) ? $hash : hash('sha256', $hash),
            'confidence' => max(0, min(100, $confidence)),
            'verified' => $verified,
            'at' => $at ? (string) $at : null,
        ];
    }

    private function sourceFamily(?string $knownDomain, ?string $url, ?string $reference, ?string $type): ?string
    {
        if (filled($knownDomain)) {
            return 'domain:'.mb_strtolower(trim((string) $knownDomain));
        }
        foreach ([$url, $reference] as $candidate) {
            $host = filled($candidate) ? parse_url((string) $candidate, PHP_URL_HOST) : null;
            if (! is_string($host) || $host === '') {
                continue;
            }
            try {
                return 'domain:'.$this->domains->resolve($host);
            } catch (\Throwable) {
                // Invalid provenance never creates an independent domain family.
            }
        }

        return filled($type) ? 'type:'.mb_strtolower(trim((string) $type)) : null;
    }

    private function referenceSafe(string $reference): bool
    {
        return ! $this->scanner->scan(['reference' => $reference], AiProcessingContour::ExternalSanitized)->blocked();
    }
}
