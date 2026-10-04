<?php

namespace App\Services\Avito;

use App\Domain\Avito\Exceptions\AvitoException;
use App\Models\Order;
use GdImage;
use Throwable;

class AvitoOrderTableRenderer
{
    private const WIDTH = 1400;

    private const MAX_HEIGHT = 1900;

    private const MARGIN = 48;

    private const TABLE_HEADER_HEIGHT = 64;

    private const LINE_HEIGHT = 32;

    /** @var array<int, int> */
    private const COLUMN_WIDTHS = [64, 610, 180, 225, 225];

    /** @var array<string, int> */
    private array $widthCache = [];

    /**
     * Relations are loaded by the caller. Only customer-facing order fields are
     * rendered; entity details and internal comments never enter the document.
     * The caller owns temporary files returned after successful rendering.
     *
     * @return array<int, string>
     */
    public function render(Order $order): array
    {
        $this->widthCache = [];
        if (! function_exists('imagettftext') || ! is_readable($this->font()) || ! is_readable($this->font(true))) {
            throw new AvitoException('Не удалось подготовить таблицу заказа: недоступны шрифты или обработка изображений.', 'order_table_unavailable', 500, true);
        }

        $paths = [];

        try {
            $header = $this->header($order);
            $footer = $this->footer($order);
            $rows = $this->rows($order);
            $headerHeight = 136 + count($header) * self::LINE_HEIGHT;
            $footerHeight = 154 + count($footer) * self::LINE_HEIGHT;
            $availableHeight = self::MAX_HEIGHT - $headerHeight - self::TABLE_HEADER_HEIGHT - $footerHeight - self::MARGIN;
            $pages = [[]];
            $pageHeight = 0;

            foreach ($rows as $row) {
                if ($row['height'] > $availableHeight) {
                    throw new AvitoException('Строка заказа слишком велика для таблицы. Сократите название товара или данные доставки.', 'order_table_row_too_large', 422);
                }
                if ($pageHeight + $row['height'] > $availableHeight && $pages[array_key_last($pages)] !== []) {
                    $pages[] = [];
                    $pageHeight = 0;
                }
                $pages[array_key_last($pages)][] = $row;
                $pageHeight += $row['height'];
            }

            foreach ($pages as $index => $pageRows) {
                $height = $headerHeight + self::TABLE_HEADER_HEIGHT + array_sum(array_column($pageRows, 'height')) + $footerHeight + self::MARGIN;
                $image = imagecreatetruecolor(self::WIDTH, $height);
                if ($image === false) {
                    throw new AvitoException('Не удалось создать изображение таблицы заказа.', 'order_table_unavailable', 500, true);
                }

                try {
                    imageantialias($image, true);
                    $this->fill($image, 0, 0, self::WIDTH, $height, '#f3f6f5');
                    $this->fill($image, self::MARGIN, self::MARGIN, self::WIDTH - self::MARGIN, $height - 24, '#ffffff');
                    $this->fill($image, self::MARGIN, self::MARGIN, self::WIDTH - self::MARGIN, self::MARGIN + 8, '#16866c');
                    $this->drawText($image, 'ИНФОРМАЦИЯ ПО ЗАКАЗУ', 72, 76, 18, '#16866c', true);
                    $this->drawText($image, ($index + 1).' / '.count($pages), self::WIDTH - 72, 76, 18, '#687b76', false, 'right');

                    $y = 114;
                    foreach ($header as $lineIndex => $line) {
                        $this->drawText($image, $line, 72, $y, $lineIndex === 0 ? 30 : 21, $lineIndex === 0 ? '#183b33' : '#536b64', $lineIndex === 0);
                        $y += self::LINE_HEIGHT;
                    }

                    $y = $headerHeight;
                    $this->tableHeader($image, $y);
                    $y += self::TABLE_HEADER_HEIGHT;
                    foreach ($pageRows as $rowIndex => $row) {
                        $this->drawRow($image, $row, $y, $rowIndex % 2 === 1);
                        $y += $row['height'];
                    }
                    $this->drawFooter($image, $order, $footer, $y + 24);

                    $path = $this->temporaryPath();
                    $paths[] = $path;
                    if (! imagejpeg($image, $path, 90)) {
                        throw new AvitoException('Не удалось записать изображение таблицы заказа.', 'order_table_temp', 500, true);
                    }
                } finally {
                    imagedestroy($image);
                }
            }

            return $paths;
        } catch (Throwable $exception) {
            foreach ($paths as $path) {
                @unlink($path);
            }

            throw $exception;
        }
    }

    /** @return array<int, string> */
    private function header(Order $order): array
    {
        $lines = $this->wrap('Заказ № '.$order->number, self::WIDTH - 144, 30, true);
        $lines = array_merge($lines, $this->wrap('Статус: '.($order->status?->name ?: 'Уточняется'), self::WIDTH - 144, 21));
        $date = $order->submitted_at ?? $order->created_at;
        if ($date) {
            $lines[] = 'Создан: '.$date->format('d.m.Y H:i');
        }

        return $lines;
    }

    /** @return array<int, array<string, mixed>> */
    private function rows(Order $order): array
    {
        $rows = [];
        foreach ($order->items as $index => $item) {
            $currency = $item->currency_code ?: $order->currency_code ?: 'RUB';
            $cells = [
                [(string) ($index + 1)],
                $this->wrap($item->good_name ?: 'Товар', self::COLUMN_WIDTHS[1] - 36, 22),
                $this->wrap($this->number((float) $item->quantity), self::COLUMN_WIDTHS[2] - 32, 22),
                $this->wrap($this->money($item->price_gross, $currency, 4), self::COLUMN_WIDTHS[3] - 32, 22),
                $this->wrap($this->money($item->line_total, $currency), self::COLUMN_WIDTHS[4] - 32, 22),
            ];
            $packing = $item->denominator !== null && $item->denominator > 0
                ? $this->wrap('Фасовка: '.$this->number((float) $item->denominator, 4).' кг', self::COLUMN_WIDTHS[1] - 36, 18)
                : [];
            $lineCount = max(count($cells[1]) + count($packing), ...array_map('count', $cells));
            $rows[] = ['cells' => $cells, 'packing' => $packing, 'height' => max(88, 28 + $lineCount * self::LINE_HEIGHT)];
        }
        if ($rows === []) {
            $rows[] = ['cells' => [[], ['Товары не указаны'], [], [], []], 'packing' => [], 'height' => 88];
        }

        return $rows;
    }

    /** @return array<int, string> */
    private function footer(Order $order): array
    {
        $lines = [];
        if ($order->items->contains(fn ($item) => $item->price_gross === null || $item->line_total === null)) {
            $lines[] = 'Стоимость позиций без цены уточняется.';
        }
        $lines[] = 'Дата доставки: '.($order->delivery_date?->format('d.m.Y') ?: 'Уточняется');
        if (filled($order->preferred_delivery_time)) {
            $lines[] = 'Желаемое время: '.$order->preferred_delivery_time;
        }
        $building = $order->buildings->first();
        $address = $building ? collect([$building->city?->name, $building->address_with_apartment])->filter()->implode(', ') : '';
        $lines[] = 'Адрес доставки: '.($address ?: 'Уточняется');
        if (filled($order->contactTelephone?->number)) {
            $lines[] = 'Контактный телефон: '.$order->contactTelephone->number;
        }

        return array_merge(...array_map(fn ($line) => $this->wrap($line, self::WIDTH - 160, 20), $lines));
    }

    private function tableHeader(GdImage $image, int $y): void
    {
        $this->fill($image, self::MARGIN, $y, self::WIDTH - self::MARGIN, $y + self::TABLE_HEADER_HEIGHT, '#e8f2ee');
        $x = self::MARGIN;
        foreach (['№', 'Товар / фасовка', 'Кол-во', 'Цена', 'Сумма'] as $index => $label) {
            $this->drawText($image, $label, $x + 16, $y + 20, 20, '#285548', true);
            $x += self::COLUMN_WIDTHS[$index];
        }
    }

    /** @param array<string, mixed> $row */
    private function drawRow(GdImage $image, array $row, int $y, bool $alternate): void
    {
        $this->fill($image, self::MARGIN, $y, self::WIDTH - self::MARGIN, $y + $row['height'], $alternate ? '#f6f9f8' : '#ffffff');
        $x = self::MARGIN;
        foreach ($row['cells'] as $index => $lines) {
            foreach ($lines as $lineIndex => $line) {
                $align = $index >= 2 ? 'right' : 'left';
                $textX = $align === 'right' ? $x + self::COLUMN_WIDTHS[$index] - 16 : $x + 16;
                $this->drawText($image, $line, $textX, $y + 14 + $lineIndex * self::LINE_HEIGHT, 22, '#263e37', false, $align);
            }
            if ($index === 1) {
                foreach ($row['packing'] as $lineIndex => $line) {
                    $this->drawText($image, $line, $x + 16, $y + 14 + (count($lines) + $lineIndex) * self::LINE_HEIGHT, 18, '#73877f');
                }
            }
            $x += self::COLUMN_WIDTHS[$index];
            if ($index < 4) {
                imageline($image, $x, $y, $x, $y + $row['height'], $this->color($image, '#e8eeeb'));
            }
        }
        imageline($image, self::MARGIN, $y + $row['height'], self::WIDTH - self::MARGIN, $y + $row['height'], $this->color($image, '#e0e8e4'));
    }

    /** @param array<int, string> $lines */
    private function drawFooter(GdImage $image, Order $order, array $lines, int $y): void
    {
        $this->fill($image, 72, $y, self::WIDTH - 72, $y + 96, '#16866c');
        $totalLabel = $order->total_amount !== null && $order->items->contains(fn ($item) => $item->price_gross === null || $item->line_total === null)
            ? 'ИТОГО ПО УКАЗАННЫМ ЦЕНАМ'
            : 'ИТОГО ПО ЗАКАЗУ';
        $this->drawText($image, $totalLabel, 96, $y + 16, 17, '#ffffff', true);
        $amount = $this->money($order->total_amount, $order->currency_code ?: 'RUB');
        $amountSize = 30;
        while ($amountSize > 18 && $this->textWidth($amount, $amountSize, true) > 700) {
            $amountSize--;
        }
        $this->drawText($image, $amount, 96, $y + 48, $amountSize, '#ffffff', true);
        $weight = $order->total_weight !== null ? $this->number((float) $order->total_weight, 4).' кг' : 'Уточняется';
        $this->drawText($image, 'Общий вес: '.$weight, self::WIDTH - 96, $y + 48, 21, '#ffffff', false, 'right');
        $y += 120;
        foreach ($lines as $line) {
            $this->drawText($image, $line, 80, $y, 20, '#536b64');
            $y += self::LINE_HEIGHT;
        }
    }

    /** @return array<int, string> */
    private function wrap(string $text, int $width, int $size, bool $bold = false): array
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));
        if ($text === '') {
            return [];
        }

        $lines = [];
        $line = '';
        foreach (explode(' ', $text) as $word) {
            $candidate = $line === '' ? $word : $line.' '.$word;
            if ($this->textWidth($candidate, $size, $bold) <= $width) {
                $line = $candidate;

                continue;
            }
            if ($line !== '') {
                $lines[] = $line;
                $line = '';
            }
            while ($this->textWidth($word, $size, $bold) > $width) {
                $minimum = 1;
                $maximum = mb_strlen($word);
                while ($minimum < $maximum) {
                    $middle = (int) ceil(($minimum + $maximum) / 2);
                    if ($this->textWidth(mb_substr($word, 0, $middle), $size, $bold) <= $width) {
                        $minimum = $middle;
                    } else {
                        $maximum = $middle - 1;
                    }
                }
                $lines[] = mb_substr($word, 0, $minimum);
                $word = mb_substr($word, $minimum);
            }
            $line = $word;
        }
        if ($line !== '') {
            $lines[] = $line;
        }

        return $lines;
    }

    private function money(?float $amount, string $currency, int $decimals = 2): string
    {
        if ($amount === null) {
            return 'Уточняется';
        }
        $formatted = number_format($amount, $decimals, ',', ' ');
        if ($decimals > 2) {
            while (str_ends_with($formatted, '0') && strlen(substr(strrchr($formatted, ','), 1)) > 2) {
                $formatted = substr($formatted, 0, -1);
            }
        }

        return $formatted.' '.(strtoupper($currency) === 'RUB' ? '₽' : strtoupper($currency));
    }

    private function number(float $value, int $decimals = 3): string
    {
        return rtrim(rtrim(number_format($value, $decimals, ',', ' '), '0'), ',');
    }

    private function font(bool $bold = false): string
    {
        return resource_path('fonts/'.($bold ? 'Rubik-Medium.ttf' : 'Roboto-Regular.ttf'));
    }

    private function textWidth(string $text, int $size, bool $bold = false): int
    {
        $key = (int) $bold.':'.$size.':'.$text;
        if (isset($this->widthCache[$key])) {
            return $this->widthCache[$key];
        }
        $box = imagettfbbox($size, 0, $this->font($bold), $text);

        return $this->widthCache[$key] = abs($box[2] - $box[0]);
    }

    protected function drawText(GdImage $image, string $text, int $x, int $y, int $size, string $hex, bool $bold = false, string $align = 'left'): void
    {
        if ($align === 'right') {
            $x -= $this->textWidth($text, $size, $bold);
        }
        imagettftext($image, $size, 0, $x, $y + $size, $this->color($image, $hex), $this->font($bold), $text);
    }

    protected function temporaryPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'avito-order-');
        if ($path === false) {
            throw new AvitoException('Не удалось подготовить временный файл таблицы заказа.', 'order_table_temp', 500, true);
        }

        return $path;
    }

    private function fill(GdImage $image, int $left, int $top, int $right, int $bottom, string $hex): void
    {
        imagefilledrectangle($image, $left, $top, $right, $bottom, $this->color($image, $hex));
    }

    private function color(GdImage $image, string $hex): int
    {
        return imagecolorallocate($image, hexdec(substr($hex, 1, 2)), hexdec(substr($hex, 3, 2)), hexdec(substr($hex, 5, 2)));
    }
}
