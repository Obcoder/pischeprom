<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateGoodRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'name' => ['sometimes','required','string','max:255'],
            'denominator' => ['sometimes','nullable','string','max:50'],
            'description' => ['sometimes','nullable','string'],
            'is_published' => ['sometimes','nullable','boolean'],
            'ava_image' => ['sometimes','nullable','image','max:4096'],
            'products' => ['sometimes','nullable','array'],
            'products.*' => ['integer','exists:products,id'],

            // опционально: удалить текущий аватар
            'remove_ava' => ['sometimes','boolean'],
        ];
    }
}
