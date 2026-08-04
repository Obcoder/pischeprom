<?php

namespace App\Domain\AiPriceLists\Normalization;

class CurrencyNormalizer
{
    private const MAP = [
        '₽' => 'RUB', 'руб' => 'RUB', 'руб.' => 'RUB', 'р.' => 'RUB', 'rur' => 'RUB', 'rub' => 'RUB',
        '$' => 'USD', 'usd' => 'USD', 'долл' => 'USD', 'доллар' => 'USD',
        '€' => 'EUR', 'eur' => 'EUR', 'евро' => 'EUR',
        '¥' => 'CNY', 'cny' => 'CNY', 'rmb' => 'CNY', 'юань' => 'CNY',
        'byn' => 'BYN', 'тенге' => 'KZT', 'kzt' => 'KZT',
    ];

    public function normalize(?string $value): ?string
    {
        $value = mb_strtolower(trim((string) $value));

        if ($value === '') {
            return null;
        }

        if (preg_match('/\b(RUB|USD|EUR|CNY|BYN|KZT)\b/i', $value, $match)) {
            return strtoupper($match[1]);
        }

        foreach (self::MAP as $needle => $code) {
            if (str_contains($value, $needle)) {
                return $code;
            }
        }

        return null;
    }
}
