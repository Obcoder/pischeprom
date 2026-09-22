<?php

namespace App\Services\Mail;

class MailOfferRenderer
{
    /**
     * Render the same resolved, server-owned offer for preview and delivery.
     * Quantities are expressed in price_unit_label, not in packages.
     *
     * @return array{html: string, text: string}
     */
    public function render(string $body, array $items, ?array $logistics = null, string $quotedBody = ''): array
    {
        $items = array_map(fn (array $item): array => $this->item($item), $items);
        $logistics = $logistics === null ? null : $this->logistics($logistics);

        if ($items === [] && $logistics === null) {
            $text = $this->join([$body, $quotedBody]);

            return ['html' => nl2br(e($text)), 'text' => $text];
        }

        return [
            'html' => view('emails.mail-offer', compact('body', 'items', 'logistics', 'quotedBody'))->render(),
            'text' => $this->plainText($body, $items, $logistics, $quotedBody),
        ];
    }

    private function item(array $item): array
    {
        $price = $this->number($item['price'] ?? null);
        $quantity = $this->number($item['quantity'] ?? null);
        $quantity = $quantity !== null && $quantity > 0 ? $quantity : null;
        $weight = $this->number($item['package_weight'] ?? null);
        $currency = (string) ($item['currency_code'] ?? 'RUB');
        $specifications = [];

        if ($item['include_specifications'] ?? false) {
            foreach ($item['specifications'] ?? [] as $specification) {
                $label = trim((string) ($specification['label'] ?? ''));
                $value = trim((string) ($specification['value'] ?? ''));

                if ($label !== '' && $value !== '') {
                    $specifications[] = compact('label', 'value');
                }
            }
        }

        return [
            'name' => (string) ($item['name'] ?? ''),
            'url' => $this->safeUrl($item['url'] ?? null),
            'image_url' => ($item['include_image'] ?? true) ? $this->safeUrl($item['image_url'] ?? null) : null,
            'description' => ($item['include_description'] ?? false) ? trim((string) ($item['description'] ?? '')) : '',
            'specifications' => $specifications,
            'price_label' => $price === null ? 'Цена по запросу' : $this->money($price, $currency),
            'has_price' => $price !== null,
            'price_unit_label' => (string) ($item['price_unit_label'] ?? ''),
            'includes_vat' => $price !== null && ($item['includes_vat'] ?? false),
            'quantity_label' => $quantity === null ? null : $this->formatNumber($quantity, 3),
            'total_label' => $price !== null && $quantity !== null ? $this->money($price * $quantity, $currency) : null,
            'package_label' => $weight !== null && $weight > 0 ? $this->formatNumber($weight, 3).' кг' : null,
        ];
    }

    private function logistics(array $logistics): array
    {
        $options = [];

        foreach (array_slice($logistics['options'] ?? [], 0, 4) as $option) {
            $price = $this->number($option['price'] ?? null);
            $options[] = [
                'name' => (string) ($option['name'] ?? ''),
                'price_label' => $price === null ? 'Стоимость по запросу' : $this->money($price, (string) ($option['currency_code'] ?? 'RUB')),
                'duration' => trim((string) ($option['duration'] ?? '')),
                'note' => trim((string) ($option['note'] ?? '')),
            ];
        }

        return [
            'origin' => (string) ($logistics['origin'] ?? ''),
            'destination' => (string) ($logistics['destination'] ?? ''),
            'note' => trim((string) ($logistics['note'] ?? '')),
            'options' => $options,
        ];
    }

    private function plainText(string $body, array $items, ?array $logistics, string $quotedBody): string
    {
        $parts = [$body];

        if ($items !== [] || $logistics !== null) {
            $parts[] = 'ПИЩЕПРОМ-СЕРВЕР'.($items !== [] ? "\nКоммерческое предложение" : '');
        }

        foreach ($items as $item) {
            $lines = [$item['name']];
            $lines[] = $item['price_label'].($item['has_price'] && $item['price_unit_label'] !== '' ? ' / '.$item['price_unit_label'] : '').($item['includes_vat'] ? ' · С НДС' : '');

            if ($item['quantity_label'] !== null) {
                $lines[] = 'Количество: '.$item['quantity_label'].' '.$item['price_unit_label'];
            }

            if ($item['total_label'] !== null) {
                $lines[] = 'Сумма: '.$item['total_label'];
            }

            if ($item['package_label'] !== null) {
                $lines[] = 'Упаковка: '.$item['package_label'];
            }

            if ($item['description'] !== '') {
                $lines[] = $item['description'];
            }

            foreach ($item['specifications'] as $specification) {
                $lines[] = $specification['label'].': '.$specification['value'];
            }

            if ($item['url'] !== null) {
                $lines[] = 'Подробнее о товаре: '.$item['url'];
            }

            $parts[] = implode("\n", $lines);
        }

        if ($logistics !== null) {
            $lines = ['Доставка', $logistics['origin'].' → '.$logistics['destination']];

            foreach ($logistics['options'] as $option) {
                $lines[] = $option['name'].' — '.$option['price_label'].($option['duration'] !== '' ? ' · Срок: '.$option['duration'] : '');

                if ($option['note'] !== '') {
                    $lines[] = $option['note'];
                }
            }

            if ($logistics['note'] !== '') {
                $lines[] = $logistics['note'];
            }

            $parts[] = implode("\n", $lines);
        }

        if ($quotedBody !== '') {
            $parts[] = "Предыдущее письмо\n".$quotedBody;
        }

        return $this->join($parts);
    }

    private function safeUrl(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $url = trim($value);

        if (! preg_match('~^https?://~i', $url) || preg_match('/[\x00-\x20\x7f\\\\]/', $url)) {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        return $url;
    }

    private function number(mixed $value): ?float
    {
        return is_numeric($value) && is_finite((float) $value) && (float) $value >= 0 ? (float) $value : null;
    }

    private function money(float $value, string $currency): string
    {
        $symbol = ['RUB' => '₽', 'USD' => '$', 'EUR' => '€'][$currency] ?? $currency;

        return $this->formatNumber($value, 2).' '.$symbol;
    }

    private function formatNumber(float $value, int $decimals): string
    {
        return rtrim(rtrim(number_format($value, $decimals, ',', ' '), '0'), ',');
    }

    private function join(array $parts): string
    {
        return implode("\n\n", array_filter($parts, static fn (string $part): bool => $part !== ''));
    }
}
