<?php

namespace App\Support;

final class PhoneNumber
{
    /**
     * Return a Russian phone number in the canonical E.164 form.
     */
    public static function russian(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', (string) $value) ?: '';

        if (strlen($digits) === 10) {
            $digits = '7'.$digits;
        } elseif (strlen($digits) === 11 && str_starts_with($digits, '8')) {
            $digits = '7'.substr($digits, 1);
        }

        if (strlen($digits) !== 11 || ! str_starts_with($digits, '7')) {
            return null;
        }

        return '+'.$digits;
    }

    /**
     * Include formats written by older CRM and PBX code so existing rows remain discoverable.
     *
     * @return array<int, string>
     */
    public static function russianStorageVariants(mixed $value): array
    {
        $canonical = self::russian($value);

        if ($canonical === null) {
            return [];
        }

        $digits = substr($canonical, 1);

        return array_values(array_unique([
            $canonical,
            $digits,
            '8'.substr($digits, 1),
        ]));
    }
}
