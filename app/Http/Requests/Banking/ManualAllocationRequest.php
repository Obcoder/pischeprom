<?php

namespace App\Http\Requests\Banking;

use Illuminate\Foundation\Http\FormRequest;

class ManualAllocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('bank.reconcile') ?? false;
    }

    protected function prepareForValidation(): void
    {
        $allocations = collect($this->input('allocations', []))
            ->map(function (mixed $item): mixed {
                if (! is_array($item) || ! isset($item['amount']) || ! is_string($item['amount'])) {
                    return $item;
                }

                $item['amount'] = str_replace([' ', ','], ['', '.'], $item['amount']);

                return $item;
            })
            ->all();

        $this->merge(['allocations' => $allocations]);
    }

    public function rules(): array
    {
        return [
            'allocations' => ['required', 'array', 'min:1', 'max:50'],
            'allocations.*.sale_id' => ['required', 'integer', 'distinct', 'exists:sales,id'],
            'allocations.*.amount' => ['required', 'string', 'regex:/^\d{1,18}(?:\.\d{1,2})?$/', 'not_in:0,0.0,0.00'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
