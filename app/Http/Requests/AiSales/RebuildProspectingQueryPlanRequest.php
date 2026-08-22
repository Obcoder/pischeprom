<?php

namespace App\Http\Requests\AiSales;

use App\Domain\AiSales\Prospecting\BuyerArchetypeRegistry;
use App\Domain\AiSales\Prospecting\ProspectingQueryTemplateRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class RebuildProspectingQueryPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('review', $this->route('prospectingSearchJob'));
    }

    public function rules(): array
    {
        return [
            'target_query_count' => ['required', 'integer', 'min:1', 'max:20'],
            'buyer_archetypes' => ['required', 'array', 'min:1', 'max:10'],
            'buyer_archetypes.*' => [
                'required', 'string', 'distinct',
                Rule::in(app(BuyerArchetypeRegistry::class)->codes()),
            ],
            'intents' => ['required', 'array', 'min:1', 'max:6'],
            'intents.*' => [
                'required', 'string', 'distinct',
                Rule::in(app(ProspectingQueryTemplateRegistry::class)->buyerIntentCodes()),
            ],
            'query' => ['prohibited'],
            'queries' => ['prohibited'],
            'url' => ['prohibited'],
            'provider' => ['prohibited'],
            'profile' => ['prohibited'],
            'model' => ['prohibited'],
            'prompt' => ['prohibited'],
            'execute' => ['prohibited'],
            'auto_create_unit' => ['prohibited'],
            'entity_id' => ['prohibited'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $job = $this->route('prospectingSearchJob');
            $globalMaximum = (int) config('ai-sales.prospecting.limits.max_queries', 20);
            $jobMaximum = is_object($job) ? (int) $job->max_queries : 0;
            if ($this->integer('target_query_count') > min($globalMaximum, $jobMaximum)) {
                $validator->errors()->add(
                    'target_query_count',
                    'The requested query count exceeds the reviewed Search Job ceiling.',
                );
            }
        }];
    }
}
