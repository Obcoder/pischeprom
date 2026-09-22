<?php

namespace Tests\Unit;

use App\Services\Mail\MailOfferRenderer;
use Tests\TestCase;

class MailOfferRendererTest extends TestCase
{
    public function test_it_keeps_ordinary_mail_plain_and_escapes_html(): void
    {
        $result = (new MailOfferRenderer)->render("Здравствуйте!\n<em>Спасибо</em>", [], null, 'Исходное письмо');

        $this->assertSame("Здравствуйте!\n<em>Спасибо</em>\n\nИсходное письмо", $result['text']);
        $this->assertSame(nl2br(e($result['text'])), $result['html']);
        $this->assertStringNotContainsString('Коммерческое предложение', $result['html']);
    }

    public function test_it_renders_resolved_product_details_and_quantities_in_the_price_unit(): void
    {
        $result = (new MailOfferRenderer)->render('Добрый день, направляю предложение.', [$this->item()], null, 'Прошу уточнить стоимость.');

        foreach (['Желатин пищевой', '1 250,5 ₽', ' / кг', 'Количество: 12,5 кг', 'Сумма: 15 631,25 ₽', 'Упаковка: 25 кг', 'С НДС', 'Прочность: 200 Bloom', 'Подробнее о товаре: https://example.test/goods/42'] as $expected) {
            $this->assertStringContainsString($expected, $result['text']);
        }

        $this->assertStringContainsString('src="https://example.test/images/42.jpg"', $result['html']);
        $this->assertStringContainsString('href="https://example.test/goods/42"', $result['html']);
        $this->assertStringContainsString('target="_blank" rel="noopener noreferrer"', $result['html']);
        $this->assertLessThan(strpos($result['html'], 'Коммерческое предложение</h1>'), strpos($result['html'], 'Добрый день, направляю предложение.'));
        $this->assertLessThan(strpos($result['html'], 'Прошу уточнить стоимость.'), strpos($result['html'], '15 631,25 ₽'));
        $this->assertStringContainsString("Предыдущее письмо\nПрошу уточнить стоимость.", $result['text']);
    }

    public function test_it_omits_optional_fields_and_does_not_invent_prices_or_vat(): void
    {
        $item = $this->item([
            'price' => null,
            'includes_vat' => true,
            'quantity' => null,
            'include_description' => false,
            'include_specifications' => false,
            'include_image' => false,
        ]);
        $result = (new MailOfferRenderer)->render('', [$item]);

        foreach (['html', 'text'] as $format) {
            $this->assertStringContainsString('Цена по запросу', $result[$format]);
            foreach (['Растворяется', '200 Bloom', '42.jpg', 'С НДС', 'Количество:', 'Сумма:'] as $absent) {
                $this->assertStringNotContainsString($absent, $result[$format]);
            }
        }
        $this->assertStringNotContainsString('<img', $result['html']);
    }

    public function test_it_escapes_all_customer_visible_data_and_rejects_unsafe_urls(): void
    {
        $injection = '<img src=x onerror=alert(1)>';
        $result = (new MailOfferRenderer)->render($injection, [$this->item([
            'name' => $injection,
            'url' => 'javascript:alert(1)',
            'image_url' => 'data:image/svg+xml,<svg onload=alert(1)>',
            'description' => '<script>alert(1)</script>',
            'specifications' => [['label' => '<b>label</b>', 'value' => $injection]],
            'currency_code' => $injection,
            'price_unit_label' => $injection,
        ])], [
            'origin' => $injection,
            'destination' => $injection,
            'note' => $injection,
            'options' => [['name' => $injection, 'duration' => $injection, 'note' => $injection]],
        ], '<iframe src="bad"></iframe>');

        foreach (['<script', '<img', '<iframe', '<b>label</b>', 'javascript:', 'data:image'] as $unsafe) {
            $this->assertStringNotContainsString($unsafe, $result['html']);
        }
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $result['html']);
        $this->assertStringContainsString('&lt;iframe src=&quot;bad&quot;&gt;', $result['html']);
        $this->assertStringNotContainsString('javascript:', $result['text']);
    }

    public function test_it_rejects_relative_obfuscated_and_credential_urls_but_preserves_safe_queries(): void
    {
        foreach (['//example.test/42', '/goods/42', "https://example.test/\n42", 'https://trusted.test\\@evil.test/42', 'https://user:password@example.test/42', 'file:///etc/passwd'] as $url) {
            $result = (new MailOfferRenderer)->render('', [$this->item(['url' => $url, 'image_url' => $url])]);
            $this->assertStringNotContainsString('href=', $result['html'], $url);
            $this->assertStringNotContainsString('<img', $result['html'], $url);
        }

        $result = (new MailOfferRenderer)->render('', [$this->item(['url' => 'https://example.test/goods?x=1&y=2'])]);
        $this->assertStringContainsString('href="https://example.test/goods?x=1&amp;y=2"', $result['html']);
    }

    public function test_it_includes_manual_logistics_options_after_products_and_before_quote(): void
    {
        $result = (new MailOfferRenderer)->render('Здравствуйте.', [$this->item()], [
            'origin' => 'Санкт-Петербург',
            'destination' => 'Курск',
            'note' => 'Разгрузка силами получателя.',
            'options' => [
                ['name' => 'Сборный груз', 'price' => 8200, 'currency_code' => 'RUB', 'duration' => '3–4 дня', 'note' => 'До терминала'],
                ['name' => 'Отдельная машина', 'price' => 410, 'currency_code' => 'EUR', 'duration' => '2 дня'],
                ['name' => 'Самовывоз', 'price' => 0, 'currency_code' => 'RUB', 'duration' => 'По согласованию'],
                ['name' => 'Другой перевозчик', 'price' => null, 'duration' => ''],
            ],
        ], 'Изначальный запрос');

        foreach (['Санкт-Петербург → Курск', 'Сборный груз — 8 200 ₽ · Срок: 3–4 дня', 'До терминала', 'Отдельная машина — 410 € · Срок: 2 дня', 'Самовывоз — 0 ₽', 'Другой перевозчик — Стоимость по запросу', 'Разгрузка силами получателя.'] as $expected) {
            $this->assertStringContainsString($expected, $result['text']);
        }

        $this->assertLessThan(strpos($result['html'], 'Маршрут и варианты доставки'), strpos($result['html'], 'Желатин пищевой'));
        $this->assertLessThan(strpos($result['html'], 'Изначальный запрос'), strpos($result['html'], 'Разгрузка силами получателя.'));
        $this->assertStringNotContainsString('<svg', $result['html']);
        $this->assertStringNotContainsString('<script', $result['html']);
    }

    public function test_it_does_not_make_up_shipping_options_or_unknown_price_details(): void
    {
        $result = (new MailOfferRenderer)->render('', [], ['origin' => 'СПб', 'destination' => 'Курск']);

        $this->assertStringContainsString("Доставка\nСПб → Курск", $result['text']);
        $this->assertStringNotContainsString('Срок:', $result['html']);
        $this->assertStringNotContainsString('ВАРИАНТ', $result['html']);
        $this->assertStringNotContainsString('₽', $result['text']);
        $this->assertStringNotContainsString('Коммерческое предложение', $result['text']);
    }

    private function item(array $overrides = []): array
    {
        return array_replace([
            'id' => 42,
            'name' => 'Желатин пищевой',
            'url' => 'https://example.test/goods/42',
            'image_url' => 'https://example.test/images/42.jpg',
            'description' => 'Растворяется в горячей воде.',
            'specifications' => [['label' => 'Прочность', 'value' => '200 Bloom']],
            'price' => 1250.5,
            'currency_code' => 'RUB',
            'price_unit_label' => 'кг',
            'includes_vat' => true,
            'package_weight' => 25,
            'quantity' => 12.5,
            'include_description' => true,
            'include_specifications' => true,
            'include_image' => true,
        ], $overrides);
    }
}
