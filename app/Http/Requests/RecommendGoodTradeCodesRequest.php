<?php

namespace App\Http\Requests;

use App\Services\Auth\StaffAccess;
use App\Services\Goods\GoodTradeCodes;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class RecommendGoodTradeCodesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return app(StaffAccess::class)->allows($this->user());
    }

    protected function prepareForValidation(): void
    {
        $this->merge(GoodTradeCodes::normalize($this->all()));
    }

    public function rules(): array
    {
        if ($this->isMethod('get')) {
            return [];
        }

        return [
            'good_id' => ['nullable', 'integer', 'exists:goods,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:10000'],
            'country_id' => ['nullable', 'integer', 'exists:countries,id'],
            'product_ids' => ['sometimes', 'array', 'max:20'],
            'product_ids.*' => ['integer', 'distinct', 'exists:products,id'],
            'requested_fields' => ['sometimes', 'array', 'list', 'min:1', 'max:11'],
            'requested_fields.*' => ['required', 'string', 'distinct:strict', Rule::in(GoodTradeCodes::FIELDS)],
            'additional_context' => ['sometimes', 'nullable', 'string', 'max:4000'],
            'clarifications' => ['sometimes', 'array', 'list', 'max:40'],
            'clarifications.*' => ['required', 'array:fields,question,answer'],
            'clarifications.*.fields' => ['required', 'array', 'list', 'min:1', 'max:11'],
            'clarifications.*.fields.*' => ['required', 'string', Rule::in(GoodTradeCodes::FIELDS)],
            'clarifications.*.question' => ['required', 'string', 'max:350'],
            'clarifications.*.answer' => ['required', 'string', 'max:2000'],
            ...GoodTradeCodes::rules(),
        ];
    }

    public function after(): array
    {
        if ($this->isMethod('get')) {
            return [];
        }

        return [function (Validator $validator): void {
            $clarifications = $this->input('clarifications', []);
            if (! is_array($clarifications)) {
                return;
            }

            $totalAnswerLength = 0;
            foreach ($clarifications as $index => $clarification) {
                if (! is_array($clarification)) {
                    continue;
                }

                // Laravel's wildcard distinct rule would compare fields across all rows.
                // A classifier may occur in several questions, but only once in a row.
                $fields = $clarification['fields'] ?? [];
                if (is_array($fields) && count(array_filter($fields, 'is_string')) === count($fields)
                    && count($fields) !== count(array_unique($fields))) {
                    $validator->errors()->add("clarifications.{$index}.fields", 'Классификаторы в одном вопросе не должны повторяться.');
                }

                if (is_string($clarification['answer'] ?? null)) {
                    $totalAnswerLength += mb_strlen($clarification['answer']);
                }
                foreach (['question', 'answer'] as $key) {
                    $value = $clarification[$key] ?? null;
                    if (is_string($value)) {
                        $text = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
                        $text = preg_replace('/<(script|style|iframe|object|template)\b[^>]*>.*?<\/\1\s*>/isu', '', $text) ?? '';
                        $text = preg_replace('/[\s\x{00A0}\x00-\x1F\x7F]+/u', ' ', strip_tags($text)) ?? '';
                        if (trim($text) === '') {
                            $validator->errors()->add("clarifications.{$index}.{$key}", 'Введите содержательный текст.');
                        }
                    }
                }
            }
            if ($totalAnswerLength > 16000) {
                $validator->errors()->add('clarifications', 'Суммарный объём ответов не должен превышать 16000 символов.');
            }
        }];
    }
}
