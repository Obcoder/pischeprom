<?php

namespace App\Http\Requests\Unit;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SearchCompaniesRequest extends FormRequest
{
    public function authorize(): bool
    {
        // The web route is protected by RequireStaffAuthentication.
        return true;
    }

    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'min:2', 'max:300'],
            'okved' => ['required', 'array', 'min:1', 'max:10'],
            'okved.*' => ['required', 'string', 'distinct', 'regex:/^[0-9]{2}(?:\.[0-9](?:[0-9](?:\.[0-9]{1,2})?)?)?$/D'],
            'status' => ['sometimes', 'array', 'max:5'],
            'status.*' => ['required', 'string', 'distinct', Rule::in([
                'ACTIVE', 'LIQUIDATING', 'LIQUIDATED', 'BANKRUPT', 'REORGANIZING',
            ])],
            'type' => ['nullable', Rule::in(['LEGAL', 'INDIVIDUAL'])],
            'region_code' => ['nullable', 'string', 'regex:/^(?:0[1-9]|[1-9][0-9])$/D'],
            'count' => ['sometimes', 'integer', 'min:1', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'query.required' => 'Укажите название, ИНН или адрес для поиска в DaData.',
            'query.min' => 'Введите не менее двух символов для поиска.',
            'okved.required' => 'Укажите хотя бы один код ОКВЭД.',
            'okved.max' => 'Можно указать не более 10 кодов ОКВЭД.',
            'okved.*.regex' => 'Укажите код ОКВЭД, например 10.51 или 10.51.1, без масок.',
            'okved.*.distinct' => 'Коды ОКВЭД не должны повторяться.',
            'region_code.regex' => 'Укажите двузначный код региона, например 77 или 02.',
            'count.max' => 'DaData возвращает не более 20 результатов за запрос.',
        ];
    }
}
