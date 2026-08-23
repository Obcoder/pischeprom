<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\Enums\AiRunStatus;
use App\Domain\AiSales\Enums\ProspectingJobStatus;
use App\Domain\AiSales\Prospecting\BuyerArchetypeRegistry;
use App\Domain\AiSales\Prospecting\ProspectingQueryTemplateRegistry;
use App\Domain\AiSales\Support\AiCanonicalJson;
use App\Models\AiAgentRun;
use App\Models\ClientAcquisitionCampaignRunLink;
use App\Models\ProspectingSearchJob;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RebuildProspectingQueryPlan
{
    public const PREFERENCE_VERSION = 'reviewed-query-plan-preferences-v1';

    public function __construct(
        private readonly ProspectingFeatureGuard $features,
        private readonly ProspectingAuthorizationService $authorization,
        private readonly BuyerArchetypeRegistry $archetypes,
        private readonly ProspectingQueryTemplateRegistry $templates,
        private readonly PlanProspectingQueries $planner,
    ) {}

    /** @param array{target_query_count: int, buyer_archetypes: list<string>, intents: list<string>} $input */
    public function handle(ProspectingSearchJob $job, array $input, User $actor): Collection
    {
        $this->features->queryPlanning();
        $this->authorization->authorize($actor, ProspectingAuthorizationService::PLAN_SEARCH, $job->lane);
        $this->authorization->authorize($actor, ProspectingAuthorizationService::REVIEW_SEARCH, $job->lane);

        $target = (int) $input['target_query_count'];
        $archetypes = $this->canonicalValues(
            $input['buyer_archetypes'], $this->archetypes->codes(), 'buyer_archetypes',
        );
        $intents = $this->canonicalValues($input['intents'], $this->templates->buyerIntentCodes(), 'intents');
        $maximum = min(
            (int) $job->max_queries,
            (int) config('ai-sales.prospecting.limits.max_queries', 20),
        );
        if ($target < 1 || $target > $maximum || $archetypes === [] || $intents === []) {
            throw ValidationException::withMessages([
                'query_plan' => 'The reviewed query-plan selection is outside its code-owned bounds.',
            ]);
        }

        $productCount = $job->products()->wherePivotIn('role', ['primary', 'additional'])->count();
        if ($target > ($productCount * count($archetypes) * count($intents))) {
            throw ValidationException::withMessages([
                'target_query_count' => 'Select more buyer archetypes or search intents for the requested query count.',
            ]);
        }

        return DB::transaction(function () use ($job, $target, $archetypes, $intents, $actor, $maximum): Collection {
            $locked = ProspectingSearchJob::query()->whereKey($job->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== ProspectingJobStatus::Approved
                || $locked->launch_source_type !== 'campaign'
                || $locked->searchExecutions()->exists()
                || $locked->searchResults()->exists()
                || $locked->candidates()->exists()
                || $locked->queries()->where('plan_status', 'approved')->exists()) {
                throw ValidationException::withMessages([
                    'query_plan' => 'Only an unexecuted Campaign query plan awaiting human review may be rebuilt.',
                ]);
            }

            $link = ClientAcquisitionCampaignRunLink::query()
                ->where('prospecting_search_job_id', $locked->id)
                ->lockForUpdate()
                ->first();
            $run = $link
                ? AiAgentRun::query()->whereKey($link->ai_agent_run_id)->lockForUpdate()->first()
                : null;
            if (! $run
                || $run->status !== AiRunStatus::RequiresAction
                || ! hash_equals('query_plan_review_required', (string) $run->safe_error_code)) {
                throw ValidationException::withMessages([
                    'query_plan' => 'The linked Campaign run is not waiting for query-plan review.',
                ]);
            }

            $criteria = (array) ($locked->criteria_snapshot ?? []);
            $baseSchemaHash = $this->baseSchemaHash(
                $locked,
                collect($criteria)->except('query_plan_preferences')->all(),
            );
            $preferences = [
                'version' => self::PREFERENCE_VERSION,
                'target_query_count' => $target,
                'buyer_archetypes' => $archetypes,
                'intents' => $intents,
            ];
            $criteria['query_plan_preferences'] = $preferences;
            $locked->update([
                'criteria_snapshot' => $criteria,
                'schema_hash' => AiCanonicalJson::hash([
                    'base_schema_hash' => $baseSchemaHash,
                    'query_plan_preferences' => $preferences,
                ]),
            ]);

            $currentPlanRows = $locked->queries()->whereNotNull('plan_hash')
                ->where('plan_status', '!=', 'stale')
                ->orderBy('sequence')->lockForUpdate()->get();
            $nextAuditSequence = max(
                (int) $locked->queries()->max('sequence'),
                100 + $maximum,
            ) + 1;
            if ($nextAuditSequence + $currentPlanRows->count() > 65535) {
                throw ValidationException::withMessages([
                    'query_plan' => 'The query-plan audit sequence range is exhausted.',
                ]);
            }
            foreach ($currentPlanRows as $row) {
                $row->update([
                    'sequence' => $nextAuditSequence++,
                    'plan_status' => 'stale',
                    'status' => 'stale',
                    'plan_approved_by' => null,
                    'plan_approved_at' => null,
                ]);
            }

            return $this->planner->handle($locked->fresh(), $actor);
        }, 3);
    }

    /** @param list<string> $selected
     * @param  list<string>  $allowed
     * @return list<string>
     */
    private function canonicalValues(array $selected, array $allowed, string $field): array
    {
        $values = collect($selected)->filter(fn ($value): bool => is_string($value))->unique()->values();
        if ($values->count() !== count($selected) || $values->diff($allowed)->isNotEmpty()) {
            throw ValidationException::withMessages([
                $field => 'Every query-plan option must be unique and selected from the code-owned registry.',
            ]);
        }

        return collect($allowed)->filter(fn (string $value): bool => $values->contains($value))->values()->all();
    }

    /** @param array<string, mixed> $criteria */
    private function baseSchemaHash(ProspectingSearchJob $job, array $criteria): string
    {
        $products = DB::table('prospecting_search_job_products')
            ->where('prospecting_search_job_id', $job->id)
            ->whereIn('role', ['primary', 'additional', 'exclude'])
            ->orderBy('role')
            ->orderBy('product_id')
            ->get(['product_id', 'role']);
        $primary = $products->firstWhere('role', 'primary');
        $scope = [
            'primary' => $primary ? (int) $primary->product_id : null,
            'additional' => $products->where('role', 'additional')->pluck('product_id')->map('intval')->values()->all(),
            'exclude' => $products->where('role', 'exclude')->pluck('product_id')->map('intval')->values()->all(),
        ];
        $goodIds = DB::table('prospecting_search_job_goods')
            ->where('prospecting_search_job_id', $job->id)
            ->orderBy('role')
            ->orderBy('good_id')->pluck('good_id')->map('intval')->values()->all();

        return AiCanonicalJson::hash([
            'criteria' => $criteria,
            'products' => $scope,
            'originating_goods' => $goodIds,
        ]);
    }
}
