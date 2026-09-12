<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Throwable;

class GenerateGoodSeoAiRequest extends FormRequest
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
        $rules = [
            'field' => ['required', Rule::in(['h1', 'meta_title', 'meta_description', 'short_seo_text', 'seo_text'])],
            'context' => ['sometimes', 'array:focus_keyword,h1,meta_title,meta_description,short_seo_text,seo_text,semantic_core,keywords,search_queries,min_order,delivery_note,payment_note'],
            'context.focus_keyword' => ['nullable', 'string', 'max:255'],
            'context.h1' => ['nullable', 'string', 'max:255'],
            'context.meta_title' => ['nullable', 'string', 'max:255'],
            'context.meta_description' => ['nullable', 'string', 'max:2000'],
            'context.short_seo_text' => ['nullable', 'string', 'max:5000'],
            'context.seo_text' => ['nullable', 'string', 'max:20000'],
            'context.min_order' => ['nullable', 'string', 'max:255'],
            'context.delivery_note' => ['nullable', 'string', 'max:2000'],
            'context.payment_note' => ['nullable', 'string', 'max:2000'],
        ];

        foreach (['semantic_core', 'keywords', 'search_queries'] as $field) {
            $rules["context.{$field}"] = ['nullable', 'array', 'max:40'];
            $rules["context.{$field}.*"] = ['string', 'max:255'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'field.required' => 'Выберите поле для AI-заполнения.',
            'field.in' => 'Это поле не поддерживает AI-заполнение.',
            'context.array' => 'Контекст содержит неподдерживаемые поля или имеет неверный формат.',
            'string' => 'Поле «:attribute» должно содержать текст.',
            'array' => 'Поле «:attribute» должно содержать список.',
            'max' => [
                'string' => 'Поле «:attribute» не должно превышать :max символов.',
                'array' => 'Поле «:attribute» не должно содержать больше :max элементов.',
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'context.focus_keyword' => 'Фокусная фраза',
            'context.h1' => 'H1',
            'context.meta_title' => 'Meta title',
            'context.meta_description' => 'Meta description',
            'context.short_seo_text' => 'Краткий SEO-текст',
            'context.seo_text' => 'Большой SEO-текст',
            'context.min_order' => 'Минимальный заказ',
            'context.delivery_note' => 'Условия доставки',
            'context.payment_note' => 'Условия оплаты',
            'context.semantic_core' => 'Семантическое ядро',
            'context.keywords' => 'Ключевые слова',
            'context.search_queries' => 'Поисковые запросы',
            'context.semantic_core.*' => 'Фраза семантического ядра',
            'context.keywords.*' => 'Ключевая фраза',
            'context.search_queries.*' => 'Поисковый запрос',
        ];
    }
}
