<?php

namespace App\Http\Resources\AiSales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class ClientAcquisitionCampaignResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $products = $this->resource->relationLoaded('products') ? $this->products : collect();
        $latestRun = $this->resource->relationLoaded('runLinks')
            ? $this->runLinks->sortByDesc('id')->first()?->run : null;

        return [
            'id' => $this->public_id,
            'safe_name' => $this->safe_name,
            'safe_objective' => $this->safe_objective,
            'owner' => $this->whenLoaded('owner', fn () => ['id' => $this->owner->id, 'name' => $this->owner->name]),
            'reviewer' => $this->whenLoaded('reviewer', fn () => $this->reviewer ? ['id' => $this->reviewer->id, 'name' => $this->reviewer->name] : null),
            'purpose' => $this->purpose->value,
            'lane' => $this->lane->value,
            'role_code' => $this->role_code->value,
            'status' => $this->status->value,
            'automation_mode' => $this->automation_mode->value,
            'products' => $products->map(fn ($product): array => [
                'id' => $product->id,
                'name' => $product->rus ?: $product->eng,
                'role' => $product->pivot->role,
            ])->values()->all(),
            'originating_good_id' => $this->originating_good_id,
            'criteria' => $this->criteria_snapshot,
            'schedule' => [
                'cadence' => $this->schedule_cadence->value,
                'timezone' => $this->schedule_timezone,
                'next_run_at' => $this->next_run_at?->toIso8601String(),
                'last_run_at' => $this->last_run_at?->toIso8601String(),
            ],
            'limits' => collect($this->getAttributes())->filter(
                fn ($value, $key) => str_starts_with($key, 'max_')
            )->all(),
            'policies' => [
                'auto_unit' => ['code' => $this->auto_unit_policy_code, 'version' => $this->auto_unit_policy_version, 'approved' => $this->auto_unit_approved],
                'auto_draft' => ['code' => $this->auto_draft_policy_code, 'version' => $this->auto_draft_policy_version, 'approved' => $this->auto_draft_approved],
            ],
            'workflow' => ['code' => $this->workflow_code, 'version' => $this->workflow_version, 'hash' => $this->workflow_hash],
            'approval' => [
                'current' => $this->approval_snapshot_hash !== null,
                'approved_at' => $this->approved_at?->toIso8601String(),
            ],
            'latest_run' => $latestRun ? [
                'id' => $latestRun->public_id,
                'status' => $latestRun->status->value,
                'current_step' => $latestRun->current_step,
                'safe_error_code' => $latestRun->safe_error_code,
                'safe_error_summary' => $latestRun->safe_error_summary,
            ] : null,
            'safe_status_summary' => $this->safe_status_summary,
            'live_run_available' => false,
            'email_dispatch_available' => false,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
