<?php

namespace App\Http\Requests;

use App\Services\Products\ProductTranslationAiService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Throwable;

class TranslateProductAiRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        if (! $user || $user->status === 'blocked') {
            return false;
        }

        if ($user->type === 'employee') {
            return true;
        }

        try {
            return $user->hasRole('admin', 'crm');
        } catch (Throwable) {
            return false;
        }
    }

    public function rules(): array
    {
        return [
            'rus' => ['required', 'string', 'max:255'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'languages' => ['required', 'array', 'list', 'min:1', 'max:18'],
            'languages.*' => ['required', 'string', 'distinct', Rule::in(array_keys(ProductTranslationAiService::LANGUAGES))],
        ];
    }

    public function messages(): array
    {
        return [
            'rus.required' => 'Введите русское название продукта.',
            'rus.string' => 'Название продукта должно содержать текст.',
            'rus.max' => 'Название продукта не должно превышать 255 символов.',
            'category_id.exists' => 'Выбранная категория не найдена.',
            'languages.required' => 'Выберите языки для перевода.',
            'languages.*.in' => 'Этот язык не поддерживается.',
            'languages.*.distinct' => 'Языки для перевода не должны повторяться.',
        ];
    }
}
