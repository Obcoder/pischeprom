<?php

namespace App\Http\Requests\AiSales;

use App\Domain\AiSales\Enums\ProspectingPurpose;
use App\Models\ProspectingSearchJob;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class StoreProspectingSearchJobRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('create', ProspectingSearchJob::class);
    }

    public function rules(): array
    {
        return [
            'purpose' => ['required', Rule::enum(ProspectingPurpose::class)],
            'safe_objective' => ['required', 'string', 'max:512'],
            'primary_good_id' => ['nullable', 'integer', 'exists:goods,id'],
            'additional_good_ids' => ['sometimes', 'array', 'max:25'],
            'additional_good_ids.*' => ['integer', 'distinct', 'exists:goods,id'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'region_id' => ['nullable', 'integer', 'exists:regions,id'],
            'city_id' => ['nullable', 'integer', 'exists:cities,id'],
            'locale' => ['sometimes', 'string', 'max:12', 'regex:/^[a-z]{2}(?:-[A-Z]{2})?$/'],
            'max_queries' => ['sometimes', 'integer', 'min:1', 'max:20'],
            'max_candidates' => ['sometimes', 'integer', 'min:1', 'max:250'],
            'max_results_per_query' => ['sometimes', 'integer', 'min:1', 'max:50'],
            'max_rows' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'max_bytes' => ['sometimes', 'integer', 'min:1024', 'max:2097152'],
            'criteria' => ['sometimes', 'array:segments,industries,categories,notes'],
            'criteria.segments' => ['sometimes', 'array', 'max:25'],
            'criteria.segments.*' => ['string', 'max:120'],
            'criteria.industries' => ['sometimes', 'array', 'max:25'],
            'criteria.industries.*' => ['string', 'max:120'],
            'criteria.categories' => ['sometimes', 'array', 'max:25'],
            'criteria.categories.*' => ['string', 'max:120'],
            'criteria.notes' => ['sometimes', 'string', 'max:500'],
            'provider' => ['prohibited'], 'model' => ['prohibited'], 'contour' => ['prohibited'],
            'prompt' => ['prohibited'], 'tool' => ['prohibited'], 'tools' => ['prohibited'],
            'url' => ['prohibited'], 'auto_create_unit' => ['prohibited'], 'execute' => ['prohibited'],
            'entity_id' => ['prohibited'],
        ];
    }
}
