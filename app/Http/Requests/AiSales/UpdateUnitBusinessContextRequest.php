<?php

namespace App\Http\Requests\AiSales;

class UpdateUnitBusinessContextRequest extends StoreUnitBusinessContextRequest
{
    public function rules(): array
    {
        return collect(parent::rules())
            ->map(fn (array $rules) => collect($rules)
                ->reject(fn (mixed $rule) => $rule === 'required')
                ->prepend('sometimes')
                ->values()
                ->all())
            ->all();
    }
}
