<?php

namespace App\Http\Requests\AiSales;

use Illuminate\Support\Facades\Gate;

class UpdateProspectingSearchJobRequest extends StoreProspectingSearchJobRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('prospectingSearchJob'));
    }

    public function rules(): array
    {
        $rules = parent::rules();
        $rules['purpose'][0] = 'sometimes';
        $rules['safe_objective'][0] = 'sometimes';

        return $rules;
    }
}
