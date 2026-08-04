<?php

namespace App\Domain\AiPriceLists\Normalization;

class LocalizedDecimalParser
{
    public function parse(mixed $value, int $scale = 6): ?string
    {
        if ($value === null || is_bool($value)) {
            return null;
        }

        $raw = trim((string) $value);

        if ($raw === '' || preg_match('/^(?:-|—|–|нет|n\/a|по\s+запросу|договорн)/iu', $raw)) {
            return null;
        }

        $negative = str_starts_with($raw, '(') && str_ends_with($raw, ')');
        $raw = str_replace(["\u{00A0}", "\u{202F}", ' ', "'", '’'], '', $raw);
        $raw = preg_replace('/[^0-9,\.\-]/u', '', $raw) ?: '';

        if ($raw === '' || ! preg_match('/\d/u', $raw)) {
            return null;
        }

        if (str_starts_with($raw, '-')) {
            $negative = true;
            $raw = ltrim($raw, '-');
        }

        $comma = strrpos($raw, ',');
        $dot = strrpos($raw, '.');
        $decimalSeparator = null;

        if ($comma !== false && $dot !== false) {
            $decimalSeparator = $comma > $dot ? ',' : '.';
        } elseif ($comma !== false || $dot !== false) {
            $candidate = $comma !== false ? ',' : '.';
            $position = strrpos($raw, $candidate);
            $fractionLength = strlen($raw) - $position - 1;
            $occurrences = substr_count($raw, $candidate);

            if ($fractionLength > 0 && $fractionLength <= $scale) {
                $looksLikeSingleThousands = $occurrences === 1
                    && $fractionLength === 3
                    && $position > 0
                    && $position <= 3
                    && ! str_starts_with($raw, '0'.$candidate);

                if (! $looksLikeSingleThousands) {
                    $decimalSeparator = $candidate;
                }
            }
        }

        if ($decimalSeparator !== null) {
            $position = strrpos($raw, $decimalSeparator);
            $integer = substr($raw, 0, $position);
            $fraction = substr($raw, $position + 1);
        } else {
            $integer = $raw;
            $fraction = '';
        }

        $integer = preg_replace('/\D/', '', $integer) ?: '0';
        $fraction = preg_replace('/\D/', '', $fraction) ?: '';
        $integer = ltrim($integer, '0') ?: '0';
        $fraction = substr($fraction, 0, $scale);
        $result = $fraction === '' ? $integer : $integer.'.'.str_pad($fraction, min($scale, strlen($fraction)), '0');

        if ($negative && $result !== '0') {
            $result = '-'.$result;
        }

        return $result;
    }
}
