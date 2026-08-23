<?php

namespace App\Http\Requests\AiSales;

use App\Domain\AiSales\Enums\GoodOfferFitStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ReviewUnitGoodMatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('review', $this->route('unitGoodMatch'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(GoodOfferFitStatus::class)],
            'provider' => ['prohibited'], 'model' => ['prohibited'], 'contour' => ['prohibited'],
            'prompt' => ['prohibited'], 'tool' => ['prohibited'], 'tools' => ['prohibited'],
            'url' => ['prohibited'], 'execute' => ['prohibited'], 'auto_create_unit' => ['prohibited'],
            'entity_id' => ['prohibited'],
        ];
    }
}
