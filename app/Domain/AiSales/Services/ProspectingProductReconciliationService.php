<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\Enums\CandidateProductSource;
use App\Domain\AiSales\Enums\CandidateProductStatus;
use App\Domain\AiSales\Enums\GoodOfferFitStatus;
use App\Domain\AiSales\Enums\ProductMappingState;
use App\Domain\AiSales\Enums\ProductScopeRole;
use App\Domain\AiSales\Enums\UnitGoodMatchStatus;
use App\Domain\AiSales\Enums\UnitProductMatchOrigin;
use App\Domain\AiSales\Enums\UnitProductMatchStatus;
use App\Domain\AiSales\Enums\UnitProductMatchType;
use App\Models\ProspectingCandidateProduct;
use App\Models\ProspectingSearchJob;
use App\Models\UnitGoodMatch;
use App\Models\UnitProductMatch;
use Illuminate\Support\Facades\DB;

class ProspectingProductReconciliationService
{
    public function __construct(private readonly GoodProductMappingResolver $mappings) {}

    public function reconcile(bool $apply, int $chunk): array
    {
        $report = $this->emptyReport($apply);

        ProspectingSearchJob::query()->select(['id', 'public_id', 'status', 'approved_by', 'approved_at', 'schema_hash'])
            ->orderBy('id')->chunkById($chunk, function ($jobs) use ($apply, &$report): void {
                foreach ($jobs as $job) {
                    $operation = function () use ($job, $apply, &$report): void {
                        $this->reconcileJob($job, $apply, $report);
                    };
                    $apply ? DB::transaction($operation, 3) : $operation();
                }
            });

        UnitGoodMatch::query()->orderBy('id')->chunkById($chunk, function ($matches) use ($apply, &$report): void {
            foreach ($matches as $match) {
                $operation = function () use ($match, $apply, &$report): void {
                    $this->reconcileGoodFit($match, $apply, $report);
                };
                $apply ? DB::transaction($operation, 3) : $operation();
            }
        });

        return $report;
    }

    private function reconcileJob(ProspectingSearchJob $job, bool $apply, array &$report): void
    {
        $report['jobs_seen']++;
        $goodRows = DB::table('prospecting_search_job_goods')
            ->where('prospecting_search_job_id', $job->id)
            ->orderBy('id')->get(['id', 'good_id', 'role', 'source_origin', 'compatibility_state'])
            ->filter(fn ($row) => in_array((string) $row->source_origin, [
                'legacy_stage08',
                'legacy_api_compatibility',
                'stage08r_reconciliation',
            ], true) || in_array((string) $row->role, ['primary', 'additional', 'legacy_primary'], true));
        if ($goodRows->isEmpty()) {
            return;
        }
        $mappedProducts = [];
        $aggregate = ProductMappingState::Mapped;

        foreach ($goodRows as $row) {
            $state = $this->mappings->state((int) $row->good_id);
            $report['job_goods_'.$this->counterSuffix($state)]++;
            if ($state === ProductMappingState::Mapped) {
                $productId = $this->mappings->exactProductId((int) $row->good_id);
                if ($productId !== null) {
                    $mappedProducts[] = $productId;
                }
            } else {
                $aggregate = $this->strongerReviewState($aggregate, $state);
            }

            if ($apply) {
                DB::table('prospecting_search_job_goods')->where('id', $row->id)->update([
                    'role' => $this->correctedGoodRole((string) $row->role),
                    'source_origin' => 'stage08r_reconciliation',
                    'compatibility_state' => $state->value,
                    'updated_at' => now(),
                ]);
            }
        }

        $mappedProducts = array_values(array_unique($mappedProducts));
        $existingProducts = DB::table('prospecting_search_job_products')
            ->where('prospecting_search_job_id', $job->id)
            ->orderBy('id')->get(['product_id', 'role']);
        $hasPrimary = $existingProducts->contains(fn ($row) => $row->role === ProductScopeRole::Primary->value);
        foreach ($mappedProducts as $productId) {
            $exists = $existingProducts->contains(fn ($row) => (int) $row->product_id === $productId);
            if (! $exists) {
                $report['job_products_created']++;
                if ($apply) {
                    DB::table('prospecting_search_job_products')->insert([
                        'prospecting_search_job_id' => $job->id,
                        'product_id' => $productId,
                        'role' => $hasPrimary ? ProductScopeRole::Additional->value : ProductScopeRole::Primary->value,
                        'source_origin' => 'legacy_good_mapping',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $hasPrimary = true;
            }
        }

        if ($aggregate->requiresReview()) {
            $report['jobs_review_required']++;
        }
        if ($apply) {
            DB::table('prospecting_search_jobs')->where('id', $job->id)->update([
                'product_mapping_state' => $aggregate->value,
                'product_mapping_reason_code' => $aggregate->requiresReview() ? $aggregate->value : null,
                'updated_at' => now(),
            ]);
        }

        $productIds = array_values(array_unique([
            ...$existingProducts->where('role', '!=', ProductScopeRole::Exclude->value)->pluck('product_id')->map(fn ($id) => (int) $id)->all(),
            ...$mappedProducts,
        ]));
        DB::table('prospecting_candidates')->where('prospecting_search_job_id', $job->id)
            ->orderBy('id')->chunkById(100, function ($candidates) use ($job, $productIds, $apply, &$report): void {
                foreach ($candidates as $candidate) {
                    foreach ($productIds as $productId) {
                        $exists = DB::table('prospecting_candidate_products')
                            ->where('prospecting_candidate_id', $candidate->id)
                            ->where('product_id', $productId)->exists();
                        if ($exists) {
                            continue;
                        }
                        $report['candidate_products_created']++;
                        if ($apply) {
                            $approved = $job->approved_by !== null && $job->approved_at !== null;
                            ProspectingCandidateProduct::query()->create([
                                'prospecting_candidate_id' => $candidate->id,
                                'product_id' => $productId,
                                'source' => CandidateProductSource::Rule,
                                'status' => $approved ? CandidateProductStatus::Approved : CandidateProductStatus::Suggested,
                                'safe_rationale' => 'Product scope reconciled deterministically from an approved legacy Job Good mapping.',
                                'evidence_reference' => 'prospecting-job:'.$job->public_id,
                                'evidence_hash' => hash('sha256', $job->schema_hash.'|reconciled-product|'.$productId),
                                'confidence' => null,
                                'reviewed_by' => $approved ? $job->approved_by : null,
                                'reviewed_at' => $approved ? $job->approved_at : null,
                            ]);
                        }
                    }
                }
            });
    }

    private function reconcileGoodFit(UnitGoodMatch $match, bool $apply, array &$report): void
    {
        $report['good_matches_seen']++;
        if ($match->unit_product_match_id !== null) {
            return;
        }
        $state = $this->mappings->state((int) $match->good_id);
        if ($state !== ProductMappingState::Mapped) {
            $report['good_fits_'.$this->counterSuffix($state)]++;
            if ($apply) {
                DB::table('unit_good_matches')->where('id', $match->id)->update([
                    'compatibility_state' => $state->value,
                    'updated_at' => now(),
                ]);
            }

            return;
        }

        $productId = $this->mappings->exactProductId((int) $match->good_id);
        $productType = UnitProductMatchType::from($match->match_type->value);
        $existing = UnitProductMatch::query()->where([
            'unit_business_context_id' => $match->unit_business_context_id,
            'product_id' => $productId,
            'match_type' => $productType->value,
        ])->first();
        if (! $existing) {
            $report['unit_product_matches_created']++;
            if ($apply) {
                $existing = UnitProductMatch::query()->create([
                    'unit_id' => $match->unit_id,
                    'unit_business_context_id' => $match->unit_business_context_id,
                    'product_id' => $productId,
                    'unit_source_id' => $match->unit_source_id,
                    'match_type' => $productType,
                    'status' => UnitProductMatchStatus::Suggested,
                    'origin' => UnitProductMatchOrigin::Rule,
                    'evidence_confidence' => $match->confidence,
                    'safe_rationale' => 'Product scope reconciled from one exact historical Good mapping; Product relevance remains review-required.',
                    'evidence_reference' => $match->evidence_reference,
                    'evidence_hash' => hash('sha256', $match->evidence_hash.'|product|'.$productId),
                    'rules_version' => 'stage08r-reconciliation-v1',
                    'created_by' => $match->created_by,
                    'stale_after' => $match->stale_after,
                ]);
            }
        }
        if (! $match->unit_product_match_id) {
            $report['good_fits_linked']++;
        }
        if ($apply && $existing) {
            DB::table('unit_good_matches')->where('id', $match->id)->update([
                'unit_product_match_id' => $existing->id,
                'fit_status' => $this->fitStatus($match->status)->value,
                'compatibility_state' => ProductMappingState::Mapped->value,
                'updated_at' => now(),
            ]);
        }
    }

    private function fitStatus(UnitGoodMatchStatus $status): GoodOfferFitStatus
    {
        return match ($status) {
            UnitGoodMatchStatus::Suggested => GoodOfferFitStatus::OfferCandidate,
            UnitGoodMatchStatus::Reviewed => GoodOfferFitStatus::PreferredOffer,
            UnitGoodMatchStatus::Approved => GoodOfferFitStatus::ApprovedForOffer,
            UnitGoodMatchStatus::Rejected => GoodOfferFitStatus::Rejected,
            UnitGoodMatchStatus::Stale => GoodOfferFitStatus::Stale,
        };
    }

    private function correctedGoodRole(string $role): string
    {
        return match ($role) {
            'primary' => 'legacy_primary',
            'additional' => 'additional_offer',
            default => $role,
        };
    }

    private function strongerReviewState(ProductMappingState $current, ProductMappingState $candidate): ProductMappingState
    {
        $rank = [
            ProductMappingState::NotApplicable->value => 0,
            ProductMappingState::Mapped->value => 0,
            ProductMappingState::ProductScopeMismatch->value => 1,
            ProductMappingState::AmbiguousProductMapping->value => 2,
            ProductMappingState::MissingProductMapping->value => 3,
            ProductMappingState::LegacyUnreconciled->value => 4,
        ];

        return $rank[$candidate->value] > $rank[$current->value] ? $candidate : $current;
    }

    private function counterSuffix(ProductMappingState $state): string
    {
        return match ($state) {
            ProductMappingState::Mapped => 'mapped',
            ProductMappingState::MissingProductMapping => 'missing',
            ProductMappingState::AmbiguousProductMapping => 'ambiguous',
            ProductMappingState::ProductScopeMismatch => 'mismatch',
            default => 'review',
        };
    }

    private function emptyReport(bool $apply): array
    {
        return [
            'dry_run' => ! $apply,
            'jobs_seen' => 0,
            'job_products_created' => 0,
            'jobs_review_required' => 0,
            'job_goods_mapped' => 0,
            'job_goods_missing' => 0,
            'job_goods_ambiguous' => 0,
            'job_goods_mismatch' => 0,
            'job_goods_review' => 0,
            'candidate_products_created' => 0,
            'good_matches_seen' => 0,
            'unit_product_matches_created' => 0,
            'good_fits_linked' => 0,
            'good_fits_missing' => 0,
            'good_fits_ambiguous' => 0,
            'good_fits_mismatch' => 0,
            'good_fits_review' => 0,
        ];
    }
}
