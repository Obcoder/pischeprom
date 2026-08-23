<?php

namespace App\Http\Requests\AiSales;

use App\Domain\AiSales\Enums\UnitProductMatchStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class ReviewUnitProductMatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Gate::allows('review', $this->route('unitProductMatch'));
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                UnitProductMatchStatus::Reviewed->value,
                UnitProductMatchStatus::Approved->value,
                UnitProductMatchStatus::Rejected->value,
                UnitProductMatchStatus::Stale->value,
            ])],
            'provider' => ['prohibited'], 'model' => ['prohibited'], 'contour' => ['prohibited'],
            'prompt' => ['prohibited'], 'tool' => ['prohibited'], 'tools' => ['prohibited'],
            'url' => ['prohibited'], 'execute' => ['prohibited'], 'auto_create_unit' => ['prohibited'],
            'entity_id' => ['prohibited'], 'good_id' => ['prohibited'],
        ];
    }
}
