<?php

namespace App\Domain\Banking\Reconciliation;

use Illuminate\Support\Collection;

class PaymentPurposeNormalizer
{
    public function normalize(?string $purpose): string
    {
        $value = mb_strtolower((string) $purpose);
        $value = str_replace('ё', 'е', $value);
        $value = str_replace(['№', 'ℕ', 'Nº'], ' № ', $value);
        $value = preg_replace('/[\x{00A0}\s]+/u', ' ', $value) ?? $value;
        $value = preg_replace('/\s*([,;:])\s*/u', '$1 ', $value) ?? $value;

        return trim($value);
    }

    /** @return Collection<int, string> */
    public function extractReferences(?string $purpose): Collection
    {
        $normalized = $this->normalize($purpose);

        if ($normalized === '') {
            return collect();
        }

        $pattern = '/(?<![\p{L}\p{N}])'
            .'(?:оплата\s+по\s+)?'
            .'(?:счет(?:у|а)?|invoice|инвойс|заказ(?:у|а)?|продаж(?:а|е|у|и))'
            .'\s*(?:(?:номер|no|n)\s*)?(?:№|#)?\s*[:\-]?\s*'
            .'([a-zа-я0-9](?:[a-zа-я0-9._\/-]{0,62}[a-zа-я0-9])?)'
            .'(?![\p{L}\p{N}])/ui';
        preg_match_all($pattern, $normalized, $matches);

        return collect($matches[1] ?? [])
            ->map(fn (string $reference): string => $this->normalizeReference($reference))
            ->filter()
            ->unique()
            ->values();
    }

    public function normalizeReference(?string $reference): string
    {
        $value = mb_strtoupper(trim((string) $reference));
        $value = str_replace('Ё', 'Е', $value);
        $value = preg_replace('/\s+/u', '', $value) ?? $value;
        $value = trim($value, " \t\n\r\0\x0B.,;:");

        return $value;
    }
}
