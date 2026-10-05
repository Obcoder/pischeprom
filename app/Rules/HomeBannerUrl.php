<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class HomeBannerUrl implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! self::isSafe($value)) {
            $fail('Укажите ссылку с https://, http:// или путь на сайте, начинающийся с одного /.');
        }
    }

    public static function isSafe(mixed $value): bool
    {
        if (! is_string($value) || preg_match('/[\x00-\x20\x7f\\\\]/', $value)) {
            return false;
        }

        if (str_starts_with($value, '/') && ! str_starts_with($value, '//')) {
            return true;
        }

        return in_array(strtolower((string) parse_url($value, PHP_URL_SCHEME)), ['http', 'https'], true)
            && filter_var($value, FILTER_VALIDATE_URL) !== false;
    }
}
