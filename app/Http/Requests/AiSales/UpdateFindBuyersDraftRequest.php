<?php

namespace App\Http\Requests\AiSales;

use App\Domain\AiSales\FindBuyers\FindBuyersCriteriaRegistry;

class UpdateFindBuyersDraftRequest extends StoreFindBuyersDraftRequest
{
    public function rules(): array
    {
        return [
            'source_type' => ['prohibited'],
            'source_id' => ['prohibited'],
            'idempotency_key' => ['prohibited'],
            'selected_product_id' => ['nullable', 'integer', 'min:1'],
            ...$this->wizardRules(app(FindBuyersCriteriaRegistry::class)),
        ];
    }
}
