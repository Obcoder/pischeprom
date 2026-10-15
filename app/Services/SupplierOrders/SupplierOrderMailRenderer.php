<?php

namespace App\Services\SupplierOrders;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use DateTimeImmutable;

class SupplierOrderMailRenderer
{
    /**
     * Render the same normalized order snapshot for preview and dispatch.
     * Only explicitly selected supplier-visible fields reach the template.
     *
     * @return array{html: string, text: string}
     */
    public function render(array $normalized): array
    {
        $number = trim((string) ($normalized['number'] ?? ''));
        $title = 'Заказ поставщику'.($number !== '' ? ' № '.$number : '');
        $body = (string) ($normalized['body'] ?? '');
        $deliveryDate = $this->deliveryDate($normalized['delivery_date'] ?? null);
        $items = [];
        $totals = [];
        $hasUnpricedItems = false;

        foreach ($normalized['items'] ?? [] as $source) {
            $quantity = $this->decimal($source['quantity'] ?? null, 6);
            $item = [
                'name' => (string) ($source['name'] ?? ''),
                'quantity' => $quantity === null ? '—' : $this->format($quantity),
                'unit' => (string) ($source['unit_label'] ?? $source['measurement']['unit_label'] ?? ''),
                'note' => (string) ($source['note'] ?? ''),
                'avatar_url' => app(SupplierOrderProductImages::class)->safeUrl($source['avatar_url'] ?? null),
            ];

            $price = $this->decimal($source['unit_price'] ?? null, 4);
            $currency = trim((string) ($source['currency_code'] ?? ''));
            $total = $price !== null && $quantity !== null
                ? $price->multipliedBy($quantity)->toScale(4, RoundingMode::HalfUp)
                : null;
            $item['price'] = $price === null ? 'Уточнить' : $this->money($price, $currency);
            $item['total'] = $total === null ? '—' : $this->money($total, $currency);
            $item['vat_label'] = $source['quotation_snapshot']['vat_label'] ?? null;

            if ($total !== null && $currency !== '') {
                $totals[$currency] = ($totals[$currency] ?? BigDecimal::zero())->plus($total);
            } else {
                $hasUnpricedItems = true;
            }

            $items[] = $item;
        }

        foreach ($totals as $currency => $total) {
            $totals[$currency] = $this->money($total, (string) $currency);
        }

        $totalLabel = $hasUnpricedItems ? 'Итого по позициям с указанной ценой и валютой' : 'Итого';
        $viewData = compact('title', 'body', 'deliveryDate', 'items', 'totals', 'totalLabel');

        return [
            'html' => view('emails.supplier-order', $viewData)->render(),
            'text' => $this->plainText($viewData),
        ];
    }

    private function plainText(array $data): string
    {
        $parts = [$data['title']];

        if ($data['deliveryDate'] !== null) {
            $parts[] = 'Дата поставки: '.$data['deliveryDate'];
        }

        if ($data['body'] !== '') {
            $parts[] = $data['body'];
        }

        foreach ($data['items'] as $index => $item) {
            $lines = [($index + 1).'. '.$item['name']];
            $lines[] = 'Количество: '.$item['quantity'].($item['unit'] !== '' ? ' '.$item['unit'] : '');

            if ($item['note'] !== '') {
                $lines[] = 'Комментарий: '.$item['note'];
            }

            $lines[] = 'Цена за единицу: '.$item['price'].($item['vat_label'] ? ' · '.$item['vat_label'] : '');
            $lines[] = 'Сумма: '.$item['total'];

            $parts[] = implode("\n", $lines);
        }

        if ($data['items'] === []) {
            $parts[] = 'Позиции заказа пока не добавлены.';
        }

        foreach ($data['totals'] as $total) {
            $parts[] = $data['totalLabel'].': '.$total;
        }

        return implode("\n\n", $parts);
    }

    private function decimal(mixed $value, int $scale): ?BigDecimal
    {
        if (! is_numeric($value) || ! is_finite((float) $value) || (float) $value < 0) {
            return null;
        }

        $decimal = is_float($value) ? number_format($value, $scale, '.', '') : trim((string) $value);

        return BigDecimal::of($decimal)->toScale($scale, RoundingMode::HalfUp);
    }

    private function format(BigDecimal $value, int $minimumDecimals = 0): string
    {
        [$whole, $fraction] = array_pad(explode('.', (string) $value, 2), 2, '');
        $fraction = str_pad(rtrim($fraction, '0'), $minimumDecimals, '0');
        $whole = preg_replace('/\B(?=(\d{3})+(?!\d))/', ' ', $whole);

        return $whole.($fraction !== '' ? ','.$fraction : '');
    }

    private function money(BigDecimal $value, string $currency): string
    {
        return $this->format($value, 2).' '.($currency !== '' ? $currency : '(валюта не указана)');
    }

    private function deliveryDate(mixed $value): ?string
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $date->format('d.m.Y') : $value;
    }
}
