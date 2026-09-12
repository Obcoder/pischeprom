<?php

use App\Models\AvitoAutoReplyRule;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('avito_auto_reply_settings', function (Blueprint $table): void {
            $table->string('response_mode', 24)->default('assistant');
        });
        Schema::table('avito_auto_reply_decisions', function (Blueprint $table): void {
            $table->longText('response_text')->nullable();
            $table->json('matched_rule_keys')->nullable();
        });

        // Preserve the operator's sending mode and custom thresholds. Only the
        // original overly restrictive defaults are relaxed.
        DB::table('avito_auto_reply_settings')->where('minimum_confidence', 0.97)->update(['minimum_confidence' => 0.90]);
        DB::table('avito_auto_reply_settings')->where('cooldown_minutes', 1440)->update(['cooldown_minutes' => 60]);

        $pickup = AvitoAutoReplyRule::withTrashed()->where('key', 'pickup_or_viewing')->first();
        if ($pickup && ! $pickup->trashed() && $pickup->version === 1
            && $pickup->response_text === 'Мы сами бесплатно доставляем. По указанному в объявлении адресу находится склад, на котором нет сотрудника постоянно.') {
            $pickup->update([
                'description' => 'Просмотр товара, самовывоз, согласование визита. Допускаются разговорные формулировки и сочетание с другими безопасными вопросами. Не подтверждать наличие или время доставки.',
                'response_text' => 'Мы сами бесплатно доставляем. Если хотите приехать за товаром или посмотреть его, сначала согласуйте визит в этом чате.',
                'confidence_threshold' => 0.90,
                'cooldown_minutes' => 60,
                'version' => 2,
            ]);
        }

        $defaults = [
            ['greeting', 'Приветствие и начало разговора',
                'Приветствие, проверка связи и предложение помочь. Не подтверждать факт работы в определённое время.',
                'Здравствуйте! Напишите, какой товар вас интересует и что вы хотели бы уточнить.',
                ['Здравствуйте!', 'Добрый день, можно задать вопрос?', 'Вы тут?', 'Привет, хочу узнать про товар']],
            ['order_request', 'Как оформить заявку',
                'Начало заказа, запрос количества и пожеланий покупателя. Не подтверждать наличие, резерв, принятие заказа, оплату или отгрузку.',
                'Напишите в этом чате, какой товар и какое количество вам нужно. Здесь можно уточнить детали заявки.',
                ['Как заказать?', 'Нужно 10 мешков, как оформить заявку?', 'Хочу купить, что вам написать?', 'Куда отправить заявку?']],
            ['product_clarification', 'Уточнение товара и характеристик',
                'Уточнять название, фасовку, назначение или интересующую характеристику. Без подтверждённых данных не описывать состав, свойства, качество, производителя или ассортимент.',
                'Уточните название товара и какие характеристики или фасовка вас интересуют.',
                ['Какая фасовка?', 'Подскажите характеристики', 'Мне нужна другая упаковка', 'Интересует состав товара']],
            ['delivery_method', 'Общий вопрос о доставке',
                'Объяснить, что доставка организуется, и уточнить город. Не обещать географию, стоимость или сроки доставки, не подтверждать отправку.',
                'Мы организуем доставку. Напишите город или населённый пункт, чтобы можно было обсудить условия.',
                ['Доставкой занимаетесь?', 'Как получить товар?', 'Отправляете в другие города?', 'Можно обсудить доставку?']],
            ['payment_documents', 'Вопросы об оплате и документах',
                'Уточнять, какой способ оплаты или документ требуется. Без утверждённых фактов не обещать безнал, НДС, рассрочку, сертификаты или оформление документов.',
                'Напишите, какой способ оплаты или какие документы вам нужны. Конкретные условия необходимо согласовать в чате.',
                ['Какие документы даёте?', 'Можно оплатить по счёту?', 'Работаете с юридическими лицами?', 'Нужен сертификат']],
            ['public_price_question', 'Розничная цена и прайс',
                'При вопросе о продажной цене, прайсе или скидке уточнять товар и объём. Не называть неизвестную цену или обещать скидку. Закупочные цены, себестоимость и маржа запрещены.',
                'Уточните, какой товар и какой объём вас интересуют, чтобы можно было согласовать продажную цену.',
                ['Сколько стоит?', 'Пришлите прайс', 'Какая цена на 10 кг?', 'Есть скидка за объём?']],
            ['thanks', 'Благодарность и завершение разговора',
                'Коротко отвечать на благодарность или прощание. Не подтверждать заказ, резерв или отправку.',
                'Спасибо за обращение! Если появятся вопросы, напишите в этот чат.',
                ['Спасибо!', 'Благодарю за ответ', 'Понятно, спасибо', 'До свидания']],
        ];

        foreach ($defaults as $index => [$key, $name, $description, $response, $examples]) {
            // Do not resurrect deleted scenarios or overwrite operator content.
            if (AvitoAutoReplyRule::withTrashed()->where('key', $key)->exists()) {
                continue;
            }
            $rule = AvitoAutoReplyRule::create([
                'key' => $key, 'name' => $name, 'description' => $description,
                'response_text' => $response, 'is_active' => true, 'is_approved' => true,
                'is_pilot' => true, 'confidence_threshold' => 0.90,
                'cooldown_minutes' => 60, 'daily_limit' => 20,
                'version' => 1, 'sort_order' => ($index + 2) * 10, 'approved_at' => now(),
            ]);
            foreach ($examples as $position => $text) {
                $rule->examples()->create(['kind' => 'positive', 'text' => $text, 'sort_order' => ($position + 1) * 10]);
            }
        }
    }

    public function down(): void
    {
        // Keep editable scenarios and decisions as business records on rollback.
        Schema::table('avito_auto_reply_decisions', fn (Blueprint $table) => $table->dropColumn(['response_text', 'matched_rule_keys']));
        Schema::table('avito_auto_reply_settings', fn (Blueprint $table) => $table->dropColumn('response_mode'));
    }
};
