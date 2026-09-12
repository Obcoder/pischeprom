<?php

namespace Tests\Unit\Avito;

use App\Services\Avito\AutoReply\AvitoAutoReplyHandoffGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class AvitoAutoReplyHandoffGuardTest extends TestCase
{
    #[DataProvider('detailRequests')]
    public function test_requests_for_order_details_require_human_follow_up(string $reply): void
    {
        $this->assertTrue((new AvitoAutoReplyHandoffGuard)->requestsCustomerDetails($reply));
    }

    public static function detailRequests(): array
    {
        return array_map(fn (string $text) => [$text], [
            'Мы организуем доставку. Напишите город или населённый пункт, чтобы можно было обсудить условия.',
            'В какой город доставка?', 'Куда нужна доставка?', 'В какой населённый пункт отправить?',
            'Здравствуйте! Подскажите, пожалуйста, в какой город нужно отправить заказ.',
            'Укажите адрес доставки.', 'Для расчёта потребуется ваш адрес.',
            'Какой товар интересует?', 'Какая фасовка вам нужна?', 'Какое количество планируете заказать?',
            'Уточните нужный товар и объём заказа.', 'Товар и объём вам нужны какие?',
            'Какой способ оплаты вам удобен?', 'Уточните, пожалуйста, какой способ оплаты вы предпочитаете.',
            'Оставьте номер телефона.', 'Пришлите контактные данные.', 'Как к вам обращаться?',
            'Где вы находитесь?', 'Сколько упаковок нужно?',
            'Ваш город?', 'Как вам удобнее оплатить?', 'Каким способом вы хотите оплатить?',
            'Необходимо уточнить город доставки.', 'Нужно указать населённый пункт.',
            'Для обсуждения доставки нужен ваш город.',
            "Напишите го\u{200B}род, пожалуйста.",
        ]);
    }

    #[DataProvider('ordinaryReplies')]
    public function test_public_facts_and_general_greetings_do_not_start_a_handoff(string $reply): void
    {
        $this->assertFalse((new AvitoAutoReplyHandoffGuard)->requestsCustomerDetails($reply));
    }

    public static function ordinaryReplies(): array
    {
        return array_map(fn (string $text) => [$text], [
            '', 'Здравствуйте! Чем помочь?', 'Здравствуйте! Рады вашему обращению.',
            'Мы организуем доставку.', 'Наш адрес указан в объявлении.',
            'Телефон указан в профиле.', 'Способы оплаты указаны в описании.',
            'Спасибо за обращение!', 'Напишите, если появятся вопросы.',
            'Наш адрес: улица Ленина.', 'Доставка в другие города.',
        ]);
    }

    #[DataProvider('customerDetails')]
    public function test_customer_details_are_not_answered_even_when_a_public_question_is_present(string $text): void
    {
        $this->assertSame('customer_details_provided', (new AvitoAutoReplyHandoffGuard)->customerDetailsReason($text));
    }

    public static function customerDetails(): array
    {
        return array_map(fn (string $text) => [$text], [
            'Ижевск', 'ижевск', 'ИЖЕВСК', 'Ижевск?', 'пгт Яр', 'пгтЯр', 'Ростов-на-Дону',
            'Здравствуйте! Ижевск', 'Добрый день, Нижний Новгород', "И\u{200B}жевск",
            '10 мешков', 'Мне нужно 10 кг, как оформить заказ?', 'Можно заказать 10кг?',
            'Пять мешков', '2,5 тонны', 'По 25 килограммов', 'Мука, фасовка по 10 кг',
            'наличными', 'Банковской картой', 'Александр', 'Меня зовут Андрей', 'Да',
            'ул. Ленина, 12', 'Адрес: улица Пушкина, 3', 'Живу в Ижевске',
            'Я из Ижевска, доставка есть?', 'Доставка в Ижевск есть?',
            'Живу в Ижевске, как заказать?', 'Мне в Ижевск, доставка есть?',
            'Город Ижевск, как заказать?', 'Мой телефон +7 (999) 123-45-67',
            '+7 999 123 45 67', 'buyer@example.ru', 'Напишите @buyer_name',
            'Заказ № 12345, как оплатить?', 'Артикул: A123, какая цена?',
            "Авито доставка есть у вас\nИжевск", 'Как заказать? Ижевск', 'Ижевск. Есть доставка?',
            "Здравствуйте!\nЕсть доставка?\nпгт Яр", 'Есть доставка?Ижевск',
            'Хочу заказать. Наличными.',
        ]);
    }

    #[DataProvider('publicRequests')]
    public function test_general_public_questions_can_still_reach_the_assistant(string $text): void
    {
        $this->assertNull((new AvitoAutoReplyHandoffGuard)->customerDetailsReason($text));
    }

    public static function publicRequests(): array
    {
        return array_map(fn (string $text) => [$text], [
            '', 'Здравствуйте', 'Здравствуйте! Чем можете помочь?', 'Добрый день!', 'Спасибо!',
            'Авито доставка есть у вас', 'Есть доставка?', 'Как заказать?', 'Хочу заказать',
            'Как у вас с фасовкой?', 'Я ваш клиент, нужен прайс', 'Я ваш клиент, напишите адрес.',
            'Пришлите прайс, пожалуйста.', 'Есть самовывоз?', 'Можно посмотреть товар?',
            'Какие способы оплаты?', 'Какая фасовка, сколько штук в упаковке?',
            'Цена?', 'Сколько стоит?', 'Почём товар?', 'Есть скидки?', 'Есть сертификаты?',
            'Доставка в другие города возможна?', 'Есть доставка в ваш город?',
            'Можно я сам заберу товар?', 'Можно забрать самому?',
            'Какая фасовка? И как заказать?', "Есть доставка?\nЕсть самовывоз?",
            'Подскажите, пожалуйста. Как заказать?', 'Я ваш клиент. Нужен прайс.',
            'Понятно, спасибо', 'Благодарю за ответ', 'До свидания', 'Здравствуйте, спасибо за ответ!',
        ]);
    }
}
