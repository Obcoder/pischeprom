<?php

namespace App\Http\Resources\AiSales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AiAgentRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'definition' => ['code' => $this->definition_code, 'version' => $this->definition_version],
            'unit' => ['id' => $this->unit_id, 'name_snapshot' => $this->unit_name_snapshot],
            'unit_business_context_id' => $this->unit_business_context_id,
            'purpose' => $this->purpose->value,
            'audience' => $this->audience->value,
            'lane' => $this->lane->value,
            'role_code' => $this->role_code->value,
            'task_profile' => $this->task_profile->value,
            'requested_contour' => $this->requested_contour->value,
            'selected_contour' => $this->selected_contour?->value,
            'provider' => $this->actual_provider,
            'provider_route' => $this->actual_route,
            'model' => $this->actual_model,
            'status' => $this->status->value,
            'reason_code' => $this->safe_error_code,
            'safe_error_summary' => $this->safe_error_summary,
            'policy_decision_hash' => $this->policy_decision_hash,
            'safe_input' => [
                'summary' => $this->safe_input_summary,
                'hash' => $this->safe_input_hash,
            ],
            'budgets' => [
                'max_steps' => $this->max_steps,
                'max_searches' => $this->max_searches,
                'max_tokens' => $this->max_tokens,
                'max_cost_rub' => $this->max_cost_rub,
                'used_tokens' => $this->accumulated_tokens,
                'used_searches' => $this->accumulated_searches,
                'used_cost_rub' => $this->accumulated_cost_rub,
            ],
            'steps' => $this->whenLoaded('steps', fn () => $this->steps->map(fn ($step) => [
                'sequence' => $step->sequence,
                'type' => $step->step_type,
                'contour' => $step->contour->value,
                'provider' => $step->provider_code,
                'route' => $step->provider_route,
                'model' => $step->model_id,
                'status' => $step->status->value,
                'safe_request_summary' => $step->safe_request_summary,
                'normalized_output_metadata' => $step->normalized_output_metadata,
                'provider_request_id' => $step->provider_request_id,
                'retry_count' => $step->retry_count,
                'failover_count' => $step->failover_count,
                'safe_error_code' => $step->safe_error_code,
            ])->values()->all()),
            'queued_at' => $this->queued_at?->toISOString(),
            'prepared_at' => $this->prepared_at?->toISOString(),
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'cancelled_at' => $this->cancelled_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }
}
