<?php

namespace App\Http\Requests\AiSales;

use App\Domain\AiSales\FindBuyers\FindBuyersCriteriaRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFindBuyersDraftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        $criteria = app(FindBuyersCriteriaRegistry::class);

        return [
            'source_type' => ['required', Rule::in(['product', 'good'])],
            'source_id' => ['required', 'integer', 'min:1'],
            'selected_product_id' => ['nullable', 'integer', 'min:1'],
            'idempotency_key' => ['required', 'uuid', 'max:36'],
            ...$this->wizardRules($criteria),
        ];
    }

    /** @return array<string, array<int, mixed>> */
    protected function wizardRules(FindBuyersCriteriaRegistry $criteria): array
    {
        $additionalCap = (int) config('ai-sales.find_buyers.limits.additional_products', 10);
        $excludedCap = (int) config('ai-sales.find_buyers.limits.excluded_products', 10);
        $industryCap = (int) config('ai-sales.find_buyers.limits.industries', 10);
        $categoryCap = (int) config('ai-sales.find_buyers.limits.categories', 10);

        return [
            'additional_product_ids' => ['sometimes', 'array', 'max:'.$additionalCap],
            'additional_product_ids.*' => ['integer', 'distinct', 'exists:products,id'],
            'excluded_product_ids' => ['sometimes', 'array', 'max:'.$excludedCap],
            'excluded_product_ids.*' => ['integer', 'distinct', 'exists:products,id'],
            'originating_good_id' => ['nullable', 'integer', 'exists:goods,id'],
            'industry_ids' => ['sometimes', 'array', 'max:'.$industryCap],
            'industry_ids.*' => ['integer', 'distinct', 'exists:industries,id'],
            'included_category_ids' => ['sometimes', 'array', 'max:'.$categoryCap],
            'included_category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
            'excluded_category_ids' => ['sometimes', 'array', 'max:'.$categoryCap],
            'excluded_category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
            'company_activity_codes' => ['sometimes', 'array', 'max:5'],
            'company_activity_codes.*' => ['string', 'distinct', Rule::in($criteria->activityCodes())],
            'company_type_code' => ['nullable', 'string', Rule::in($criteria->companyTypeCodes())],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'region_id' => ['nullable', 'integer', 'exists:regions,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'limits' => ['sometimes', 'array:max_queries,max_results_per_query,max_domains,max_page_fetch_attempts,max_candidates'],
            'limits.max_queries' => ['sometimes', 'integer', 'min:1', 'max:'.config('ai-sales.find_buyers.limits.max_queries', 10)],
            'limits.max_results_per_query' => ['sometimes', 'integer', 'min:1', 'max:'.config('ai-sales.find_buyers.limits.max_results_per_query', 20)],
            'limits.max_domains' => ['sometimes', 'integer', 'min:1', 'max:'.config('ai-sales.find_buyers.limits.max_domains', 10)],
            'limits.max_page_fetch_attempts' => ['sometimes', 'integer', 'min:0', 'max:'.config('ai-sales.find_buyers.limits.max_page_fetch_attempts', 5)],
            'limits.max_candidates' => ['sometimes', 'integer', 'min:1', 'max:'.config('ai-sales.find_buyers.limits.max_candidates', 50)],
            'purpose' => ['prohibited'], 'lane' => ['prohibited'], 'role_code' => ['prohibited'],
            'match_type' => ['prohibited'], 'primary_product_id' => ['prohibited'],
            'safe_objective' => ['prohibited'], 'criteria' => ['prohibited'], 'notes' => ['prohibited'],
            'query' => ['prohibited'], 'provider' => ['prohibited'], 'profile' => ['prohibited'],
            'model' => ['prohibited'], 'contour' => ['prohibited'], 'prompt' => ['prohibited'],
            'url' => ['prohibited'], 'tool' => ['prohibited'], 'tools' => ['prohibited'],
            'execute' => ['prohibited'], 'auto_create_unit' => ['prohibited'], 'entity_id' => ['prohibited'],
            'explicit_good_product_selection' => ['prohibited'],
        ];
    }
}
