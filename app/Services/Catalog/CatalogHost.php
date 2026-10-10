<?php

namespace App\Services\Catalog;

class CatalogHost
{
    /** Store exact hostnames in ASCII so Unicode and punycode match identically. */
    public static function normalize(string $value): ?string
    {
        $value = mb_strtolower(rtrim(trim($value), '.'));
        if ($value === '' || preg_match('~[\s/@:#?\\\\]~u', $value)) {
            return null;
        }
        $ascii = idn_to_ascii($value, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        if ($ascii === false || strlen($ascii) > 253 || ! filter_var($ascii, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME)) {
            return null;
        }

        return strtolower($ascii);
    }
}
