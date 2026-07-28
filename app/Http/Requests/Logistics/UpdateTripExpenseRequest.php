<?php

namespace App\Http\Requests\Logistics;

class UpdateTripExpenseRequest extends StoreTripExpenseRequest
{
    public function authorize(): bool
    {
        $expense = $this->route('expense');

        return $expense && (bool) $this->user()?->can('update', $expense);
    }
}
