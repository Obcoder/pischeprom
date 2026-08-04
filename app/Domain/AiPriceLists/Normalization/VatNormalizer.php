<?php

namespace App\Domain\AiPriceLists\Normalization;

use App\Domain\AiPriceLists\Enums\VatMode;

class VatNormalizer
{
    public function __construct(private readonly LocalizedDecimalParser $decimals) {}

    public function normalize(?string $value): array
    {
        $value = mb_strtolower(trim((string) $value));
        $mode = VatMode::Unknown;

        if (preg_match('/без\s*ндс|ндс\s*не\s*облага/iu', $value)) {
            $mode = VatMode::Excluded;
        } elseif (preg_match('/с\s*ндс|включая\s*ндс|ндс\s*включ/iu', $value)) {
            $mode = VatMode::Included;
        }

        $rate = preg_match('/(?:ндс\s*)?(\d{1,2}(?:[,.]\d+)?)\s*%/iu', $value, $match)
            ? $this->decimals->parse($match[1], 4)
            : null;

        return ['mode' => $mode, 'rate' => $rate];
    }
}
