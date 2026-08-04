<?php

namespace App\Domain\AiPriceLists\Normalization;

class TextNormalizer
{
    public function display(?string $value): ?string
    {
        $value = trim(preg_replace('/[\x{00A0}\x{202F}\s]+/u', ' ', (string) $value) ?: '');

        return $value === '' ? null : mb_substr($value, 0, 255);
    }

    public function search(?string $value): ?string
    {
        $value = $this->display($value);

        if ($value === null) {
            return null;
        }

        if (class_exists(\Normalizer::class)) {
            $value = \Normalizer::normalize($value, \Normalizer::FORM_KC) ?: $value;
        }

        $value = mb_strtolower(str_replace('ё', 'е', $value));
        $value = preg_replace('/[^\p{L}\p{N}]+/u', ' ', $value) ?: '';

        return trim($value) ?: null;
    }
}
