<?php

namespace App\Domain\AiPriceLists\Services;

use App\Models\PriceListEvent;
use App\Models\PriceListImport;
use App\Models\User;
use Illuminate\Support\Str;

class PriceListAuditLogger
{
    private const REDACTED_KEYS = [
        'access_token',
        'api_key',
        'authorization',
        'content',
        'document',
        'body',
        'password',
        'payload',
        'prompt',
        'raw_payload',
        'secret',
        'signed_url',
        'token',
        'url',
    ];

    public function record(
        PriceListImport $import,
        string $eventType,
        array $metadata = [],
        User|int|null $user = null,
        ?string $correlationId = null,
        ?int $durationMs = null,
        ?string $statusFrom = null,
        ?string $statusTo = null,
        ?string $stage = null,
    ): PriceListEvent {
        return PriceListEvent::query()->create([
            'price_list_import_id' => $import->id,
            'user_id' => $user instanceof User ? $user->id : $user,
            'correlation_id' => $correlationId ?: (string) Str::uuid(),
            'event_type' => $eventType,
            'stage' => $stage,
            'status_from' => $statusFrom,
            'status_to' => $statusTo,
            'metadata' => $this->sanitize($metadata),
            'duration_ms' => $durationMs,
            'created_at' => now(),
        ]);
    }

    private function sanitize(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && in_array(strtolower($key), self::REDACTED_KEYS, true)) {
            return '[REDACTED]';
        }

        if (is_array($value)) {
            $safe = [];

            foreach ($value as $itemKey => $itemValue) {
                $safe[$itemKey] = $this->sanitize($itemValue, (string) $itemKey);
            }

            return $safe;
        }

        if (! is_string($value)) {
            return $value;
        }

        $value = preg_replace('/Bearer\s+[^\s]+/i', 'Bearer [REDACTED]', $value) ?? $value;

        return mb_strlen($value) > 1000 ? mb_substr($value, 0, 1000).'…' : $value;
    }
}
