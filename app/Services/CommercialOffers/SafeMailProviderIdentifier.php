<?php

namespace App\Services\CommercialOffers;

final class SafeMailProviderIdentifier
{
    public function normalize(mixed $value): ?string
    {
        if (! is_scalar($value)) {
            return null;
        }

        $value = (string) $value;
        if ($value === ''
            || trim($value) !== $value
            || preg_match('/\A[A-Za-z0-9._:-]{1,128}\z/', $value) !== 1
            || preg_match('/password|token|api[_-]?key|authorization|cookie|session|private/i', $value) === 1
            || preg_match('/\AeyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\z/', $value) === 1) {
            return null;
        }

        $apiKey = (string) config('services.unisender_go.api_key', '');
        if ($apiKey !== '' && hash_equals($apiKey, $value)) {
            return null;
        }

        return $value;
    }
}
