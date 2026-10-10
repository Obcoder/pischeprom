<?php

namespace App\Services\Goods;

final class GoodTradeCodes
{
    public const FIELDS = [
        'hs_code', 'tn_ved_code', 'okpd2_code', 'cn_code', 'taric_code',
        'htsus_code', 'schedule_b_code', 'gtin', 'unspsc_code', 'cas_number', 'eccn_code',
    ];

    public const PUBLIC_FIELDS = ['tn_ved_code', 'okpd2_code', 'hs_code'];

    /** All identifiers are strings: leading zeroes are significant. */
    public static function rules(): array
    {
        return [
            'hs_code' => ['nullable', 'string', 'regex:/^\d{6}$/D'],
            'tn_ved_code' => ['nullable', 'string', 'regex:/^\d{10}$/D'],
            'okpd2_code' => ['nullable', 'string', 'regex:/^\d{2}(?:\.\d{1,2}|\.\d{2}\.\d{1,2}|\.\d{2}\.\d{2}\.\d{1,3})?$/D'],
            'cn_code' => ['nullable', 'string', 'regex:/^\d{8}$/D'],
            'taric_code' => ['nullable', 'string', 'regex:/^\d{10}$/D'],
            'htsus_code' => ['nullable', 'string', 'regex:/^(?:\d{8}|\d{10})$/D'],
            'schedule_b_code' => ['nullable', 'string', 'regex:/^\d{10}$/D'],
            'gtin' => ['nullable', 'string', 'regex:/^(?:\d{8}|\d{12}|\d{13}|\d{14})$/D'],
            'unspsc_code' => ['nullable', 'string', 'regex:/^\d{8}$/D'],
            'cas_number' => ['nullable', 'string', 'regex:/^\d{2,7}-\d{2}-\d$/D'],
            'eccn_code' => ['nullable', 'string', 'regex:/^(?:[0-9][A-E][0-9]{3}|EAR99)$/D'],
        ];
    }

    /** Return only supplied fields, so omitted values survive partial updates. */
    public static function normalize(array $input): array
    {
        $normalized = [];
        foreach (self::FIELDS as $field) {
            if (! array_key_exists($field, $input)) {
                continue;
            }

            $value = $input[$field];
            // Leave non-string values for validation; never guess lost leading zeroes.
            if (! is_string($value)) {
                $normalized[$field] = $value;

                continue;
            }

            $value = preg_replace('/\s+/u', '', trim($value));
            if ($field === 'eccn_code') {
                $value = strtoupper($value);
            } elseif ($field === 'okpd2_code') {
                $digits = $value;
                if (ctype_digit($digits) && strlen($digits) >= 2 && strlen($digits) <= 9) {
                    $value = implode('.', array_filter([
                        substr($digits, 0, 2), substr($digits, 2, 2),
                        substr($digits, 4, 2), substr($digits, 6, 3),
                    ], fn (string $part): bool => $part !== ''));
                }
            } elseif ($field !== 'cas_number') {
                $value = str_replace(['.', '-'], '', $value);
            }

            $normalized[$field] = $value === '' ? null : $value;
        }

        return $normalized;
    }
}
