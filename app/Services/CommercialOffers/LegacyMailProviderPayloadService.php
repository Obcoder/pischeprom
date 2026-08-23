<?php

namespace App\Services\CommercialOffers;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use stdClass;

final class LegacyMailProviderPayloadService
{
    private const TABLES = [
        'mailing_webhook_calls' => [
            'columns' => ['raw_payload', 'parsed_payload', 'error_message'],
            'timestamp' => 'created_at',
        ],
        'mailing_events' => [
            'columns' => ['payload', 'email', 'url', 'destination_response', 'user_agent', 'ip', 'country', 'city', 'sender_ip', 'metadata'],
            'timestamp' => 'created_at',
        ],
        'mailing_messages' => [
            'columns' => ['request_payload', 'response_payload', 'failed_emails', 'error_message'],
            'timestamp' => 'created_at',
        ],
        'mailing_campaign_recipients' => [
            'columns' => ['last_clicked_url', 'failure_reason', 'delivery_info'],
            'timestamp' => 'created_at',
        ],
    ];

    public function __construct(private readonly SafeMailProviderIdentifier $identifiers) {}

    public function audit(int $chunkSize = 500): array
    {
        $chunkSize = $this->chunkSize($chunkSize);
        $tables = [];

        foreach (self::TABLES as $table => $definition) {
            $summary = [
                'rows' => 0,
                'approximate_bytes' => 0,
                'oldest_at' => null,
                'newest_at' => null,
            ];

            $this->affectedQuery($table, $definition['columns'])
                ->select(array_values(array_unique(array_merge(['id', $definition['timestamp']], $definition['columns']))))
                ->orderBy('id')
                ->chunkById($chunkSize, function ($rows) use (&$summary, $definition): void {
                    foreach ($rows as $row) {
                        $summary['rows']++;
                        foreach ($definition['columns'] as $column) {
                            $summary['approximate_bytes'] += $this->byteLength($row->{$column} ?? null);
                        }

                        $timestamp = $row->{$definition['timestamp']} ?? null;
                        if ($timestamp !== null) {
                            $timestamp = (string) $timestamp;
                            $summary['oldest_at'] = $summary['oldest_at'] === null || $timestamp < $summary['oldest_at']
                                ? $timestamp
                                : $summary['oldest_at'];
                            $summary['newest_at'] = $summary['newest_at'] === null || $timestamp > $summary['newest_at']
                                ? $timestamp
                                : $summary['newest_at'];
                        }
                    }
                });

            $tables[$table] = $summary;
        }

        return [
            'tables' => $tables,
            'total_rows' => array_sum(array_column($tables, 'rows')),
            'total_approximate_bytes' => array_sum(array_column($tables, 'approximate_bytes')),
        ];
    }

    public function purge(bool $apply = false, int $chunkSize = 500): array
    {
        if ($apply && ! app()->environment(['local', 'testing', 'staging'])) {
            throw new RuntimeException('Provider payload purge apply is blocked outside local/testing/staging.');
        }

        $before = $this->audit($chunkSize);
        if (! $apply) {
            return ['applied' => false, 'before' => $before, 'updated_rows' => [], 'after' => $before];
        }

        $chunkSize = $this->chunkSize($chunkSize);
        $updated = [
            'mailing_webhook_calls' => $this->purgeWebhookCalls($chunkSize),
            'mailing_events' => $this->purgeEvents($chunkSize),
            'mailing_messages' => $this->purgeMessages($chunkSize),
            'mailing_campaign_recipients' => $this->purgeRecipientErrors($chunkSize),
        ];

        return [
            'applied' => true,
            'before' => $before,
            'updated_rows' => $updated,
            'after' => $this->audit($chunkSize),
        ];
    }

    private function purgeWebhookCalls(int $chunkSize): int
    {
        $updated = 0;
        $this->affectedQuery('mailing_webhook_calls', self::TABLES['mailing_webhook_calls']['columns'])
            ->select(['id', 'request_hash', 'raw_payload', 'parsed_payload', 'error_message'])
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use (&$updated): void {
                DB::transaction(function () use ($rows, &$updated): void {
                    foreach ($rows as $row) {
                        $requestHash = $row->request_hash;
                        if (! $requestHash && is_string($row->raw_payload) && $row->raw_payload !== '') {
                            $candidate = hash('sha256', $row->raw_payload);
                            $requestHash = DB::table('mailing_webhook_calls')
                                ->where('request_hash', $candidate)
                                ->where('id', '!=', $row->id)
                                ->exists() ? null : $candidate;
                        }

                        $updated += DB::table('mailing_webhook_calls')->where('id', $row->id)->update([
                            'request_hash' => $requestHash,
                            'raw_payload' => null,
                            'parsed_payload' => null,
                            'error_message' => null,
                            'safe_error_code' => $row->error_message !== null
                                ? MailProviderSafeErrorCode::ProcessingFailedSafe->value
                                : null,
                            'safe_summary' => 'legacy_raw_columns_purged',
                        ]);
                    }
                });
            });

        return $updated;
    }

    private function purgeEvents(int $chunkSize): int
    {
        $updated = 0;
        $columns = self::TABLES['mailing_events']['columns'];
        $this->affectedQuery('mailing_events', $columns)
            ->select(array_merge(['id', 'event_name', 'status'], $columns))
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use (&$updated): void {
                DB::transaction(function () use ($rows, &$updated): void {
                    foreach ($rows as $row) {
                        [$eventType, $status, $providerEventId, $providerMessageId] = $this->legacyEventMetadata($row);
                        $updated += DB::table('mailing_events')->where('id', $row->id)->update([
                            'provider_event_id' => $providerEventId,
                            'provider_message_id' => $providerMessageId,
                            'normalized_event_type' => $eventType,
                            'normalized_status' => $status,
                            'safe_error_code' => $status === 'unknown' ? MailProviderSafeErrorCode::UnknownEvent->value : null,
                            'safe_summary' => 'legacy_raw_columns_purged',
                            'payload' => null,
                            'email' => null,
                            'url' => null,
                            'destination_response' => null,
                            'user_agent' => null,
                            'ip' => null,
                            'country' => null,
                            'city' => null,
                            'sender_ip' => null,
                            'metadata' => null,
                        ]);
                    }
                });
            });

        return $updated;
    }

    private function purgeMessages(int $chunkSize): int
    {
        $updated = 0;
        $this->affectedQuery('mailing_messages', self::TABLES['mailing_messages']['columns'])
            ->select(['id', 'request_hash', 'response_hash', 'request_payload', 'response_payload', 'failed_emails', 'error_message'])
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use (&$updated): void {
                DB::transaction(function () use ($rows, &$updated): void {
                    foreach ($rows as $row) {
                        $updated += DB::table('mailing_messages')->where('id', $row->id)->update([
                            'request_hash' => $row->request_hash ?: $this->valueHash($row->request_payload),
                            'response_hash' => $row->response_hash ?: $this->valueHash($row->response_payload),
                            'safe_error_code' => $row->error_message !== null
                                ? MailProviderSafeErrorCode::ProcessingFailedSafe->value
                                : null,
                            'safe_summary' => 'legacy_raw_columns_purged',
                            'request_payload' => null,
                            'response_payload' => null,
                            'failed_emails' => null,
                            'error_message' => null,
                        ]);
                    }
                });
            });

        return $updated;
    }

    private function purgeRecipientErrors(int $chunkSize): int
    {
        $updated = 0;
        $this->affectedQuery('mailing_campaign_recipients', self::TABLES['mailing_campaign_recipients']['columns'])
            ->select(['id', 'last_clicked_url', 'failure_reason', 'delivery_info'])
            ->orderBy('id')
            ->chunkById($chunkSize, function ($rows) use (&$updated): void {
                DB::transaction(function () use ($rows, &$updated): void {
                    foreach ($rows as $row) {
                        $safeErrorCode = is_string($row->failure_reason)
                            ? MailProviderSafeErrorCode::tryFrom($row->failure_reason)?->value
                            : null;
                        $safeSummary = match ($row->failure_reason) {
                            'provider_soft_bounce' => 'provider_soft_bounce',
                            'provider_hard_bounce' => 'provider_hard_bounce',
                            default => 'legacy_raw_columns_purged',
                        };

                        $updated += DB::table('mailing_campaign_recipients')->where('id', $row->id)->update([
                            'last_clicked_url' => null,
                            'failure_reason' => null,
                            'delivery_info' => null,
                            'safe_error_code' => $safeErrorCode
                                ?? ($row->failure_reason !== null ? MailProviderSafeErrorCode::ProcessingFailedSafe->value : null),
                            'safe_summary' => $safeSummary,
                        ]);
                    }
                });
            });

        return $updated;
    }

    private function affectedQuery(string $table, array $columns): Builder
    {
        return DB::table($table)->where(function (Builder $query) use ($columns): void {
            foreach ($columns as $column) {
                $query->orWhereNotNull($column);
            }
        });
    }

    private function legacyEventMetadata(stdClass $row): array
    {
        $eventName = mb_strtolower(trim((string) ($row->event_name ?? '')));
        $statusValue = mb_strtolower(trim((string) ($row->status ?? '')));
        $eventType = match ($eventName) {
            'transactional_email_status', 'email_status' => 'email_status',
            'transactional_spam_block', 'spam_block' => 'spam_block',
            default => 'unknown',
        };
        $status = match ($statusValue) {
            'sent', 'accepted', 'delivered', 'opened', 'clicked', 'unsubscribed', 'soft_bounced', 'hard_bounced', 'spam' => $statusValue,
            default => 'unknown',
        };

        $payload = $this->decodeArray($row->payload ?? null);
        $data = $payload['event_data'] ?? $payload['data'] ?? $payload;
        $data = is_array($data) ? $data : [];

        return [
            $eventType,
            $status,
            $this->safeProviderId($payload['event_id'] ?? $payload['id'] ?? $data['event_id'] ?? $data['id'] ?? null),
            $this->safeProviderId($data['message_id'] ?? $data['messageId'] ?? null),
        ];
    }

    private function decodeArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function safeProviderId(mixed $value): ?string
    {
        return $this->identifiers->normalize($value);
    }

    private function valueHash(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return hash('sha256', is_string($value) ? $value : (json_encode($value, JSON_UNESCAPED_SLASHES) ?: ''));
    }

    private function byteLength(mixed $value): int
    {
        if ($value === null) {
            return 0;
        }

        return strlen(is_string($value) ? $value : (json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: ''));
    }

    private function chunkSize(int $chunkSize): int
    {
        return min(2000, max(50, $chunkSize));
    }
}
