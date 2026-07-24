<?php

namespace App\Domain\Banking\Services;

use App\Models\BankAuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class BankAuditLogger
{
    private const SENSITIVE_KEYS = [
        'authorization',
        'access_token',
        'refresh_token',
        'id_token',
        'client_secret',
        'password',
        'private_key',
        'ssl_key',
        'mtls_key',
        'raw_payload',
        'request_body',
        'response_body',
    ];

    public function record(
        string $action,
        ?Model $auditable = null,
        array $metadata = [],
        User|int|null $user = null,
        ?string $correlationId = null,
    ): BankAuditEvent {
        return DB::transaction(function () use ($action, $auditable, $metadata, $user, $correlationId): BankAuditEvent {
            $previous = BankAuditEvent::query()->lockForUpdate()->latest('id')->first();
            $createdAt = now()->startOfSecond();
            $correlationId ??= (string) Str::uuid();
            $safeMetadata = $this->canonicalize($this->sanitize($metadata));
            $userId = $user instanceof User ? $user->id : $user;
            $payload = [
                'user_id' => $userId,
                'action' => $action,
                'auditable_type' => $auditable?->getMorphClass(),
                'auditable_id' => $auditable?->getKey(),
                'correlation_id' => $correlationId,
                'metadata' => $safeMetadata,
                'previous_hash' => $previous?->hash,
                'created_at' => $createdAt->toISOString(),
            ];

            return BankAuditEvent::query()->create([
                ...$payload,
                'hash' => hash('sha256', json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)),
                'created_at' => $createdAt,
            ]);
        }, 3);
    }

    private function sanitize(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && in_array(strtolower($key), self::SENSITIVE_KEYS, true)) {
            return '[REDACTED]';
        }

        if (! is_array($value)) {
            if (! is_string($value)) {
                return $value;
            }

            $value = preg_replace(
                '/Bearer\s+[A-Za-z0-9._~+\/=-]+/i',
                'Bearer [REDACTED]',
                $value
            ) ?? $value;
            $value = preg_replace(
                '/\b(access_token|refresh_token|id_token|client_secret)\s*[:=]\s*([^&\s,;]+)/i',
                '$1=[REDACTED]',
                $value
            ) ?? $value;
            $value = preg_replace('/\b\d{16,34}\b/u', '[REDACTED_NUMBER]', $value) ?? $value;

            return mb_strlen($value) > 2048
                ? mb_substr($value, 0, 2048).'…'
                : $value;
        }

        $safe = [];

        foreach ($value as $itemKey => $itemValue) {
            $safe[$itemKey] = $this->sanitize($itemValue, (string) $itemKey);
        }

        return $safe;
    }

    private function canonicalize(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value);
        }

        foreach ($value as $key => $item) {
            $value[$key] = $this->canonicalize($item);
        }

        return $value;
    }
}
