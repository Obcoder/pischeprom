<?php

namespace App\Http\Requests\AiSales;

use App\Domain\AiSales\Campaigns\Enums\ClientAcquisitionAutomationMode;
use App\Domain\AiSales\Campaigns\Enums\ClientAcquisitionCampaignCadence;
use App\Models\ClientAcquisitionCampaign;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreClientAcquisitionCampaignRequest extends FormRequest
{
    private const LIMITS = [
        'max_active_runs', 'max_runs_per_day', 'max_runs_per_month',
        'max_search_requests_per_run', 'max_search_requests_per_day', 'max_search_requests_per_month',
        'max_research_pages_per_run', 'max_candidates_per_run', 'max_units_per_run',
        'max_units_per_day', 'max_units_per_month', 'max_drafts_per_run', 'max_drafts_per_day',
        'max_drafts_per_month', 'max_requests_per_run', 'max_requests_per_day',
        'max_requests_per_month', 'max_tokens_per_run', 'max_tokens_per_day', 'max_tokens_per_month',
        'max_cost_rub_per_run', 'max_cost_rub_per_day', 'max_cost_rub_per_month',
    ];

    public function authorize(): bool
    {
        return Gate::allows('create', ClientAcquisitionCampaign::class);
    }

    public function rules(): array
    {
        $rules = [
            'safe_name' => ['required', 'string', 'max:160'],
            'safe_objective' => ['required', 'string', 'max:512'],
            'reviewer_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'primary_product_id' => ['required', 'integer', 'exists:products,id'],
            'additional_product_ids' => ['sometimes', 'array', 'max:25'],
            'additional_product_ids.*' => ['integer', 'distinct', 'exists:products,id'],
            'excluded_product_ids' => ['sometimes', 'array', 'max:25'],
            'excluded_product_ids.*' => ['integer', 'distinct', 'exists:products,id'],
            'originating_good_id' => ['nullable', 'integer', 'exists:goods,id'],
            'automation_mode' => ['sometimes', Rule::enum(ClientAcquisitionAutomationMode::class)],
            'auto_unit_approved' => ['sometimes', 'boolean'],
            'auto_draft_approved' => ['sometimes', 'boolean'],
            'schedule_cadence' => ['sometimes', Rule::enum(ClientAcquisitionCampaignCadence::class)],
            'schedule_timezone' => ['sometimes', 'timezone:all'],
            'next_run_at' => ['nullable', 'date'],
            'criteria' => ['sometimes', 'array:country_id,region_id,city_id,segments,industries,categories,excluded_categories,company_types,max_domains,max_page_fetch_attempts,max_results_per_query'],
            'criteria.country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'criteria.region_id' => ['nullable', 'integer', 'exists:regions,id'],
            'criteria.city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'criteria.segments' => ['sometimes', 'array', 'max:25'],
            'criteria.segments.*' => ['string', 'max:120'],
            'criteria.industries' => ['sometimes', 'array', 'max:25'],
            'criteria.industries.*' => ['string', 'max:120'],
            'criteria.categories' => ['sometimes', 'array', 'max:25'],
            'criteria.categories.*' => ['string', 'max:120'],
            'criteria.excluded_categories' => ['sometimes', 'array', 'max:25'],
            'criteria.excluded_categories.*' => ['string', 'max:120'],
            'criteria.company_types' => ['sometimes', 'array', 'max:25'],
            'criteria.company_types.*' => ['string', 'max:120'],
            'criteria.max_domains' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'criteria.max_page_fetch_attempts' => ['sometimes', 'integer', 'min:1', 'max:25'],
            'criteria.max_results_per_query' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'limits' => ['required', 'array:'.implode(',', self::LIMITS)],
            'purpose' => ['prohibited'], 'lane' => ['prohibited'], 'role_code' => ['prohibited'],
            'workflow' => ['prohibited'], 'workflow_code' => ['prohibited'], 'stage' => ['prohibited'],
            'provider' => ['prohibited'], 'model' => ['prohibited'], 'contour' => ['prohibited'],
            'prompt' => ['prohibited'], 'query' => ['prohibited'], 'url' => ['prohibited'],
            'tool' => ['prohibited'], 'tools' => ['prohibited'], 'scheduler' => ['prohibited'],
            'entity_id' => ['prohibited'], 'consent' => ['prohibited'], 'dispatch' => ['prohibited'],
        ];
        foreach (self::LIMITS as $field) {
            $rules['limits.'.$field] = str_starts_with($field, 'max_cost_')
                ? ['required', 'numeric', 'min:0', 'max:1000000']
                : ['required', 'integer', 'min:0', 'max:1000000000'];
        }

        return $rules;
    }
}
