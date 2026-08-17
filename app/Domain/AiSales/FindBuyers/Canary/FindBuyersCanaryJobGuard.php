<?php

namespace App\Domain\AiSales\FindBuyers\Canary;

use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\ProductScopeRole;
use App\Domain\AiSales\Enums\ProspectingJobStatus;
use App\Domain\AiSales\Enums\ProspectingPurpose;
use App\Domain\AiSales\Enums\UnitRoleCode;
use App\Domain\AiSales\FindBuyers\FindBuyersDisclosurePreview;
use App\Domain\AiSales\Prospecting\ProspectingQueryPlanner;
use App\Domain\AiSales\Search\SearchProviderException;
use App\Domain\AiSales\Services\GoodProductMappingResolver;
use App\Domain\AiSales\Services\ProspectingAuthorizationService;
use App\Models\ProspectingSearchJob;
use App\Models\ProspectingSearchQuery;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Throwable;

final class FindBuyersCanaryJobGuard
{
    public const MAX_YANDEX_REQUESTS = 1;

    public const MAX_RESULTS = 10;

    public const MAX_FETCH_DOMAINS = 3;

    public const MAX_SUCCESSFUL_PAGES = 1;

    public const MAX_CANDIDATES = 1;

    public function __construct(
        private readonly ProspectingAuthorizationService $authorization,
        private readonly ProspectingQueryPlanner $planner,
        private readonly FindBuyersDisclosurePreview $disclosure,
        private readonly GoodProductMappingResolver $productMappings,
    ) {}

    public function validate(ProspectingSearchJob $job): FindBuyersCanaryContext
    {
        if (! $job->isFindBuyersWorkflow()
            || $job->wizard_version !== (string) config('ai-sales.find_buyers.wizard_version', 'stage11-v1')
            || $job->purpose !== ProspectingPurpose::BuyerDiscovery
            || $job->lane !== BusinessLane::Sales
            || $job->default_role_code !== UnitRoleCode::ProspectiveCustomer) {
            throw new SearchProviderException('canary_policy', 'stage11b_find_buyers_job_required');
        }
        if ($job->status !== ProspectingJobStatus::Approved
            || $job->cancelled_at !== null
            || $job->submitted_by === null
            || $job->submitted_at === null
            || $job->approved_by === null
            || $job->approved_at === null) {
            throw new SearchProviderException('canary_policy', 'stage11b_approved_job_required');
        }
        if ($job->auto_create_unit
            || (int) $job->max_searches !== 0
            || (float) $job->max_cost_rub !== 0.0) {
            throw new SearchProviderException('canary_policy', 'stage11b_automation_or_cost_reservation_blocked');
        }

        $operator = User::query()->find($job->approved_by);
        if (! $operator || $operator->status !== 'active' || $operator->email_verified_at === null) {
            throw new SearchProviderException('canary_policy', 'stage11b_active_verified_operator_required');
        }
        $this->authorization->authorize(
            $operator,
            ProspectingAuthorizationService::EXECUTE_SEARCH,
            BusinessLane::Sales,
        );
        $this->authorization->authorize(
            $operator,
            ProspectingAuthorizationService::RESEARCH_SEARCH_RESULTS,
            BusinessLane::Sales,
        );

        $scope = DB::table('prospecting_search_job_products as scope')
            ->join('products', 'products.id', '=', 'scope.product_id')
            ->where('scope.prospecting_search_job_id', $job->id)
            ->select(['products.id', 'products.rus', 'products.is_published', 'scope.role'])
            ->orderBy('products.id')->get();
        $primary = $scope->where('role', ProductScopeRole::Primary->value)->values();
        if ($scope->count() !== 1 || $primary->count() !== 1 || ! (bool) $primary->first()->is_published) {
            throw new SearchProviderException('canary_policy', 'stage11b_single_published_primary_product_required');
        }
        $product = $primary->first();
        $productName = trim((string) $product->rus);
        if (mb_strtolower($productName) !== mb_strtolower('Брокколи')) {
            throw new SearchProviderException('canary_policy', 'stage11b_broccoli_fixture_required');
        }

        $goods = $job->goods()->select(['goods.id', 'goods.is_published'])->get()->unique('id')->values();
        if ($goods->count() > 1 || $goods->contains(fn ($good): bool => ! (bool) $good->is_published)) {
            throw new SearchProviderException('canary_policy', 'stage11b_optional_good_scope_blocked');
        }
        if ($job->launch_source_type === 'product') {
            if ((int) $job->launch_source_id !== (int) $product->id) {
                throw new SearchProviderException('canary_policy', 'stage11b_launch_source_stale');
            }
        } elseif ($job->launch_source_type === 'good') {
            if ($goods->count() !== 1 || (int) $goods->first()->id !== (int) $job->launch_source_id) {
                throw new SearchProviderException('canary_policy', 'stage11b_launch_source_stale');
            }
        } else {
            throw new SearchProviderException('canary_policy', 'stage11b_launch_source_stale');
        }
        foreach ($goods as $good) {
            if ($this->productMappings->distinctProductIds((int) $good->id) !== [(int) $product->id]) {
                throw new SearchProviderException('canary_policy', 'stage11b_good_product_mapping_stale');
            }
        }

        $city = $job->city_id ? DB::table('cities')->where('id', $job->city_id)->value('name') : null;
        if (! is_string($city) || mb_strtolower(trim($city)) !== mb_strtolower('Санкт-Петербург')) {
            throw new SearchProviderException('canary_policy', 'stage11b_spb_geography_required');
        }
        $currentDisclosureHash = (string) ($this->disclosure->build()['policy_hash'] ?? '');
        if ($currentDisclosureHash === ''
            || ! hash_equals($currentDisclosureHash, (string) $job->disclosure_policy_hash)) {
            throw new SearchProviderException('canary_policy', 'stage11b_disclosure_hash_stale');
        }

        $criteria = $job->criteria_snapshot ?? [];
        if ((int) $job->max_queries !== 1
            || (int) $job->max_results_per_query < 1
            || (int) $job->max_results_per_query > self::MAX_RESULTS
            || (int) $job->max_candidates !== self::MAX_CANDIDATES
            || (int) ($criteria['max_domains'] ?? 0) < 0
            || (int) ($criteria['max_domains'] ?? 0) > self::MAX_FETCH_DOMAINS
            || (int) ($criteria['max_page_fetch_attempts'] ?? 0) < 0
            || (int) ($criteria['max_page_fetch_attempts'] ?? 0) > self::MAX_FETCH_DOMAINS) {
            throw new SearchProviderException('canary_policy', 'stage11b_job_caps_exceeded');
        }

        try {
            $plan = $this->planner->plan($job);
        } catch (Throwable) {
            throw new SearchProviderException('canary_policy', 'stage11b_product_or_plan_stale');
        }
        if (count($plan->items) !== 1) {
            throw new SearchProviderException('canary_policy', 'stage11b_exactly_one_query_required');
        }
        $queries = $job->queries()->orderBy('sequence')->get();
        if ($queries->count() !== 1) {
            throw new SearchProviderException('canary_policy', 'stage11b_exactly_one_query_required');
        }

        /** @var ProspectingSearchQuery $query */
        $query = $queries->first();
        $item = $plan->items[0];
        if ($query->plan_status !== 'approved'
            || $query->plan_approved_by === null
            || (int) $query->plan_approved_by !== (int) $operator->id
            || ! hash_equals($plan->planHash, (string) $query->plan_hash)
            || ! hash_equals($plan->productScopeHash, (string) $query->product_scope_hash)
            || ! hash_equals($item->templateHash, (string) $query->template_hash)
            || ! hash_equals($item->queryHash, (string) $query->query_hash)) {
            throw new SearchProviderException('canary_policy', 'stage11b_approved_plan_stale');
        }

        return new FindBuyersCanaryContext(
            $job,
            $query,
            $operator,
            mb_substr($productName, 0, 255),
            (string) $job->launch_source_type,
            $goods->count(),
            [
                'yandex_search_requests' => self::MAX_YANDEX_REQUESTS,
                'normalized_results' => self::MAX_RESULTS,
                'fetch_domains' => min(self::MAX_FETCH_DOMAINS, (int) ($criteria['max_domains'] ?? 0)),
                'fetch_attempts' => min(self::MAX_FETCH_DOMAINS, (int) ($criteria['max_page_fetch_attempts'] ?? 0)),
                'successful_pages' => self::MAX_SUCCESSFUL_PAGES,
                'candidates' => self::MAX_CANDIDATES,
                'retries' => 0,
                'failovers' => 0,
            ],
        );
    }
}
