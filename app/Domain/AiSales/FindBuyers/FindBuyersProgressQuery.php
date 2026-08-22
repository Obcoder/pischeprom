<?php

namespace App\Domain\AiSales\FindBuyers;

use App\Domain\AiSales\DTO\FindBuyers\FindBuyersProgressView;
use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\ProspectingJobStatus;
use App\Domain\AiSales\Enums\ProspectingPurpose;
use App\Domain\AiSales\Enums\ScoreConfidenceBand;
use App\Models\ProspectingSearchJob;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class FindBuyersProgressQuery
{
    public function __construct(
        private readonly FindBuyersFeatureGuard $features,
        private readonly FindBuyersAuthorizationService $authorization,
    ) {}

    public function get(ProspectingSearchJob $job, User $actor): FindBuyersProgressView
    {
        $this->features->ui();
        $this->authorization->authorizeView($actor);
        if ($job->purpose !== ProspectingPurpose::BuyerDiscovery || $job->lane !== BusinessLane::Sales) {
            throw new NotFoundHttpException('Find Buyers job was not found.');
        }

        $products = DB::table('prospecting_search_job_products as scope')
            ->join('products', 'products.id', '=', 'scope.product_id')
            ->where('scope.prospecting_search_job_id', $job->id)
            ->select(['products.id', 'products.rus', 'scope.role'])
            ->orderByRaw("CASE WHEN scope.role = 'primary' THEN 0 WHEN scope.role = 'additional' THEN 1 ELSE 2 END")
            ->orderBy('products.id')->get()->unique(fn ($row) => $row->role.':'.$row->id)->values();
        $goods = DB::table('prospecting_search_job_goods as scope')
            ->join('goods', 'goods.id', '=', 'scope.good_id')
            ->where('scope.prospecting_search_job_id', $job->id)
            ->select(['goods.id', 'goods.name', 'scope.role', 'scope.compatibility_state'])
            ->orderBy('goods.id')->get()->unique('id')->values();
        $queryRows = DB::table('prospecting_search_queries')->where('prospecting_search_job_id', $job->id)
            ->whereNotNull('plan_hash')->where('plan_status', '!=', 'stale')
            ->select(['id', 'plan_status', 'status'])->get();
        $executionRows = DB::table('prospecting_search_executions')->where('prospecting_search_job_id', $job->id)
            ->select(['id', 'prospecting_search_query_id', 'status', 'request_count', 'result_count', 'duplicate_count', 'blocked_result_count', 'error_code'])->get();
        $resultRows = DB::table('prospecting_search_results')->where('prospecting_search_job_id', $job->id)
            ->select(['id', 'duplicate_of_id', 'domain_hash', 'prospecting_candidate_id', 'fetch_status', 'research_status'])->get();
        $fetchRows = DB::table('prospecting_public_fetches as fetches')
            ->join('prospecting_search_results as results', 'results.id', '=', 'fetches.prospecting_search_result_id')
            ->where('results.prospecting_search_job_id', $job->id)
            ->select(['fetches.status', 'fetches.error_category', 'fetches.error_code'])->get();
        $researchRows = DB::table('prospecting_public_research_records as research')
            ->join('prospecting_search_results as results', 'results.id', '=', 'research.prospecting_search_result_id')
            ->where('results.prospecting_search_job_id', $job->id)
            ->select(['research.status', 'research.schema_valid', 'research.error_category', 'research.error_code'])->get();
        $candidateRows = DB::table('prospecting_candidates as candidates')
            ->leftJoin('units', 'units.id', '=', 'candidates.resolved_unit_id')
            ->where('candidates.prospecting_search_job_id', $job->id)
            ->where('candidates.lane', BusinessLane::Sales->value)
            ->select([
                'candidates.id', 'candidates.public_id', 'candidates.status', 'candidates.resolution_outcome',
                'candidates.resolved_unit_id', 'units.name as unit_name',
            ])->orderBy('candidates.id')->limit(250)->get();
        $matchRows = $this->productMatches($job);
        $scoring = $this->authorization->canViewScoring($actor)
            ? $this->scores($matchRows, $candidateRows)
            : ['visible' => false, 'product_relevance' => [], 'prospect_priority' => [], 'blocked_count' => 0];

        $counts = [
            'products' => [
                'total' => $products->count(),
                'primary' => $products->where('role', 'primary')->count(),
                'additional' => $products->where('role', 'additional')->count(),
                'excluded' => $products->where('role', 'exclude')->count(),
            ],
            'queries' => [
                'planned' => $queryRows->count(),
                'review_required' => $queryRows->where('plan_status', 'review_required')->count(),
                'approved' => $queryRows->where('plan_status', 'approved')->count(),
                'executed' => $executionRows->where('status', 'completed')->pluck('prospecting_search_query_id')->unique()->count(),
            ],
            'executions' => [
                'total' => $executionRows->count(),
                'queued_or_processing' => $executionRows->whereIn('status', ['queued', 'processing'])->count(),
                'completed' => $executionRows->where('status', 'completed')->count(),
                'failed' => $executionRows->where('status', 'failed')->count(),
                'request_count' => (int) $executionRows->sum('request_count'),
            ],
            'results' => [
                'total' => $resultRows->count(),
                'deduplicated' => $resultRows->whereNull('duplicate_of_id')->count(),
                'duplicates' => $resultRows->whereNotNull('duplicate_of_id')->count(),
                'domains' => $resultRows->pluck('domain_hash')->filter()->unique()->count(),
                'blocked' => (int) $executionRows->sum('blocked_result_count'),
            ],
            'fetches' => $this->outcomeCounts($fetchRows, 'completed'),
            'research' => [
                ...$this->outcomeCounts($researchRows, 'completed'),
                'schema_valid' => $researchRows->where('schema_valid', true)->count(),
            ],
            'candidates' => [
                'total' => $candidateRows->count(),
                'pending_resolution' => $candidateRows->where('status', 'pending_resolution')->count(),
                'exact_existing' => $candidateRows->where('resolution_outcome', 'exact_existing')->count(),
                'probable_existing_review' => $candidateRows->where('resolution_outcome', 'probable_existing_review')->count(),
                'new_unit_review' => $candidateRows->whereIn('status', ['new_unit_review', 'new_unit_created'])->count(),
                'resolved' => $candidateRows->whereNotNull('resolved_unit_id')->count(),
                'rejected' => $candidateRows->where('status', 'rejected')->count(),
            ],
            'matches' => [
                'unit_product_matches' => $matchRows->pluck('id')->unique()->count(),
                'sales_contexts' => $matchRows->pluck('unit_business_context_id')->unique()->count(),
            ],
            'scores' => [
                'product_relevance_snapshots' => count($scoring['product_relevance']),
                'prospect_priority_snapshots' => count($scoring['prospect_priority']),
                'blocked' => $scoring['blocked_count'],
            ],
        ];
        $stage = $this->stage($job, $counts);

        return new FindBuyersProgressView([
            'job' => $this->jobSummary($job, $products, $goods),
            'stage' => $stage,
            'progress_percent' => $this->progressPercent($stage),
            'counts' => $counts,
            'fetch_outcomes' => $this->safeOutcomes($fetchRows),
            'research_outcomes' => $this->safeOutcomes($researchRows),
            'candidates' => $candidateRows->map(fn ($candidate): array => [
                'id' => $candidate->public_id,
                'status' => $candidate->status,
                'resolution_outcome' => $candidate->resolution_outcome,
                'resolved_unit' => $candidate->resolved_unit_id ? [
                    'id' => (int) $candidate->resolved_unit_id,
                    'name' => mb_substr((string) $candidate->unit_name, 0, 255),
                    'url' => '/Ameise/unit/'.(int) $candidate->resolved_unit_id,
                ] : null,
                'review_url' => '/Ameise/ai-sales?tab=review&candidate='.
                    rawurlencode((string) $candidate->public_id).'#candidate-review',
                'review_api' => '/api/ai-sales/prospecting/candidates/'.$candidate->public_id,
            ])->all(),
            'scoring' => $scoring,
            'next_action' => $this->nextAction($stage),
            'runtime' => $this->features->runtimeState(),
            'source_of_truth' => [
                'job' => 'prospecting_search_jobs',
                'query_plan' => 'prospecting_search_queries',
                'progress_is_projection' => true,
                'copied_event_rows' => 0,
            ],
        ]);
    }

    private function productMatches(ProspectingSearchJob $job): Collection
    {
        return DB::table('unit_product_matches as matches')
            ->join('unit_business_contexts as contexts', 'contexts.id', '=', 'matches.unit_business_context_id')
            ->join('prospecting_candidate_products as candidate_products', 'candidate_products.id', '=', 'matches.prospecting_candidate_product_id')
            ->join('prospecting_candidates as candidates', 'candidates.id', '=', 'candidate_products.prospecting_candidate_id')
            ->join('products', 'products.id', '=', 'matches.product_id')
            ->join('units', 'units.id', '=', 'matches.unit_id')
            ->where('candidates.prospecting_search_job_id', $job->id)
            ->where('contexts.lane', BusinessLane::Sales->value)
            ->whereColumn('contexts.unit_id', 'matches.unit_id')
            ->where('candidates.lane', BusinessLane::Sales->value)
            ->select([
                'matches.id', 'matches.unit_id', 'matches.unit_business_context_id', 'matches.product_id',
                'candidate_products.prospecting_candidate_id', 'products.rus as product_name', 'units.name as unit_name',
            ])->orderBy('matches.id')->limit(250)->get()->unique('id')->values();
    }

    private function scores(Collection $matches, Collection $candidates): array
    {
        $candidatePublicIds = $candidates->keyBy('id')->map(fn ($row) => $row->public_id);
        $productSnapshots = $matches->isEmpty() ? collect() : DB::table('unit_product_relevance_snapshots')
            ->whereIn('unit_product_match_id', $matches->pluck('id'))
            ->whereNull('stale_at')->whereNull('superseded_at')->orderByDesc('id')->limit(500)->get()
            ->unique('unit_product_match_id')->values();
        $matchById = $matches->keyBy('id');
        $productScores = $productSnapshots->map(function ($snapshot) use ($matchById, $candidatePublicIds): array {
            $match = $matchById[(int) $snapshot->unit_product_match_id];

            return [
                'snapshot_id' => (int) $snapshot->id,
                'unit_product_match_id' => (int) $snapshot->unit_product_match_id,
                'candidate_id' => $candidatePublicIds[(int) $match->prospecting_candidate_id] ?? null,
                'unit' => ['id' => (int) $match->unit_id, 'name' => mb_substr((string) $match->unit_name, 0, 255)],
                'product' => ['id' => (int) $match->product_id, 'name' => mb_substr((string) $match->product_name, 0, 255)],
                'effective_score' => (int) $snapshot->effective_score,
                'confidence' => (int) $snapshot->confidence,
                'confidence_band' => ScoreConfidenceBand::fromConfidence((int) $snapshot->confidence)->value,
                'band' => (string) $snapshot->band,
                'eligibility' => (string) $snapshot->eligibility,
                'review_status' => (string) $snapshot->review_status,
                'history_api' => '/api/ai-sales/scoring/units/'.(int) $match->unit_id.'/contexts/'.(int) $match->unit_business_context_id,
            ];
        })->all();
        $contextIds = $matches->pluck('unit_business_context_id')->unique();
        $prioritySnapshots = $contextIds->isEmpty() ? collect() : DB::table('unit_prospect_priority_snapshots')
            ->whereIn('unit_business_context_id', $contextIds)->whereNull('stale_at')->whereNull('superseded_at')
            ->orderByDesc('id')->limit(500)->get()->unique('unit_business_context_id')->values();
        $matchByContext = $matches->unique('unit_business_context_id')->keyBy('unit_business_context_id');
        $priorityScores = $prioritySnapshots->map(function ($snapshot) use ($matchByContext): array {
            $match = $matchByContext[(int) $snapshot->unit_business_context_id];

            return [
                'snapshot_id' => (int) $snapshot->id,
                'unit_business_context_id' => (int) $snapshot->unit_business_context_id,
                'unit' => ['id' => (int) $match->unit_id, 'name' => mb_substr((string) $match->unit_name, 0, 255)],
                'effective_score' => (int) $snapshot->effective_score,
                'confidence' => (int) $snapshot->confidence,
                'confidence_band' => ScoreConfidenceBand::fromConfidence((int) $snapshot->confidence)->value,
                'band' => (string) $snapshot->band,
                'eligibility' => (string) $snapshot->eligibility,
                'review_status' => (string) $snapshot->review_status,
                'history_api' => '/api/ai-sales/scoring/units/'.(int) $match->unit_id.'/contexts/'.(int) $snapshot->unit_business_context_id,
            ];
        })->all();

        return [
            'visible' => true,
            'bands' => ['low', 'medium', 'promising', 'high', 'very_high'],
            'product_relevance' => $productScores,
            'prospect_priority' => $priorityScores,
            'blocked_count' => collect([...$productScores, ...$priorityScores])
                ->filter(fn (array $score): bool => str_starts_with($score['eligibility'], 'blocked_'))->count(),
        ];
    }

    private function outcomeCounts(Collection $rows, string $successStatus): array
    {
        return [
            'total' => $rows->count(),
            'completed' => $rows->where('status', $successStatus)->count(),
            'partial_or_fail_closed' => $rows->where('status', '!=', $successStatus)->count(),
            'blocked_or_failed' => $rows->filter(fn ($row): bool => filled($row->error_code)
                || str_contains((string) $row->status, 'blocked')
                || str_contains((string) $row->status, 'failed'))->count(),
        ];
    }

    /** @return list<array{status: string, error_category: ?string, error_code: ?string, count: int}> */
    private function safeOutcomes(Collection $rows): array
    {
        return $rows->groupBy(fn ($row): string => implode('|', [
            (string) $row->status, (string) $row->error_category, (string) $row->error_code,
        ]))->map(function (Collection $group): array {
            $row = $group->first();

            return [
                'status' => (string) $row->status,
                'error_category' => filled($row->error_category) ? (string) $row->error_category : null,
                'error_code' => filled($row->error_code) ? (string) $row->error_code : null,
                'count' => $group->count(),
            ];
        })->values()->all();
    }

    private function stage(ProspectingSearchJob $job, array $counts): string
    {
        if ($job->status === ProspectingJobStatus::Cancelled) {
            return 'cancelled';
        }
        if ($job->status === ProspectingJobStatus::Archived) {
            return 'completed';
        }
        if ($job->product_mapping_state?->requiresReview()) {
            return 'blocked';
        }
        if ($job->status === ProspectingJobStatus::Draft) {
            return $counts['queries']['planned'] > 0 ? 'query_plan_ready' : 'draft';
        }
        if ($job->status === ProspectingJobStatus::ReviewRequired) {
            return 'review_required';
        }
        if ($counts['queries']['review_required'] > 0 || $counts['queries']['approved'] === 0) {
            return 'review_required';
        }
        if ($counts['executions']['failed'] > 0 && $counts['executions']['completed'] === 0) {
            return 'failed';
        }
        if ($counts['executions']['total'] === 0) {
            return 'search_pending';
        }
        if ($counts['executions']['queued_or_processing'] > 0) {
            return 'searching';
        }
        if ($counts['results']['total'] === 0) {
            return 'blocked';
        }
        if ($counts['fetches']['total'] === 0) {
            return 'public_research_pending';
        }
        if ($counts['candidates']['total'] === 0) {
            return $counts['fetches']['completed'] > 0 ? 'researching' : 'blocked';
        }
        if ($counts['candidates']['resolved'] === 0) {
            return $counts['candidates']['pending_resolution'] > 0 ? 'candidate_review' : 'candidates_created';
        }
        if ($counts['scores']['product_relevance_snapshots'] === 0) {
            return 'scoring_pending';
        }
        if ($counts['scores']['prospect_priority_snapshots'] > 0) {
            return 'scored';
        }

        return 'units_enriched';
    }

    private function progressPercent(string $stage): int
    {
        return [
            'draft' => 5, 'query_plan_ready' => 15, 'review_required' => 25, 'approved' => 30,
            'search_pending' => 35, 'searching' => 45, 'results_collected' => 55,
            'public_research_pending' => 60, 'researching' => 65, 'candidates_created' => 72,
            'candidate_review' => 78, 'units_enriched' => 85, 'scoring_pending' => 90,
            'scored' => 95, 'completed' => 100, 'cancelled' => 100, 'failed' => 100, 'blocked' => 100,
        ][$stage] ?? 0;
    }

    private function nextAction(string $stage): array
    {
        return match ($stage) {
            'draft' => ['code' => 'build_query_plan', 'label' => 'Сформировать план запросов', 'allowed' => true],
            'query_plan_ready' => ['code' => 'submit_for_review', 'label' => 'Отправить на проверку', 'allowed' => true],
            'review_required' => ['code' => 'human_review', 'label' => 'Ожидается проверка человеком', 'allowed' => false],
            'search_pending' => ['code' => 'stage11b_live_gate', 'label' => 'Live execution заблокирован до Stage 11B', 'allowed' => false],
            'candidate_review' => ['code' => 'review_candidates', 'label' => 'Проверить Candidates', 'allowed' => true],
            'scoring_pending' => ['code' => 'review_then_score', 'label' => 'Проверить dossier перед scoring', 'allowed' => false],
            default => ['code' => 'none', 'label' => 'Нет доступного автоматического действия', 'allowed' => false],
        };
    }

    private function jobSummary(ProspectingSearchJob $job, Collection $products, Collection $goods): array
    {
        $criteria = is_array($job->criteria_snapshot) ? $job->criteria_snapshot : [];
        $users = DB::table('users')->whereIn('id', array_filter([
            $job->owner_user_id, $job->reviewer_user_id, $job->submitted_by,
        ]))->pluck('name', 'id');
        $geography = null;
        if ($job->city_id) {
            $geography = DB::table('cities')->where('id', $job->city_id)->value('name');
        } elseif ($job->region_id) {
            $geography = DB::table('regions')->where('id', $job->region_id)->value('name');
        } elseif ($job->country_id) {
            $geography = DB::table('countries')->where('id', $job->country_id)->value('name');
        }

        return [
            'id' => $job->public_id,
            'purpose' => $job->purpose->value,
            'lane' => $job->lane->value,
            'role_code' => $job->default_role_code->value,
            'status' => $job->status->value,
            'safe_objective' => mb_substr((string) $job->safe_objective, 0, 512),
            'launch_source' => $job->launch_source_type ? [
                'type' => $job->launch_source_type, 'id' => (int) $job->launch_source_id,
            ] : null,
            'products' => $products->map(fn ($row): array => [
                'id' => (int) $row->id, 'name' => mb_substr((string) $row->rus, 0, 255), 'role' => $row->role,
            ])->all(),
            'originating_goods' => $goods->map(fn ($row): array => [
                'id' => (int) $row->id, 'name' => mb_substr((string) $row->name, 0, 255),
                'role' => $row->role, 'compatibility_state' => $row->compatibility_state,
            ])->all(),
            'geography' => filled($geography) ? mb_substr((string) $geography, 0, 120) : null,
            'owner' => ['id' => (int) $job->owner_user_id, 'name' => mb_substr((string) ($users[$job->owner_user_id] ?? ''), 0, 255)],
            'reviewer' => $job->reviewer_user_id ? ['id' => (int) $job->reviewer_user_id, 'name' => mb_substr((string) ($users[$job->reviewer_user_id] ?? ''), 0, 255)] : null,
            'submitted_at' => $job->submitted_at?->toISOString(),
            'approved_at' => $job->approved_at?->toISOString(),
            'cancelled_at' => $job->cancelled_at?->toISOString(),
            'last_action_at' => $job->updated_at?->toISOString(),
            'limits' => [
                'max_queries' => (int) $job->max_queries,
                'max_results_per_query' => (int) $job->max_results_per_query,
                'max_candidates' => (int) $job->max_candidates,
                'max_domains' => (int) ($criteria['max_domains'] ?? 0),
                'max_page_fetch_attempts' => (int) ($criteria['max_page_fetch_attempts'] ?? 0),
                'max_cost_rub' => (string) $job->max_cost_rub,
            ],
        ];
    }
}
