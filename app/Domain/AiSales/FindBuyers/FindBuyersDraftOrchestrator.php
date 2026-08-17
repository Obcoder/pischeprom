<?php

namespace App\Domain\AiSales\FindBuyers;

use App\Domain\AiSales\DTO\FindBuyers\FindBuyersDraftResult;
use App\Domain\AiSales\Enums\ProductScopeRole;
use App\Domain\AiSales\Enums\ProspectingJobStatus;
use App\Domain\AiSales\Enums\ProspectingPurpose;
use App\Domain\AiSales\Services\GoodProductMappingResolver;
use App\Domain\AiSales\Services\ProspectingSearchJobService;
use App\Models\ProspectingSearchJob;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class FindBuyersDraftOrchestrator
{
    public function __construct(
        private readonly FindBuyersFeatureGuard $features,
        private readonly FindBuyersAuthorizationService $authorization,
        private readonly FindBuyersLaunchContextResolver $launchContexts,
        private readonly FindBuyersGeographyService $geography,
        private readonly FindBuyersCriteriaRegistry $criteria,
        private readonly FindBuyersDisclosurePreview $disclosure,
        private readonly GoodProductMappingResolver $mappings,
        private readonly ProspectingSearchJobService $jobs,
    ) {}

    public function create(array $input, User $actor): FindBuyersDraftResult
    {
        $this->features->drafts();
        $this->authorization->authorizeLaunch($actor);
        $context = $this->launchContexts->resolve(
            $actor,
            (string) $input['source_type'],
            (int) $input['source_id'],
            isset($input['selected_product_id']) ? (int) $input['selected_product_id'] : null,
        );
        $this->assertEligible($context->eligibility);
        $idempotencyHash = hash('sha256', $actor->id.'|'.(string) $input['idempotency_key']);
        $existing = $this->findIdempotent($actor, $idempotencyHash);
        if ($existing) {
            $this->assertReplayMatches($existing, $context->source);

            return new FindBuyersDraftResult($this->fresh($existing), false);
        }
        $attributes = $this->jobAttributes($input, $context->toArray());

        try {
            $job = DB::transaction(function () use ($attributes, $context, $idempotencyHash, $actor): ProspectingSearchJob {
                $job = $this->jobs->createDraft($attributes, $actor);
                $job->update([
                    'launch_source_type' => $context->source['type'],
                    'launch_source_id' => $context->source['id'],
                    'wizard_version' => (string) config('ai-sales.find_buyers.wizard_version', 'stage11-v1'),
                    'disclosure_policy_hash' => $context->disclosurePreview['policy_hash'],
                    'draft_idempotency_key_hash' => $idempotencyHash,
                    'policy_version' => 'stage03-disclosure+stage11-find-buyers-v1',
                    'workflow_version' => 'stage11-find-buyers-v1',
                ]);

                return $job;
            }, 3);
        } catch (QueryException $exception) {
            $job = $this->findIdempotent($actor, $idempotencyHash);
            if (! $job) {
                throw $exception;
            }
            $this->assertReplayMatches($job, $context->source);

            return new FindBuyersDraftResult($this->fresh($job), false);
        }

        return new FindBuyersDraftResult($this->fresh($job), true);
    }

    public function update(ProspectingSearchJob $job, array $input, User $actor): ProspectingSearchJob
    {
        $this->features->drafts();
        $this->authorization->authorizeManage($actor);
        $this->assertFindBuyersJob($job);
        if ($job->status !== ProspectingJobStatus::Draft || (int) $job->owner_user_id !== (int) $actor->id) {
            throw ValidationException::withMessages(['job' => 'Only the owner may update a Find Buyers draft.']);
        }
        $currentPrimary = (int) $job->products()->wherePivot('role', ProductScopeRole::Primary->value)->value('products.id');
        $selected = isset($input['selected_product_id']) ? (int) $input['selected_product_id'] : $currentPrimary;
        $context = $this->launchContexts->resolve(
            $actor,
            (string) $job->launch_source_type,
            (int) $job->launch_source_id,
            $selected ?: null,
        );
        $this->assertEligible($context->eligibility);
        $attributes = $this->jobAttributes($input, $context->toArray(), $job);

        return DB::transaction(function () use ($job, $attributes, $actor): ProspectingSearchJob {
            $updated = $this->jobs->updateDraft($job, $attributes, $actor);
            $updated->queries()->whereNotNull('plan_hash')->update([
                'plan_status' => 'stale',
                'status' => 'stale',
                'updated_at' => now(),
            ]);
            $updated->update(['submitted_by' => null, 'submitted_at' => null]);

            return $this->fresh($updated);
        }, 3);
    }

    /** @param array<string, mixed> $context */
    private function jobAttributes(array $input, array $context, ?ProspectingSearchJob $job = null): array
    {
        $primaryProductId = (int) $context['primary_product']['id'];
        $currentProducts = $job ? $job->products()->get(['products.id'])->groupBy('pivot.role') : collect();
        $additional = array_key_exists('additional_product_ids', $input)
            ? array_map('intval', $input['additional_product_ids'])
            : $currentProducts->get(ProductScopeRole::Additional->value, collect())->pluck('id')->map(fn ($id) => (int) $id)->all();
        $excluded = array_key_exists('excluded_product_ids', $input)
            ? array_map('intval', $input['excluded_product_ids'])
            : $currentProducts->get(ProductScopeRole::Exclude->value, collect())->pluck('id')->map(fn ($id) => (int) $id)->all();
        $originatingGoodIds = $this->originatingGoodIds($input, $context, $job, $primaryProductId);
        $geography = $this->geography->validate(
            array_key_exists('country_id', $input) ? ($input['country_id'] ? (int) $input['country_id'] : null) : $job?->country_id,
            array_key_exists('region_id', $input) ? ($input['region_id'] ? (int) $input['region_id'] : null) : $job?->region_id,
            array_key_exists('city_id', $input) ? ($input['city_id'] ? (int) $input['city_id'] : null) : $job?->city_id,
        );
        $criteria = $this->criteriaSnapshot($input, $job?->criteria_snapshot ?? []);
        $limits = $input['limits'] ?? [];
        $productName = mb_substr((string) $context['primary_product']['name'], 0, 255);
        $objective = 'Найти потенциальных покупателей для Product «'.$productName.'»';
        if ($geography['label']) {
            $objective .= ' в географии «'.mb_substr((string) $geography['label'], 0, 120).'»';
        }
        $objective .= '. Product задаёт semantic scope; Good остаётся только возможным коммерческим предложением.';

        return [
            'purpose' => ProspectingPurpose::BuyerDiscovery->value,
            'safe_objective' => mb_substr($objective, 0, 512),
            'primary_product_id' => $primaryProductId,
            'additional_product_ids' => $additional,
            'excluded_product_ids' => $excluded,
            'originating_good_ids' => $originatingGoodIds,
            'explicit_good_product_selection' => true,
            'country_id' => $geography['country_id'],
            'region_id' => $geography['region_id'],
            'city_id' => $geography['city_id'],
            'locale' => 'ru-RU',
            'max_queries' => (int) ($limits['max_queries'] ?? $job?->max_queries ?? 4),
            'max_candidates' => (int) ($limits['max_candidates'] ?? $job?->max_candidates ?? 25),
            'max_results_per_query' => (int) ($limits['max_results_per_query'] ?? $job?->max_results_per_query ?? 10),
            'max_rows' => min(250, (int) ($limits['max_candidates'] ?? $job?->max_rows ?? 25)),
            'max_bytes' => min(1_048_576, (int) ($job?->max_bytes ?? 1_048_576)),
            'criteria' => $criteria,
        ];
    }

    /** @return list<int> */
    private function originatingGoodIds(array $input, array $context, ?ProspectingSearchJob $job, int $primaryProductId): array
    {
        if ($context['source']['type'] === FindBuyersLaunchContextResolver::SOURCE_GOOD) {
            if (isset($input['originating_good_id']) && (int) $input['originating_good_id'] !== (int) $context['source']['id']) {
                throw ValidationException::withMessages(['originating_good_id' => 'Good launch source cannot be substituted.']);
            }
            $ids = [(int) $context['source']['id']];
        } elseif (array_key_exists('originating_good_id', $input)) {
            $ids = $input['originating_good_id'] ? [(int) $input['originating_good_id']] : [];
        } elseif ($job) {
            $ids = $job->goods()->pluck('goods.id')->map(fn ($id) => (int) $id)->unique()->values()->all();
        } else {
            $ids = [];
        }

        foreach ($ids as $goodId) {
            if ($this->mappings->stateForExplicitProduct($goodId, $primaryProductId)->value !== 'mapped') {
                throw ValidationException::withMessages(['originating_good_id' => 'Originating Good must be related to the selected Product.']);
            }
        }

        return $ids;
    }

    /** @return array<string, mixed> */
    private function criteriaSnapshot(array $input, array $current): array
    {
        $criteriaKeys = [
            'industry_ids', 'included_category_ids', 'excluded_category_ids',
            'company_activity_codes', 'company_type_code', 'limits',
        ];
        if (! collect($criteriaKeys)->contains(fn (string $key): bool => array_key_exists($key, $input))) {
            return $current;
        }
        $includedCategoryIds = array_key_exists('included_category_ids', $input)
            ? array_map('intval', $input['included_category_ids']) : null;
        $excludedCategoryIds = array_key_exists('excluded_category_ids', $input)
            ? array_map('intval', $input['excluded_category_ids']) : null;
        if ($includedCategoryIds !== null && $excludedCategoryIds !== null
            && array_intersect($includedCategoryIds, $excludedCategoryIds) !== []) {
            throw ValidationException::withMessages(['excluded_category_ids' => 'A category cannot be both included and excluded.']);
        }
        $industries = array_key_exists('industry_ids', $input)
            ? $this->labels('industries', 'title', array_map('intval', $input['industry_ids']), false)
            : (array) ($current['industries'] ?? []);
        $categories = $includedCategoryIds !== null
            ? $this->labels('categories', 'name', $includedCategoryIds, true)
            : (array) ($current['categories'] ?? []);
        $excludedCategories = $excludedCategoryIds !== null
            ? $this->labels('categories', 'name', $excludedCategoryIds, true)
            : (array) ($current['excluded_categories'] ?? []);
        if (array_intersect($categories, $excludedCategories) !== []) {
            throw ValidationException::withMessages(['excluded_category_ids' => 'A category cannot be both included and excluded.']);
        }
        $segments = array_key_exists('company_activity_codes', $input)
            ? $this->criteria->activityLabels($input['company_activity_codes'])
            : (array) ($current['segments'] ?? []);
        $companyTypes = array_key_exists('company_type_code', $input)
            ? array_values(array_filter([$this->criteria->companyTypeLabel($input['company_type_code'])]))
            : (array) ($current['company_types'] ?? []);
        $limits = $input['limits'] ?? [];

        return [
            'segments' => $segments,
            'industries' => $industries,
            'categories' => $categories,
            'excluded_categories' => $excludedCategories,
            'company_types' => $companyTypes,
            'max_domains' => (int) ($limits['max_domains'] ?? $current['max_domains'] ?? 5),
            'max_page_fetch_attempts' => (int) ($limits['max_page_fetch_attempts'] ?? $current['max_page_fetch_attempts'] ?? 0),
        ];
    }

    /** @param list<int> $ids
     * @return list<string>
     */
    private function labels(string $table, string $column, array $ids, bool $published): array
    {
        if ($ids === []) {
            return [];
        }
        $query = DB::table($table)->whereIn('id', $ids);
        if ($published) {
            $query->where('is_published', true);
        }
        $rows = $query->select(['id', $column])->get()->keyBy('id');
        if ($rows->count() !== count(array_unique($ids))) {
            throw ValidationException::withMessages([$table => 'One or more selected dictionary values are unavailable.']);
        }

        return collect($ids)->unique()->map(fn (int $id): string => mb_substr((string) $rows[$id]->{$column}, 0, 120))->all();
    }

    private function assertEligible(array $eligibility): void
    {
        if (! ($eligibility['eligible'] ?? false)) {
            throw ValidationException::withMessages([
                'launch_context' => (string) ($eligibility['message'] ?? 'Find Buyers launch is blocked.'),
            ]);
        }
    }

    private function assertFindBuyersJob(ProspectingSearchJob $job): void
    {
        if ($job->purpose !== ProspectingPurpose::BuyerDiscovery
            || $job->launch_source_type === null
            || $job->wizard_version !== (string) config('ai-sales.find_buyers.wizard_version', 'stage11-v1')) {
            throw ValidationException::withMessages(['job' => 'This is not a Stage 11 Find Buyers job.']);
        }
    }

    private function assertReplayMatches(ProspectingSearchJob $job, array $source): void
    {
        if ($job->launch_source_type !== $source['type'] || (int) $job->launch_source_id !== (int) $source['id']) {
            throw ValidationException::withMessages(['idempotency_key' => 'Idempotency key was already used for another launch source.']);
        }
    }

    private function findIdempotent(User $actor, string $hash): ?ProspectingSearchJob
    {
        return ProspectingSearchJob::query()->where('created_by', $actor->id)
            ->where('draft_idempotency_key_hash', $hash)->first();
    }

    private function fresh(ProspectingSearchJob $job): ProspectingSearchJob
    {
        return $job->fresh([
            'owner:id,name', 'reviewer:id,name', 'submitter:id,name', 'primaryGood:id,name',
            'products' => fn ($query) => $query->without(['category', 'manufacturers'])
                ->select(['products.id', 'products.rus', 'products.eng']),
            'goods:id,name',
        ]);
    }
}
