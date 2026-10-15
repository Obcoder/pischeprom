<?php

namespace Tests\Unit;

use App\Services\SupplierOrders\SupplierOrderMailRenderer;
use Tests\TestCase;

class SupplierOrderMailRendererTest extends TestCase
{
    public function test_it_renders_supplier_visible_order_details_and_purchase_prices_by_default(): void
    {
        $result = (new SupplierOrderMailRenderer)->render($this->order());

        foreach (['html', 'text'] as $format) {
            foreach (['Заказ поставщику № ЗП-42', '15.10.2026', 'Просим подтвердить поставку.', 'Брокколи замороженная', '12,500001', 'кг', 'Коробки без повреждений', 'Цена за', 'Сумма', 'Итого', '1 250,50 RUB', '15 631,2513 RUB'] as $expected) {
                $this->assertStringContainsString($expected, $result[$format]);
            }
            foreach (['internal-only-note', 'secret-client-name', '777123', '987654', '432100'] as $private) {
                $this->assertStringNotContainsString($private, $result[$format]);
            }
        }

        $this->assertStringContainsString('<thead>', $result['html']);
        $this->assertStringContainsString('<caption', $result['html']);
        $this->assertStringNotContainsString('role="presentation"', $result['html']);
    }

    public function test_a_legacy_include_prices_flag_cannot_hide_purchase_prices_and_totals(): void
    {
        $renderer = new SupplierOrderMailRenderer;
        $order = $this->order();
        $expected = $renderer->render($order);

        foreach ([false, true] as $includePrices) {
            $this->assertSame($expected, $renderer->render(array_replace($order, ['include_prices' => $includePrices])));
        }
    }

    public function test_it_uses_only_saved_purchase_prices_and_groups_totals_by_currency(): void
    {
        $order = $this->order();
        $order['include_prices'] = true;
        $order['items'] = [
            $this->item(['quantity' => 2, 'unit_price' => 1200.25, 'line_total' => 999999]),
            $this->item(['quantity' => 3, 'unit_price' => 100.10]),
            $this->item(['quantity' => 0.5, 'unit_price' => 6, 'currency_code' => 'EUR']),
        ];
        $result = (new SupplierOrderMailRenderer)->render($order);

        foreach (['html', 'text'] as $format) {
            foreach (['1 200,25 RUB', '2 400,50 RUB', '100,10 RUB', '300,30 RUB', '2 700,80 RUB', '3,00 EUR'] as $expected) {
                $this->assertStringContainsString($expected, $result[$format]);
            }
            foreach (['999999', '999 999', '2 703,80', '987654', '987 654'] as $absent) {
                $this->assertStringNotContainsString($absent, $result[$format]);
            }
        }
        $this->assertSame(2, substr_count($result['text'], 'Итого:'));
    }

    public function test_it_preserves_snapshot_precision_and_handles_zero_as_a_real_price(): void
    {
        $order = $this->order();
        $order['include_prices'] = true;
        $order['items'] = [
            $this->item(['quantity' => '0.000001', 'unit_price' => '999999.9999']),
            $this->item(['quantity' => '1.234567', 'unit_price' => '0.1234']),
            $this->item(['quantity' => 1, 'unit_price' => 0]),
        ];
        $result = (new SupplierOrderMailRenderer)->render($order);

        foreach (['0,000001 кг', '999 999,9999 RUB', '0,1234 RUB', 'Сумма: 0,1523 RUB', 'Цена за единицу: 0,00 RUB', 'Итого: 1,1523 RUB'] as $expected) {
            $this->assertStringContainsString($expected, $result['text']);
        }
    }

    public function test_it_does_not_invent_missing_prices_currencies_or_empty_order_totals(): void
    {
        $order = $this->order();
        $order['include_prices'] = true;
        $order['items'] = [$this->item(['unit_price' => null])];
        $result = (new SupplierOrderMailRenderer)->render($order);

        foreach (['html', 'text'] as $format) {
            $this->assertStringContainsString('Уточнить', $result[$format]);
            $this->assertStringNotContainsString('Итого', $result[$format]);
            $this->assertStringNotContainsString('0,00', $result[$format]);
        }

        $order['items'] = [$this->item(['unit_price' => 15, 'currency_code' => null])];
        $result = (new SupplierOrderMailRenderer)->render($order);
        $this->assertStringContainsString('15,00 (валюта не указана)', $result['text']);
        $this->assertStringNotContainsString('Итого', $result['text']);
        $this->assertStringNotContainsString('RUB', $result['text']);

        $result = (new SupplierOrderMailRenderer)->render(['include_prices' => true, 'items' => []]);
        foreach (['html', 'text'] as $format) {
            $this->assertStringContainsString('Позиции заказа пока не добавлены.', $result[$format]);
            $this->assertStringNotContainsString('Итого', $result[$format]);
            $this->assertStringNotContainsString('0,00', $result[$format]);
        }
    }

    public function test_large_float_quantities_keep_all_six_decimals_and_exact_line_totals(): void
    {
        $order = $this->order();
        $order['items'] = [$this->item(['quantity' => 999999998.123456, 'unit_price' => 999.9999])];
        $result = (new SupplierOrderMailRenderer)->render($order);

        foreach (['html', 'text'] as $format) {
            $this->assertStringContainsString('999 999 998,123456', $result[$format]);
            $this->assertStringContainsString('999,9999 RUB', $result[$format]);
            $this->assertStringContainsString('999 999 898 123,4562 RUB', $result[$format]);
        }
        $this->assertStringContainsString('Итого: 999 999 898 123,4562 RUB', $result['text']);

        $order['items'][0]['quantity'] = '999999998.123456';
        $order['items'][0]['unit_price'] = '999.9999';
        $this->assertSame($result, (new SupplierOrderMailRenderer)->render($order));
    }

    public function test_large_saved_decimal_prices_are_not_rounded_through_float_conversion(): void
    {
        $order = $this->order();
        $order['items'] = [$this->item(['quantity' => '1.000001', 'unit_price' => '999999999998.9999'])];
        $result = (new SupplierOrderMailRenderer)->render($order);

        foreach (['html', 'text'] as $format) {
            $this->assertStringContainsString('1,000001', $result[$format]);
            $this->assertStringContainsString('999 999 999 998,9999 RUB', $result[$format]);
            $this->assertStringContainsString('1 000 000 999 998,9999 RUB', $result[$format]);
        }
        $this->assertStringContainsString('Итого: 1 000 000 999 998,9999 RUB', $result['text']);
    }

    public function test_it_labels_partial_totals_when_any_row_has_no_price(): void
    {
        $order = $this->order();
        $order['include_prices'] = true;
        $order['items'] = [
            $this->item(['quantity' => 2, 'unit_price' => 100]),
            $this->item(['unit_price' => null]),
        ];
        $result = (new SupplierOrderMailRenderer)->render($order);

        $this->assertStringContainsString('Итого по позициям с указанной ценой и валютой: 200,00 RUB', $result['text']);
        $this->assertStringNotContainsString('Итого:', $result['text']);
    }

    public function test_it_escapes_every_visible_text_field_without_truncating_multiline_text(): void
    {
        $injection = '<img src=x onerror=alert(1)>';
        $body = "Здравствуйте!\n".str_repeat('Длинное описание ', 600).$injection."\nПоследняя строка";
        $order = [
            'number' => $injection,
            'delivery_date' => $injection,
            'body' => $body,
            'include_prices' => true,
            'items' => [$this->item([
                'name' => $injection,
                'unit_label' => $injection,
                'note' => "Первая строка\n".$injection,
                'currency_code' => $injection,
            ])],
        ];
        $result = (new SupplierOrderMailRenderer)->render($order);

        $this->assertStringNotContainsString('<img', $result['html']);
        $this->assertStringContainsString(e($injection), $result['html']);
        $this->assertStringContainsString(nl2br(e($body)), $result['html']);
        $this->assertStringContainsString($body, $result['text']);
        $this->assertStringContainsString("Первая строка<br />\n", $result['html']);
    }

    public function test_small_product_avatars_are_decorative_and_do_not_change_plain_text_or_totals(): void
    {
        $order = $this->order();
        $renderer = new SupplierOrderMailRenderer;
        $withoutImages = $renderer->render($order);
        $avatar = 'https://images.example.com/goods/42/thumb.jpg?width=160&height=160';
        $order['items'][0]['avatar_url'] = $avatar;
        $result = $renderer->render($order);

        $this->assertStringContainsString('src="'.e($avatar).'" alt="" width="36" height="36"', $result['html']);
        $this->assertStringContainsString('Брокколи замороженная', $result['html']);
        $this->assertStringContainsString('15 631,2513 RUB', $result['html']);
        $this->assertSame($withoutImages['text'], $result['text']);
        $this->assertStringNotContainsString('<img', $withoutImages['html']);
    }

    public function test_missing_or_unsafe_avatars_leave_readable_text_only_rows(): void
    {
        $renderer = new SupplierOrderMailRenderer;
        $order = $this->order();
        $expected = $renderer->render($order);

        foreach ([null, '', 'data:image/png;base64,AAAA', 'https://127.0.0.1/avatar.jpg', 'https://example.com/video.mp4', 'https://example.com/avatar.jpg" onerror="alert(1)'] as $avatar) {
            $order['items'][0]['avatar_url'] = $avatar;
            $this->assertSame($expected, $renderer->render($order));
        }
    }

    private function order(): array
    {
        return [
            'id' => 777123,
            'number' => 'ЗП-42',
            'delivery_date' => '2026-10-15',
            'body' => 'Просим подтвердить поставку.',
            'internal_note' => 'internal-only-note',
            'customer' => ['name' => 'secret-client-name'],
            'items' => [$this->item()],
        ];
    }

    private function item(array $overrides = []): array
    {
        return array_replace([
            'name' => 'Брокколи замороженная',
            'unit_label' => 'кг',
            'quantity' => 12.500001,
            'unit_price' => 1250.50,
            'currency_code' => 'RUB',
            'note' => 'Коробки без повреждений',
            'source_order_item_ids' => [777123],
            'retail_price' => 987654,
            'stock' => 432100,
        ], $overrides);
    }
}
