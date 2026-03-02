<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGoodRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['required','string','max:255'],
            'denominator' => ['nullable','string','max:50'],
            'description' => ['nullable','string'],
            'is_published' => ['nullable','boolean'],

            // ✅ можно без аватарки
            'ava_image' => ['nullable','image','max:4096'],

            // products attach
            'products' => ['nullable','array'],
            'products.*' => ['integer','exists:products,id'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
                         'name' => trim((string)$this->name),
                         'is_published' => filter_var($this->is_published, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? false,
                     ]);
    }
}
