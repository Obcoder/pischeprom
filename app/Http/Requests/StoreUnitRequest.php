<?php

namespace App\Http\Requests;

use App\Support\PhoneNumber;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreUnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Both creation routes are protected by the shared staff boundary.
        return true;
    }

    protected function prepareForValidation(): void
    {
        foreach (['new_emails', 'new_telephones', 'new_uris'] as $key) {
            if (! is_array($this->input($key))) {
                continue;
            }

            $this->merge([$key => array_map(function ($value) use ($key) {
                if (! is_string($value)) {
                    return $value;
                }

                $value = trim($value);

                if ($key === 'new_emails') {
                    return Str::lower($value);
                }

                if ($key === 'new_telephones' && preg_match('/^[+\d\s().-]+$/', $value)) {
                    return PhoneNumber::russian($value) ?? '+'.preg_replace('/\D/', '', $value);
                }

                if ($key === 'new_uris' && $value !== '' && ! preg_match('/^[a-z][a-z\d+.-]*:/i', $value)) {
                    return 'https://'.$value;
                }

                return $value;
            }, $this->input($key))]);
        }
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'is_customer' => ['sometimes', 'boolean'],
            'is_supplier' => ['sometimes', 'boolean'],
            'building_apartments' => ['sometimes', 'array'],
            'building_apartments.*' => ['nullable', 'integer', 'exists:apartments,id'],
            'primary_industry_id' => [
                'nullable', 'integer', 'exists:industries,id',
                Rule::in(is_array($this->input('industries')) ? $this->input('industries') : []),
            ],
            'new_emails' => ['sometimes', 'array', 'max:100'],
            'new_emails.*' => ['bail', 'required', 'string', 'max:255', 'email:rfc'],
            'new_telephones' => ['sometimes', 'array', 'max:100'],
            'new_telephones.*' => ['bail', 'required', 'string', 'max:16', 'regex:/^\+[0-9]{7,15}$/'],
            'new_uris' => ['sometimes', 'array', 'max:100'],
            'new_uris.*' => ['bail', 'required', 'string', 'max:255', 'url:http,https'],
        ];

        foreach ([
            'buildings', 'cities', 'emails', 'fields', 'industries', 'labels',
            'telephones', 'uris', 'entities', 'classifications',
        ] as $relation) {
            $table = $relation === 'classifications' ? 'entity_classifications' : $relation;
            $exists = Rule::exists($table, 'id');

            if ($relation === 'emails') {
                $exists->whereNull('deleted_at');
            }

            $rules[$relation] = ['sometimes', 'array', 'max:500'];
            $rules[$relation.'.*'] = ['integer', 'distinct', $exists];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Укажите название Unit.',
            'name.max' => 'Название не должно превышать 255 символов.',
            '*.exists' => 'Выбранная запись больше не существует. Обновите список.',
            'primary_industry_id.in' => 'Основной ОКВЭД должен входить в выбранные виды деятельности.',
            'new_emails.*.email' => 'Укажите корректный адрес электронной почты.',
            'new_telephones.*.regex' => 'Укажите телефон с кодом страны, например +7 999 123-45-67.',
            'new_uris.*.url' => 'Укажите корректную ссылку с протоколом http или https.',
        ];
    }
}
