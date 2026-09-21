<?php

namespace Tests\Unit\Avito;

use App\Services\Avito\AutoReply\AvitoAutoReplyContextGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AvitoAutoReplyContextGuardTest extends TestCase
{
    public function test_delivery_confirmation_from_the_old_archive_prevents_repeating_the_city_question(): void
    {
        $conversation = [
            ['direction' => 'in', 'text' => 'Меня зовут Анна. Телефон +7 999 111-22-33. Адрес: улица Примерная, 12/1.'],
            ['direction' => 'out', 'text' => "Заказ № PP-123 подтверждён.\nБрокколи 10 кг.\nДоставка: Санкт-Петербург, Примерная 12 к1."],
        ];
        for ($index = 0; $index < 20; $index++) {
            $conversation[] = ['direction' => $index % 2 === 0 ? 'in' : 'out', 'text' => 'Спасибо.'];
        }

        $this->assertSame('customer_details_already_known', (new AvitoAutoReplyContextGuard)->responseBlockedReason(
            'Мы организуем доставку. Напишите город или населённый пункт, чтобы можно было обсудить условия.',
            $conversation,
            'Здравствуйте а про доставку когда скажите?',
        ));
    }

    #[DataProvider('archivedDeliveryAddresses')]
    public function test_an_existing_delivery_address_prevents_restarting_the_city_questionnaire(string $address): void
    {
        $conversation = [['direction' => 'in', 'text' => $address]];
        for ($index = 0; $index < 12; $index++) {
            $conversation[] = ['direction' => 'out', 'text' => 'Спасибо.'];
        }

        $this->assertSame('customer_details_already_known', (new AvitoAutoReplyContextGuard)->responseBlockedReason(
            'Мы организуем доставку. Напишите город или населённый пункт.',
            $conversation,
            'Есть доставка?',
        ));
    }

    public static function archivedDeliveryAddresses(): array
    {
        return [
            'complete address' => ['Адрес: Санкт-Петербург, Примерная 12/1.'],
            'street without explicit city' => ['Адрес: улица Примерная, 12/1.'],
        ];
    }

    #[DataProvider('knownDetails')]
    public function test_repeated_requests_are_blocked_for_the_corresponding_known_detail(array $conversation, string $response): void
    {
        $this->assertSame('customer_details_already_known', (new AvitoAutoReplyContextGuard)->responseBlockedReason($response, $conversation));
    }

    public static function knownDetails(): array
    {
        return [
            'phone' => [[['direction' => 'in', 'text' => '+7 (999) 123-45-67']], 'Оставьте номер телефона.'],
            'name' => [[['direction' => 'in', 'text' => 'Меня зовут Анна']], 'Как к вам обращаться?'],
            'address' => [[['direction' => 'in', 'text' => 'Адрес: улица Пушкина, 12']], 'Укажите адрес доставки.'],
            'city' => [[['direction' => 'in', 'text' => 'Я из Казани']], 'В какой город доставка?'],
            'short city answer' => [[
                ['direction' => 'out', 'text' => 'Напишите город доставки.'],
                ['direction' => 'in', 'text' => 'Нижний Новгород'],
            ], 'Ваш город?'],
            'short name answer' => [[
                ['direction' => 'out', 'text' => 'Как вас зовут?'],
                ['direction' => 'in', 'text' => 'Ирина Петрова'],
            ], 'Назовите ваше имя.'],
            'quantity' => [[['direction' => 'in', 'text' => 'Мне нужно 10 кг муки']], 'Какое количество планируете заказать?'],
            'product' => [[['direction' => 'in', 'text' => 'Хочу заказать брокколи']], 'Какой товар вас интересует?'],
            'order number' => [[['direction' => 'in', 'text' => 'Заказ № PP-123']], 'Сообщите номер заказа.'],
            'corrected phone' => [[
                ['direction' => 'in', 'text' => 'Телефон +7 999 111-22-33'],
                ['direction' => 'in', 'text' => 'Исправляю: +7 999 222-33-44'],
            ], 'Напишите телефон.'],
            'corrected address' => [[
                ['direction' => 'in', 'text' => 'Адрес: улица Пушкина, 12'],
                ['direction' => 'in', 'text' => 'Поправка, дом 14'],
            ], 'Уточните адрес доставки.'],
            'order items in confirmation' => [[['direction' => 'out', 'text' => "Заказ № PP-12\nБрокколи 10 кг"]], 'Уточните нужный товар и объём заказа.'],
            'invisible separator' => [[['direction' => 'in', 'text' => 'Я из Казани']], "Напишите го\u{200B}род, пожалуйста."],
        ];
    }

    #[DataProvider('unknownDetails')]
    public function test_questions_and_company_policy_do_not_establish_customer_details(array $conversation, string $response): void
    {
        $this->assertNull((new AvitoAutoReplyContextGuard)->responseBlockedReason($response, $conversation));
    }

    public static function unknownDetails(): array
    {
        return [
            'company policy' => [[['direction' => 'out', 'text' => 'Доставляем во все города.']], 'Напишите город.'],
            'company delivery label' => [[['direction' => 'out', 'text' => 'Доставка: любой город.']], 'Напишите город.'],
            'company address' => [[['direction' => 'out', 'text' => 'Наш адрес: улица Ленина, 5.']], 'Укажите адрес доставки.'],
            'company phone' => [[['direction' => 'out', 'text' => 'Телефон магазина +7 999 111-22-33']], 'Оставьте номер телефона.'],
            'customer question' => [[['direction' => 'in', 'text' => 'У вас есть адрес?']], 'Укажите адрес доставки.'],
            'customer quantity question' => [[['direction' => 'in', 'text' => 'Какие варианты фасовки?']], 'Какое количество вам нужно?'],
            'only outgoing question' => [[['direction' => 'out', 'text' => 'Какой город?']], 'Напишите город.'],
            'no answer yet' => [[
                ['direction' => 'out', 'text' => 'Какой город?'],
                ['direction' => 'in', 'text' => 'Пока не знаю'],
            ], 'Напишите город.'],
            'unrelated later short response' => [[
                ['direction' => 'out', 'text' => 'Какой город?'],
                ['direction' => 'in', 'text' => 'Пока не знаю'],
                ['direction' => 'in', 'text' => 'Спасибо'],
            ], 'Напишите город.'],
            'multiple requested fields with ambiguous answer' => [[
                ['direction' => 'out', 'text' => 'Напишите ваше имя и город.'],
                ['direction' => 'in', 'text' => 'Владимир'],
            ], 'Напишите город.'],
            'unrelated known detail' => [[['direction' => 'in', 'text' => 'Меня зовут Анна']], 'Напишите город.'],
            'public service need' => [[['direction' => 'in', 'text' => 'Мне нужна доставка']], 'Какой товар вас интересует?'],
            'malformed rows' => [[['direction' => 'system', 'text' => 'Город: Казань'], ['direction' => 'in', 'text' => null]], 'Напишите город.'],
        ];
    }

    public function test_reminder_in_current_message_is_evidence_that_the_detail_was_already_provided(): void
    {
        $this->assertSame('customer_details_already_known', (new AvitoAutoReplyContextGuard)->responseBlockedReason(
            'Укажите адрес доставки.', [], 'Так адрес указан у вас в заказе',
        ));
    }

    public function test_a_returning_customer_can_be_asked_to_confirm_a_previous_value(): void
    {
        $guard = new AvitoAutoReplyContextGuard;
        $history = [['direction' => 'in', 'text' => 'Адрес: улица Пушкина, 12']];

        $this->assertNull($guard->responseBlockedReason('Адрес доставки прежний?', $history));
        $this->assertNull($guard->responseBlockedReason('Уточните, адрес доставки тот же?', $history));
        $this->assertNull($guard->responseBlockedReason('Доставить по ранее указанному адресу?', $history));
    }

    public function test_explicit_change_or_new_order_allows_collecting_the_changed_detail_only(): void
    {
        $guard = new AvitoAutoReplyContextGuard;
        $history = [
            ['direction' => 'in', 'text' => 'Мне нужно 10 кг муки. Мой телефон +7 999 111-22-33. Адрес: улица Пушкина, 12.'],
        ];

        $this->assertNull($guard->responseBlockedReason('Укажите адрес доставки.', $history, 'У меня другой адрес.'));
        $this->assertNull($guard->responseBlockedReason('Какое количество нужно?', $history, 'Хочу сделать новый заказ.'));
        $this->assertSame('customer_details_already_known', $guard->responseBlockedReason('Напишите телефон.', $history, 'У меня другой адрес.'));
        $this->assertSame('customer_details_already_known', $guard->responseBlockedReason('Укажите адрес доставки.', $history, 'Новый адрес: улица Ленина, 15.'));
        $this->assertSame('customer_details_already_known', $guard->responseBlockedReason('Какое количество нужно?', $history, 'Новый заказ: 20 кг муки.'));
    }

    public function test_a_changed_but_not_yet_provided_value_invalidates_the_old_value_in_the_archive(): void
    {
        $history = [
            ['direction' => 'in', 'text' => 'Адрес: улица Пушкина, 12.'],
            ['direction' => 'in', 'text' => 'У меня другой адрес.'],
            ['direction' => 'out', 'text' => 'Здравствуйте!'],
        ];

        $this->assertNull((new AvitoAutoReplyContextGuard)->responseBlockedReason('Укажите адрес доставки.', $history, 'Как заказать?'));
    }

    public function test_a_reply_can_acknowledge_known_information_without_asking_for_it_again(): void
    {
        $history = [['direction' => 'in', 'text' => 'Я из Казани. Телефон +7 999 111-22-33.']];

        $this->assertNull((new AvitoAutoReplyContextGuard)->responseBlockedReason('Спасибо, ваши контактные данные есть в переписке.', $history));
        $this->assertNull((new AvitoAutoReplyContextGuard)->responseBlockedReason('Доставка в другие города возможна.', $history));
    }

    public function test_quoted_facts_are_not_attributed_to_the_customer(): void
    {
        $history = [[
            'direction' => 'in',
            'text' => "Это неверно.\n[Цитата в сообщении]: Адрес: Санкт-Петербург, Примерная 12/1.\nМой телефон +7 999 111-22-33.",
        ]];
        $guard = new AvitoAutoReplyContextGuard;

        $this->assertNull($guard->responseBlockedReason('Напишите город.', $history));
        $this->assertNull($guard->responseBlockedReason('Напишите телефон.', $history));
        $this->assertNull($guard->responseBlockedReason('Напишите город.', [], $history[0]['text']));
    }

    public function test_quoted_questions_do_not_turn_an_unrelated_reply_into_customer_details(): void
    {
        $history = [
            ['direction' => 'out', 'text' => "Спасибо.\n[Цитата в сообщении]: Как вас зовут?"],
            ['direction' => 'in', 'text' => 'Александр'],
        ];

        $this->assertNull((new AvitoAutoReplyContextGuard)->responseBlockedReason('Как к вам обращаться?', $history));
    }

    public function test_quoted_correction_cannot_erase_a_real_known_address(): void
    {
        $history = [
            ['direction' => 'in', 'text' => 'Адрес: Санкт-Петербург, Примерная 12/1.'],
            ['direction' => 'in', 'text' => "Нет, это не мое сообщение.\n[Цитата в сообщении]: У меня другой адрес."],
        ];

        $this->assertSame('customer_details_already_known', (new AvitoAutoReplyContextGuard)->responseBlockedReason('Напишите город.', $history));
    }

    public function test_a_true_statement_before_a_quote_still_establishes_the_customer_detail(): void
    {
        $history = [[
            'direction' => 'in',
            'text' => "Адрес: Санкт-Петербург, Примерная 12/1.\n[Цитата в сообщении]: Укажите адрес доставки.",
        ]];

        $this->assertSame('customer_details_already_known', (new AvitoAutoReplyContextGuard)->responseBlockedReason('Напишите город.', $history));
    }
}
