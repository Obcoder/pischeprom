<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\Prospecting\ProspectingQueryPlan;
use App\Models\ProspectingSearchJob;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

final class ProspectingQueryPlanPersister
{
    public function persist(ProspectingSearchJob $job, ProspectingQueryPlan $plan): Collection
    {
        return DB::transaction(function () use ($job, $plan): Collection {
            $activeHashes = collect($plan->items)->pluck('queryHash')->all();
            $job->queries()->whereNotNull('plan_hash')->whereNotIn('query_hash', $activeHashes)->update([
                'plan_status' => 'stale',
                'status' => 'stale',
                'updated_at' => now(),
            ]);

            foreach ($plan->items as $item) {
                $query = $job->queries()->firstOrNew(['query_hash' => $item->queryHash]);
                $alreadyApproved = $query->exists
                    && $query->plan_status === 'approved'
                    && hash_equals((string) $query->plan_hash, $plan->planHash);
                $query->fill([
                    'sequence' => 100 + $item->sequence,
                    'template_code' => $item->templateCode,
                    'template_version' => $item->templateVersion,
                    'template_hash' => $item->templateHash,
                    'product_scope_hash' => $plan->productScopeHash,
                    'plan_hash' => $plan->planHash,
                    'plan_status' => $alreadyApproved ? 'approved' : 'review_required',
                    'safe_display_query' => $item->queryText,
                    'language' => $item->language,
                    'geography' => $item->geography,
                    'industry_intent' => $item->industryIntent,
                    'status' => $alreadyApproved ? 'approved' : 'planned',
                    'result_count' => $query->result_count ?? 0,
                    'candidate_count' => $query->candidate_count ?? 0,
                    'search_provider_reference' => null,
                    'executed_at' => null,
                    'plan_approved_by' => $alreadyApproved ? $query->plan_approved_by : null,
                    'plan_approved_at' => $alreadyApproved ? $query->plan_approved_at : null,
                ]);
                $query->save();
            }

            return $job->queries()->whereIn('query_hash', $activeHashes)->orderBy('sequence')->get();
        }, 3);
    }
}
