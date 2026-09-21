<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGoodInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('customer_email'))) {
            $this->merge(['customer_email' => mb_strtolower(trim($this->input('customer_email')))]);
        }

        $this->merge(['preferred_contact' => $this->input('preferred_contact', 'email')]);
    }

    public function rules(): array
    {
        return [
            'request_token' => ['required', 'uuid'],
            'kind' => ['required', Rule::in(['email', 'bargain', 'order'])],
            'quantity' => ['required', 'integer', 'min:1', 'max:9999'],
            'customer_name' => ['required', 'string', 'max:160', 'not_regex:/[\r\n]/'],
            'customer_email' => ['required', 'email:rfc', 'max:254'],
            'customer_phone' => ['nullable', 'string', 'max:64', 'regex:/^[+\d\s().-]{7,64}$/'],
            'company' => ['nullable', 'string', 'max:255'],
            'delivery_city' => ['nullable', 'string', 'max:160'],
            'delivery_address' => ['nullable', 'string', 'max:1000'],
            'comment' => ['nullable', 'string', 'max:3000'],
            'proposed_price' => [Rule::excludeIf(fn () => $this->input('kind') !== 'bargain'), 'required', 'numeric', 'min:0.01', 'max:99999999.99', 'decimal:0,2'],
            'bargain_scenario' => [Rule::excludeIf(fn () => $this->input('kind') !== 'bargain'), 'nullable', Rule::in(['volume', 'repeat', 'ready', 'custom'])],
            'preferred_contact' => ['required', Rule::in(['email', 'max'])],
            'max_contact' => ['required_if:preferred_contact,max', 'nullable', 'string', 'max:255'],
            'consent' => ['required', 'accepted'],
            'website' => ['nullable', 'string', 'max:0'],
        ];
    }

    public function messages(): array
    {
        return [
            'customer_name.required' => 'Укажите, как к вам обращаться.',
            'customer_email.required' => 'Укажите email, чтобы мы могли ответить на заявку.',
            'customer_email.email' => 'Проверьте адрес электронной почты.',
            'quantity.integer' => 'Укажите целое количество упаковок.',
            'quantity.min' => 'Минимальное количество — одна упаковка.',
            'quantity.max' => 'Для объёма свыше 9 999 упаковок укажите детали в комментарии.',
            'proposed_price.required' => 'Предложите цену, за которую готовы купить товар.',
            'proposed_price.min' => 'Предложенная цена должна быть больше нуля.',
            'proposed_price.decimal' => 'Укажите цену с точностью до копеек.',
            'customer_phone.regex' => 'Проверьте номер телефона.',
            'max_contact.required_if' => 'Укажите номер телефона или ссылку на ваш профиль в MAX.',
            'consent.accepted' => 'Подтвердите согласие на обработку персональных данных.',
            'website.max' => 'Не удалось проверить форму. Обновите страницу и попробуйте ещё раз.',
        ];
    }
}
