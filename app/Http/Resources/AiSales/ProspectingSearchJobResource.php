<?php

namespace App\Http\Resources\AiSales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProspectingSearchJobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'purpose' => $this->purpose->value,
            'lane' => $this->lane->value,
            'default_role_code' => $this->default_role_code->value,
            'status' => $this->status->value,
            'product_mapping_state' => $this->product_mapping_state->value,
            'product_mapping_reason_code' => $this->product_mapping_reason_code,
            'safe_objective' => $this->safe_objective,
            'criteria' => $this->criteria_snapshot ?? [],
            'locale' => $this->locale,
            'limits' => [
                'max_queries' => $this->max_queries,
                'max_candidates' => $this->max_candidates,
                'max_results_per_query' => $this->max_results_per_query,
                'max_rows' => $this->max_rows,
                'max_bytes' => $this->max_bytes,
                'max_searches' => $this->max_searches,
                'max_cost_rub' => $this->max_cost_rub,
            ],
            'auto_create_unit' => false,
            'find_buyers' => [
                'launch_source' => $this->launch_source_type ? [
                    'type' => $this->launch_source_type,
                    'id' => (int) $this->launch_source_id,
                ] : null,
                'wizard_version' => $this->wizard_version,
                'disclosure_policy_hash' => $this->disclosure_policy_hash,
                'idempotency_key_stored_as_hash' => $this->draft_idempotency_key_hash !== null,
                'submitted_at' => $this->submitted_at?->toISOString(),
                'live_execution_allowed' => false,
            ],
            'execution_available' => $this->searchExecutionAvailable($request),
            'search_discovery' => [
                'query_planning_enabled' => (bool) config('ai-sales.prospecting.query_planning_enabled', false),
                'search_execution_enabled' => (bool) config('ai-sales.prospecting.search_execution_enabled', false),
                'page_fetch_enabled' => (bool) config('ai-sales.prospecting.page_fetch_enabled', false),
                'public_research_enabled' => (bool) config('ai-sales.prospecting.public_research_enabled', false),
                'auto_candidate_ingestion' => false,
                'provider_code' => 'existing_yandex',
                'profile_code' => 'prospecting_b2b_discovery',
                'fallback_allowed' => false,
                'stage10_scoring' => false,
            ],
            'owner' => $this->whenLoaded('owner', fn () => ['id' => $this->owner->id, 'name' => $this->owner->name]),
            'reviewer' => $this->whenLoaded('reviewer', fn () => $this->reviewer ? ['id' => $this->reviewer->id, 'name' => $this->reviewer->name] : null),
            'products' => $this->whenLoaded('products', fn () => $this->products->map(fn ($product) => [
                'id' => $product->id, 'name' => $product->rus, 'english_name' => $product->eng,
                'role' => $product->pivot->role, 'source_origin' => $product->pivot->source_origin,
            ])->all()),
            'originating_goods' => $this->whenLoaded('goods', fn () => $this->goods->map(fn ($good) => [
                'id' => $good->id, 'name' => $good->name, 'role' => $good->pivot->role,
                'compatibility_state' => $good->pivot->compatibility_state,
            ])->all()),
            'legacy_good_diagnostics' => $this->when(
                $this->canViewLegacyDiagnostics($request),
                fn () => $this->whenLoaded('primaryGood', fn () => $this->primaryGood ? [
                    'deprecated_primary_good_id' => $this->primaryGood->id,
                    'name' => $this->primaryGood->name,
                ] : null),
            ),
            'approved_at' => $this->approved_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }

    private function canViewLegacyDiagnostics(Request $request): bool
    {
        try {
            return (bool) $request->user()?->hasPermissionTo('ai_sales.classifications.view_internal', 'crm');
        } catch (\Throwable) {
            return false;
        }
    }

    private function searchExecutionAvailable(Request $request): bool
    {
        if (! (bool) config('ai-sales.prospecting.search_execution_enabled', false)
            || ! (bool) config('ai-sales.prospecting.existing_yandex_provider_enabled', false)
            || ! (bool) config('ai-sales.web_search_enabled', false)
            || $this->status->value !== 'approved') {
            return false;
        }

        try {
            return (bool) $request->user()?->hasPermissionTo('ai_sales.search.execute', 'crm');
        } catch (\Throwable) {
            return false;
        }
    }
}
