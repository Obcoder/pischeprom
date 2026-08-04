<?php

namespace App\Domain\AiPriceLists\Normalization;

class PackagingNormalizer
{
    public function __construct(private readonly LocalizedDecimalParser $decimals) {}

    public function normalize(?string $value): array
    {
        $raw = trim((string) $value);
        $result = [
            'description' => $raw !== '' ? mb_substr($raw, 0, 255) : null,
            'units_per_package' => null,
            'net_quantity' => null,
            'net_quantity_unit' => null,
            'price_basis_quantity' => null,
            'price_basis_unit' => null,
        ];

        if ($raw === '') {
            return $result;
        }

        $unitPattern = '(кг|kg|г|гр|g|л|l|мл|ml|шт|pcs?)';

        if (preg_match('/(\d+(?:[,.]\d+)?)\s*[xх×]\s*(\d+(?:[,.]\d+)?)\s*'.$unitPattern.'/iu', $raw, $match)) {
            $result['units_per_package'] = $this->decimals->parse($match[1]);
            $result['net_quantity'] = $this->decimals->parse($match[2]);
            $result['net_quantity_unit'] = $this->unit($match[3]);
        } elseif (preg_match('/(\d+)\s*\/\s*(\d+(?:[,.]\d+)?)\s*'.$unitPattern.'/iu', $raw, $match)) {
            $result['units_per_package'] = $this->decimals->parse($match[1]);
            $result['net_quantity'] = $this->decimals->parse($match[2]);
            $result['net_quantity_unit'] = $this->unit($match[3]);
        } elseif (preg_match('/(?:кор(?:об(?:ка)?)?\.?|уп(?:ак(?:овка)?)?\.?)\s*(\d+(?:[,.]\d+)?)\s*(шт|pcs?)/iu', $raw, $match)) {
            $result['units_per_package'] = $this->decimals->parse($match[1]);
            $result['net_quantity_unit'] = 'pcs';
        } elseif (preg_match('/(\d+(?:[,.]\d+)?)\s*'.$unitPattern.'/iu', $raw, $match)) {
            $result['net_quantity'] = $this->decimals->parse($match[1]);
            $result['net_quantity_unit'] = $this->unit($match[2]);
        }

        return $result;
    }

    public function unit(?string $value): ?string
    {
        return match (mb_strtolower(trim((string) $value, ' .'))) {
            'кг', 'kg' => 'kg',
            'г', 'гр', 'g' => 'g',
            'л', 'l' => 'l',
            'мл', 'ml' => 'ml',
            'шт', 'pc', 'pcs' => 'pcs',
            'кор', 'короб', 'коробка' => 'box',
            default => null,
        };
    }
}
