<?php

namespace App\Http\Requests\AiSales;

use Illuminate\Support\Facades\Gate;

final class UpdateClientAcquisitionCampaignRequest extends StoreClientAcquisitionCampaignRequest
{
    public function authorize(): bool
    {
        return Gate::allows('update', $this->route('clientAcquisitionCampaign'));
    }

    public function rules(): array
    {
        $rules = parent::rules();
        foreach ($rules as $field => &$fieldRules) {
            if ($field === 'limits') {
                $fieldRules = ['sometimes', ...array_slice($fieldRules, 1)];

                continue;
            }
            if (str_starts_with($field, 'limits.')) {
                $fieldRules[0] = 'sometimes';

                continue;
            }
            if (in_array($field, ['safe_name', 'safe_objective', 'primary_product_id'], true)) {
                $fieldRules[0] = 'sometimes';
            }
        }

        return $rules;
    }
}
