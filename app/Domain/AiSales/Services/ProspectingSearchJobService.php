<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\Enums\ProductMappingState;
use App\Domain\AiSales\Enums\ProductScopeRole;
use App\Domain\AiSales\Enums\ProspectingJobStatus;
use App\Domain\AiSales\Enums\ProspectingPurpose;
use App\Models\Good;
use App\Models\Product;
use App\Models\ProspectingSearchJob;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProspectingSearchJobService
{
    public function __construct(
        private readonly ProspectingFeatureGuard $features,
        private readonly ProspectingAuthorizationService $authorization,
        private readonly GoodProductMappingResolver $productMappings,
    ) {}

    /** @param array{max_domains?: int, max_page_fetch_attempts?: int}|null $serverOwnedCriteriaLimits */
    public function createDraft(array $attributes, User $actor, ?array $serverOwnedCriteriaLimits = null): ProspectingSearchJob
    {
        $this->features->jobs();
        $this->features->assertNoLiveSearch();
        $purpose = ProspectingPurpose::from($attributes['purpose']);
        $this->authorization->authorize($actor, ProspectingAuthorizationService::MANAGE_JOBS, $purpose->lane());
        $criteria = $this->criteria($attributes['criteria'] ?? [], $serverOwnedCriteriaLimits);
        $safeObjective = $this->safeObjective((string) ($attributes['safe_objective'] ?? ''));
        $productScope = $this->productScope($attributes);
        $goodIds = $this->originatingGoodIds($attributes);
        $this->assertModernGoodInputHasProduct($attributes, $productScope, $goodIds);
        $this->assertPublishedProducts($productScope['all']);
        $this->assertPublishedGoods($goodIds);
        $mapping = $this->mappingSummary(
            $goodIds,
            $productScope['discovery'],
            (bool) ($attributes['explicit_good_product_selection'] ?? false),
            $productScope['primary'],
        );

        return DB::transaction(function () use ($attributes, $actor, $purpose, $criteria, $safeObjective, $productScope, $goodIds, $mapping): ProspectingSearchJob {
            $job = ProspectingSearchJob::query()->create([
                'created_by' => $actor->id,
                'owner_user_id' => $actor->id,
                'purpose' => $purpose,
                'lane' => $purpose->lane(),
                'default_role_code' => $purpose->role(),
                'primary_good_id' => $goodIds[0] ?? null,
                'country_id' => $attributes['country_id'] ?? null,
                'region_id' => $attributes['region_id'] ?? null,
                'city_id' => $attributes['city_id'] ?? null,
                'locale' => mb_substr((string) ($attributes['locale'] ?? 'ru-RU'), 0, 12),
                'max_queries' => $this->boundedInteger($attributes['max_queries'] ?? 10, 1, (int) config('ai-sales.prospecting.limits.max_queries', 20)),
                'max_candidates' => $this->boundedInteger($attributes['max_candidates'] ?? 100, 1, (int) config('ai-sales.prospecting.limits.max_candidates', 250)),
                'max_results_per_query' => $this->boundedInteger($attributes['max_results_per_query'] ?? 20, 1, 50),
                'max_rows' => $this->boundedInteger($attributes['max_rows'] ?? 500, 1, 1000),
                'max_bytes' => $this->boundedInteger($attributes['max_bytes'] ?? 1048576, 1024, 2097152),
                'max_searches' => 0,
                'max_cost_rub' => 0,
                'safe_objective' => $safeObjective,
                'criteria_snapshot' => $criteria,
                'policy_version' => 'stage08r-product-first-v1',
                'workflow_version' => 'stage08r-no-execution',
                'schema_hash' => $this->schemaHash($criteria, $productScope, $goodIds),
                'status' => ProspectingJobStatus::Draft,
                'product_mapping_state' => $mapping['state'],
                'product_mapping_reason_code' => $mapping['reason_code'],
                'auto_create_unit' => false,
                'retention_profile' => 'prospecting-transient-v1',
            ]);
            $this->syncProducts($job, $productScope, 'manual_review');
            $this->syncGoods($job, $goodIds, $mapping['per_good'], $this->usesLegacyGoodInput($attributes));

            return $this->fresh($job);
        }, 3);
    }

    public function updateDraft(ProspectingSearchJob $job, array $attributes, User $actor): ProspectingSearchJob
    {
        $this->features->jobs();
        if ($job->status !== ProspectingJobStatus::Draft || (int) $job->owner_user_id !== (int) $actor->id) {
            throw ValidationException::withMessages(['job' => 'Only the owner may update a draft job.']);
        }
        $purpose = ProspectingPurpose::from($attributes['purpose'] ?? $job->purpose->value);
        $this->authorization->authorize($actor, ProspectingAuthorizationService::MANAGE_JOBS, $purpose->lane());
        $criteria = array_key_exists('criteria', $attributes) ? $this->criteria($attributes['criteria']) : $job->criteria_snapshot;
        $safeObjective = $this->safeObjective((string) ($attributes['safe_objective'] ?? $job->safe_objective));
        $productScope = $this->productScope($attributes, $job);
        $goodIds = $this->originatingGoodIds($attributes, $job);
        $this->assertModernGoodInputHasProduct($attributes, $productScope, $goodIds, true);
        $this->assertPublishedProducts($productScope['all']);
        $this->assertPublishedGoods($goodIds);
        $mapping = $this->mappingSummary(
            $goodIds,
            $productScope['discovery'],
            (bool) ($attributes['explicit_good_product_selection'] ?? false),
            $productScope['primary'],
        );

        return DB::transaction(function () use ($job, $attributes, $purpose, $criteria, $safeObjective, $productScope, $goodIds, $mapping): ProspectingSearchJob {
            $job->fill([
                'purpose' => $purpose,
                'lane' => $purpose->lane(),
                'default_role_code' => $purpose->role(),
                'primary_good_id' => $this->hasGoodInput($attributes) ? ($goodIds[0] ?? null) : $job->primary_good_id,
                'country_id' => array_key_exists('country_id', $attributes) ? $attributes['country_id'] : $job->country_id,
                'region_id' => array_key_exists('region_id', $attributes) ? $attributes['region_id'] : $job->region_id,
                'city_id' => array_key_exists('city_id', $attributes) ? $attributes['city_id'] : $job->city_id,
                'locale' => array_key_exists('locale', $attributes) ? mb_substr((string) $attributes['locale'], 0, 12) : $job->locale,
                'max_queries' => $this->boundedInteger($attributes['max_queries'] ?? $job->max_queries, 1, (int) config('ai-sales.prospecting.limits.max_queries', 20)),
                'max_candidates' => $this->boundedInteger($attributes['max_candidates'] ?? $job->max_candidates, 1, (int) config('ai-sales.prospecting.limits.max_candidates', 250)),
                'max_results_per_query' => $this->boundedInteger($attributes['max_results_per_query'] ?? $job->max_results_per_query, 1, 50),
                'max_rows' => $this->boundedInteger($attributes['max_rows'] ?? $job->max_rows, 1, 1000),
                'max_bytes' => $this->boundedInteger($attributes['max_bytes'] ?? $job->max_bytes, 1024, 2097152),
                'safe_objective' => $safeObjective,
                'criteria_snapshot' => $criteria,
                'schema_hash' => $this->schemaHash($criteria, $productScope, $goodIds),
                'product_mapping_state' => $mapping['state'],
                'product_mapping_reason_code' => $mapping['reason_code'],
                'auto_create_unit' => false,
            ])->save();
            if ($this->hasProductInput($attributes)) {
                $this->syncProducts($job, $productScope, 'manual_review');
            }
            if ($this->hasGoodInput($attributes)) {
                $this->syncGoods($job, $goodIds, $mapping['per_good'], $this->usesLegacyGoodInput($attributes));
            } else {
                $this->refreshGoodCompatibility($job, $mapping['per_good']);
            }

            return $this->fresh($job);
        }, 3);
    }

    public function submit(ProspectingSearchJob $job, User $actor): ProspectingSearchJob
    {
        $this->authorization->authorize($actor, ProspectingAuthorizationService::MANAGE_JOBS, $job->lane);
        if ((int) $job->owner_user_id !== (int) $actor->id) {
            throw ValidationException::withMessages(['job' => 'Only the job owner may submit its draft.']);
        }
        $this->transition($job, ProspectingJobStatus::Draft, ProspectingJobStatus::ReviewRequired, $actor, false);

        return $this->fresh($job);
    }

    public function approve(ProspectingSearchJob $job, User $actor): ProspectingSearchJob
    {
        $this->authorization->authorize($actor, ProspectingAuthorizationService::REVIEW, $job->lane);
        $this->assertProductReady($job);
        $this->transition($job, ProspectingJobStatus::ReviewRequired, ProspectingJobStatus::Approved, $actor, true);

        return $this->fresh($job);
    }

    public function cancel(ProspectingSearchJob $job, User $actor): ProspectingSearchJob
    {
        $this->features->jobs();
        $this->authorization->authorize($actor, ProspectingAuthorizationService::MANAGE_JOBS, $job->lane);
        if (in_array($job->status, [ProspectingJobStatus::Cancelled, ProspectingJobStatus::Archived], true)) {
            return $job;
        }
        $job->update(['status' => ProspectingJobStatus::Cancelled, 'cancelled_at' => now(), 'reviewer_user_id' => $actor->id]);

        return $this->fresh($job);
    }

    public function archive(ProspectingSearchJob $job, User $actor): ProspectingSearchJob
    {
        $this->features->jobs();
        $this->authorization->authorize($actor, ProspectingAuthorizationService::REVIEW, $job->lane);
        if (! in_array($job->status, [ProspectingJobStatus::Cancelled, ProspectingJobStatus::Approved], true)) {
            throw ValidationException::withMessages(['status' => 'Only an approved or cancelled job can be archived.']);
        }
        $job->update(['status' => ProspectingJobStatus::Archived, 'reviewer_user_id' => $actor->id]);

        return $this->fresh($job);
    }

    private function assertProductReady(ProspectingSearchJob $job): void
    {
        if (! $job->products()->wherePivot('role', ProductScopeRole::Primary->value)->exists()) {
            throw ValidationException::withMessages(['primary_product_id' => 'Approval requires a primary published Product.']);
        }
        if ($job->product_mapping_state->requiresReview()) {
            throw ValidationException::withMessages(['originating_good_ids' => 'Good-to-Product mapping requires explicit review before approval.']);
        }
    }

    private function transition(ProspectingSearchJob $job, ProspectingJobStatus $from, ProspectingJobStatus $to, User $actor, bool $approved): void
    {
        $this->features->jobs();
        $updated = ProspectingSearchJob::query()->whereKey($job->id)->where('status', $from->value)->update([
            'status' => $to->value,
            'reviewer_user_id' => $approved ? $actor->id : null,
            'approved_by' => $approved ? $actor->id : null,
            'approved_at' => $approved ? now() : null,
            'updated_at' => now(),
        ]);
        if ($updated !== 1) {
            throw ValidationException::withMessages(['status' => "Job must be {$from->value}."]);
        }
    }

    private function productScope(array $attributes, ?ProspectingSearchJob $job = null): array
    {
        $current = $job ? $job->products()->get(['products.id'])->groupBy('pivot.role') : collect();
        $primary = array_key_exists('primary_product_id', $attributes)
            ? ($attributes['primary_product_id'] ? (int) $attributes['primary_product_id'] : null)
            : $current->get(ProductScopeRole::Primary->value)?->first()?->id;
        $additional = array_key_exists('additional_product_ids', $attributes)
            ? collect($attributes['additional_product_ids'])->map(fn ($id) => (int) $id)->take(25)->all()
            : $current->get(ProductScopeRole::Additional->value, collect())->pluck('id')->map(fn ($id) => (int) $id)->all();
        $excluded = array_key_exists('excluded_product_ids', $attributes)
            ? collect($attributes['excluded_product_ids'])->map(fn ($id) => (int) $id)->take(25)->all()
            : $current->get(ProductScopeRole::Exclude->value, collect())->pluck('id')->map(fn ($id) => (int) $id)->all();
        $additional = array_values(array_unique($additional));
        $excluded = array_values(array_unique($excluded));
        $discovery = array_values(array_unique(array_filter([$primary, ...$additional])));

        if (array_intersect($discovery, $excluded) !== []) {
            throw ValidationException::withMessages(['excluded_product_ids' => 'A Product cannot be selected and excluded in the same job.']);
        }
        if ($primary && in_array($primary, $additional, true)) {
            throw ValidationException::withMessages(['additional_product_ids' => 'Primary Product must not be duplicated as additional.']);
        }

        return [
            'primary' => $primary,
            'additional' => $additional,
            'exclude' => $excluded,
            'discovery' => $discovery,
            'all' => array_values(array_unique([...$discovery, ...$excluded])),
        ];
    }

    private function originatingGoodIds(array $attributes, ?ProspectingSearchJob $job = null): array
    {
        $hasInput = array_key_exists('originating_good_ids', $attributes)
            || array_key_exists('primary_good_id', $attributes)
            || array_key_exists('additional_good_ids', $attributes);
        if (! $hasInput && $job) {
            return $job->goods()->pluck('goods.id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        }

        return collect([
            ...($attributes['originating_good_ids'] ?? []),
            ...array_filter([$attributes['primary_good_id'] ?? null]),
            ...($attributes['additional_good_ids'] ?? []),
        ])->map(fn ($id) => (int) $id)->unique()->take(25)->values()->all();
    }

    private function mappingSummary(
        array $goodIds,
        array $selectedProductIds,
        bool $explicitProductSelection = false,
        ?int $primaryProductId = null,
    ): array {
        if ($goodIds === []) {
            return ['state' => ProductMappingState::NotApplicable, 'reason_code' => null, 'per_good' => []];
        }
        $perGood = [];
        foreach ($goodIds as $goodId) {
            $perGood[$goodId] = $explicitProductSelection && $primaryProductId
                ? $this->productMappings->stateForExplicitProduct($goodId, $primaryProductId)
                : $this->productMappings->state($goodId, $selectedProductIds);
        }
        $priority = [
            ProductMappingState::MissingProductMapping,
            ProductMappingState::AmbiguousProductMapping,
            ProductMappingState::ProductScopeMismatch,
        ];
        $state = ProductMappingState::Mapped;
        foreach ($priority as $blocked) {
            if (in_array($blocked, $perGood, true)) {
                $state = $blocked;
                break;
            }
        }

        return [
            'state' => $state,
            'reason_code' => $state->requiresReview() ? $state->value : null,
            'per_good' => $perGood,
        ];
    }

    private function assertModernGoodInputHasProduct(array $attributes, array $scope, array $goodIds, bool $updating = false): void
    {
        if ($goodIds === [] || $scope['discovery'] !== [] || $this->usesLegacyGoodInput($attributes)) {
            return;
        }
        if (! $updating || $this->hasGoodInput($attributes) || $this->hasProductInput($attributes)) {
            throw ValidationException::withMessages([
                'originating_good_ids' => 'Originating Goods require an explicitly selected Product scope.',
            ]);
        }
    }

    private function syncProducts(ProspectingSearchJob $job, array $scope, string $origin): void
    {
        $products = [];
        if ($scope['primary']) {
            $products[$scope['primary']] = ['role' => ProductScopeRole::Primary->value, 'source_origin' => $origin];
        }
        foreach ($scope['additional'] as $id) {
            $products[$id] = ['role' => ProductScopeRole::Additional->value, 'source_origin' => $origin];
        }
        foreach ($scope['exclude'] as $id) {
            $products[$id] = ['role' => ProductScopeRole::Exclude->value, 'source_origin' => $origin];
        }
        $job->products()->sync($products);
    }

    private function syncGoods(ProspectingSearchJob $job, array $goodIds, array $states, bool $legacyInput): void
    {
        $goods = [];
        foreach ($goodIds as $index => $id) {
            $goods[$id] = [
                'role' => $index === 0 ? 'originating' : 'additional_offer',
                'source_origin' => $legacyInput ? 'legacy_api_compatibility' : 'manual_review',
                'compatibility_state' => ($states[$id] ?? ProductMappingState::LegacyUnreconciled)->value,
            ];
        }
        $job->goods()->sync($goods);
    }

    private function refreshGoodCompatibility(ProspectingSearchJob $job, array $states): void
    {
        foreach ($states as $goodId => $state) {
            $job->goods()->updateExistingPivot((int) $goodId, [
                'compatibility_state' => $state->value,
                'updated_at' => now(),
            ]);
        }
    }

    private function assertPublishedProducts(array $ids): void
    {
        if ($ids === []) {
            return;
        }
        $found = Product::query()->without(['category', 'manufacturers'])
            ->where('is_published', true)->whereIn('id', $ids)->count();
        if ($found !== count($ids)) {
            throw ValidationException::withMessages(['product_ids' => 'Only existing published Products may enter prospecting scope.']);
        }
    }

    private function assertPublishedGoods(array $ids): void
    {
        if ($ids === []) {
            return;
        }
        if (Good::query()->where('is_published', true)->whereIn('id', $ids)->count() !== count($ids)) {
            throw ValidationException::withMessages(['originating_good_ids' => 'Only existing published Goods may be selected as offer candidates.']);
        }
    }

    /** @param array{max_domains?: int, max_page_fetch_attempts?: int}|null $serverOwnedLimits */
    private function criteria(array $criteria, ?array $serverOwnedLimits = null): array
    {
        $allowed = collect($criteria)->only([
            'segments', 'industries', 'categories', 'excluded_categories', 'company_types',
            'max_domains', 'max_page_fetch_attempts', 'notes',
        ]);

        return $allowed->map(function ($value, $key) use ($serverOwnedLimits) {
            if ($key === 'notes') {
                return mb_substr(trim((string) $value), 0, 500);
            }
            if ($key === 'max_domains') {
                $ceiling = (int) ($serverOwnedLimits['max_domains']
                    ?? config('ai-sales.find_buyers.limits.max_domains', 10));

                return max(1, min((int) $value, max(1, $ceiling)));
            }
            if ($key === 'max_page_fetch_attempts') {
                $ceiling = (int) ($serverOwnedLimits['max_page_fetch_attempts']
                    ?? config('ai-sales.find_buyers.limits.max_page_fetch_attempts', 5));

                return max(0, min((int) $value, max(0, $ceiling)));
            }

            return collect((array) $value)->take(25)->map(fn ($item) => mb_substr(trim((string) $item), 0, 120))->filter()->values()->all();
        })->all();
    }

    private function safeObjective(string $objective): string
    {
        $objective = mb_substr(trim($objective), 0, 512);
        if ($objective === '') {
            throw ValidationException::withMessages(['safe_objective' => 'A bounded safe objective is required.']);
        }

        return $objective;
    }

    private function boundedInteger(mixed $value, int $minimum, int $maximum): int
    {
        return max($minimum, min((int) $value, max($minimum, $maximum)));
    }

    private function schemaHash(array $criteria, array $scope, array $goodIds): string
    {
        return hash('sha256', json_encode([
            'criteria' => $criteria,
            'products' => [
                'primary' => $scope['primary'],
                'additional' => $scope['additional'],
                'exclude' => $scope['exclude'],
            ],
            'originating_goods' => $goodIds,
        ], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    private function usesLegacyGoodInput(array $attributes): bool
    {
        return array_key_exists('primary_good_id', $attributes) || array_key_exists('additional_good_ids', $attributes);
    }

    private function hasGoodInput(array $attributes): bool
    {
        return array_key_exists('originating_good_ids', $attributes) || $this->usesLegacyGoodInput($attributes);
    }

    private function hasProductInput(array $attributes): bool
    {
        return array_key_exists('primary_product_id', $attributes)
            || array_key_exists('additional_product_ids', $attributes)
            || array_key_exists('excluded_product_ids', $attributes);
    }

    private function fresh(ProspectingSearchJob $job): ProspectingSearchJob
    {
        return $job->fresh([
            'owner:id,name', 'reviewer:id,name', 'primaryGood:id,name',
            'products' => fn ($query) => $query->without(['category', 'manufacturers'])->select(['products.id', 'products.rus', 'products.eng']),
            'goods:id,name',
        ]);
    }
}
