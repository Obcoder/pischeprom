<?php

namespace App\Services\Avito;

use Illuminate\Support\Str;

class AvitoPayloadRedactor
{
    private const SECRET_MARKERS = [
        'authorization',
        'access_token',
        'refresh_token',
        'client_secret',
        'client_id',
        'password',
        'secret',
    ];

    public function redact(mixed $value, int $depth = 0): mixed
    {
        if ($depth > 10) {
            return '[truncated]';
        }

        if (is_array($value)) {
            $result = [];

            foreach ($value as $key => $item) {
                $normalized = Str::lower((string) $key);
                $result[$key] = Str::contains($normalized, self::SECRET_MARKERS)
                    ? '[redacted]'
                    : $this->redact($item, $depth + 1);
            }

            return $result;
        }

        if (is_string($value)) {
            $value = preg_replace(
                '/([?&](?:secret|token|access_token|refresh_token)=)[^&\s]+/i',
                '$1[redacted]',
                $value
            ) ?? $value;

            return Str::limit($value, 4000, '…');
        }

        return $value;
    }
}
