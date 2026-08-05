<?php

namespace Database\Seeders;

use App\Models\AvitoMessageTemplate;
use Illuminate\Database\Seeder;

class AvitoMessageTemplateSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->templates() as $template) {
            $exists = AvitoMessageTemplate::query()
                ->withTrashed()
                ->where('system_key', $template['system_key'])
                ->exists();

            if (! $exists) {
                AvitoMessageTemplate::query()->create($template);
            }
        }
    }

    private function templates(): array
    {
        return [
            [
                'system_key' => 'welcome',
                'name' => 'Приветствие',
                'category' => 'greeting',
                'body' => 'Здравствуйте, {{client_name}}! Спасибо за обращение. Чем могу помочь?',
                'is_active' => true,
                'is_favorite' => true,
                'sort_order' => 10,
            ],
            [
                'system_key' => 'qualification',
                'name' => 'Уточнить потребность',
                'category' => 'qualification',
                'body' => '{{client_name}}, уточните, пожалуйста, какой товар, объём и адрес доставки вас интересуют?',
                'is_active' => true,
                'is_favorite' => true,
                'sort_order' => 20,
            ],
            [
                'system_key' => 'product-card',
                'name' => 'Карточка товара',
                'category' => 'product',
                'body' => "{{good_name}}\n{{good_description}}\nЦена: {{good_price}} {{good_currency}}\nНаличие: {{good_stock}}\n{{good_url}}",
                'is_active' => true,
                'is_favorite' => false,
                'sort_order' => 30,
            ],
            [
                'system_key' => 'order-confirmation',
                'name' => 'Подтверждение заказа',
                'category' => 'order',
                'body' => "Заказ {{order_number}} оформлен.\n{{order_items}}\nИтого: {{order_total}} {{order_currency}}.",
                'is_active' => true,
                'is_favorite' => true,
                'sort_order' => 40,
            ],
            [
                'system_key' => 'delivery-confirmation',
                'name' => 'Подтверждение доставки',
                'category' => 'delivery',
                'body' => 'Проверьте, пожалуйста, адрес доставки: {{delivery_address}}. Желаемое время: {{preferred_delivery_time}}.',
                'is_active' => true,
                'is_favorite' => false,
                'sort_order' => 50,
            ],
            [
                'system_key' => 'follow-up',
                'name' => 'Повторный контакт',
                'category' => 'follow_up',
                'body' => 'Здравствуйте, {{client_name}}! Подскажите, пожалуйста, удалось ли принять решение по вашему запросу?',
                'is_active' => true,
                'is_favorite' => false,
                'sort_order' => 60,
            ],
        ];
    }
}
