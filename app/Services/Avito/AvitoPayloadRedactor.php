<?php

namespace App\Services\Avito;

use Illuminate\Support\Str;

class AvitoPayloadRedactor
{
    private const DEFAULT_MAX_DEPTH = 10;

    private const SECRET_MARKERS = [
        'authorization',
        'access_token',
        'refresh_token',
        'client_secret',
        'client_id',
        'password',
        'secret',
    ];

    public function redact(mixed $value, int $maxDepth = self::DEFAULT_MAX_DEPTH): mixed
    {
        return $this->redactValue($value, 0, max(1, $maxDepth));
    }

    private function redactValue(mixed $value, int $depth, int $maxDepth): mixed
    {
        if ($depth > $maxDepth) {
            return '[truncated]';
        }

        if (is_array($value)) {
            $result = [];

            foreach ($value as $key => $item) {
                $normalized = Str::lower((string) $key);
                $result[$key] = Str::contains($normalized, self::SECRET_MARKERS)
                    ? '[redacted]'
                    : $this->redactValue($item, $depth + 1, $maxDepth);
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
