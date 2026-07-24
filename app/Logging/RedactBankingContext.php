<?php

namespace App\Logging;

use Monolog\Logger;
use Monolog\LogRecord;

class RedactBankingContext
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

    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(function (LogRecord $record): LogRecord {
            return $record->with(
                message: $this->redactString($record->message),
                context: $this->redactValue($record->context),
                extra: $this->redactValue($record->extra),
            );
        });
    }

    private function redactValue(mixed $value, ?string $key = null): mixed
    {
        if ($key !== null && in_array(strtolower($key), self::SENSITIVE_KEYS, true)) {
            return '[REDACTED]';
        }

        if (is_array($value)) {
            $redacted = [];

            foreach ($value as $itemKey => $itemValue) {
                $redacted[$itemKey] = $this->redactValue($itemValue, (string) $itemKey);
            }

            return $redacted;
        }

        return is_string($value) ? $this->redactString($value) : $value;
    }

    private function redactString(string $value): string
    {
        $value = preg_replace('/Bearer\s+[A-Za-z0-9._~+\/=-]+/i', 'Bearer [REDACTED]', $value) ?? $value;
        $value = preg_replace(
            '/\b(access_token|refresh_token|id_token|client_secret)\s*[:=]\s*([^&\s,;]+)/i',
            '$1=[REDACTED]',
            $value
        ) ?? $value;
        $value = preg_replace('/\b\d{16,34}\b/u', '[REDACTED_NUMBER]', $value) ?? $value;

        return $value;
    }
}
