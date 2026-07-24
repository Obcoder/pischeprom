<?php

namespace App\Domain\Banking\Services;

use App\Domain\Banking\DTO\BankImportResult;
use App\Domain\Banking\DTO\BankTransactionData;
use App\Domain\Banking\Enums\BankTransactionDirection;
use App\Domain\Banking\Enums\BankTransactionStatus;
use App\Domain\Banking\Enums\MatchSuggestionStatus;
use App\Domain\Banking\Enums\ReconciliationStatus;
use App\Domain\Banking\Events\BankTransactionChanged;
use App\Domain\Banking\Events\BankTransactionImported;
use App\Models\BankAccount;
use App\Models\BankMatchSuggestion;
use App\Models\BankTransaction;
use App\Models\Entity;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class BankTransactionImporter
{
    private const MUTABLE_FIELDS = [
        'operation_date',
        'posting_date',
        'value_date',
        'direction',
        'amount',
        'currency',
        'status',
        'bank_document_number',
        'purpose',
        'payer_name',
        'payer_inn',
        'payer_kpp',
        'payer_account',
        'payer_bank_name',
        'payer_bic',
        'payer_corr_account',
        'recipient_name',
        'recipient_inn',
        'recipient_kpp',
        'recipient_account',
        'recipient_bank_name',
        'recipient_bic',
        'recipient_corr_account',
        'entity_id',
        'bank_modified_at',
    ];

    public function __construct(
        private readonly BankAuditLogger $audit,
        private readonly PaymentAllocationService $allocations,
    ) {}

    /** @param Collection<int, BankTransactionData> $transactions */
    public function import(BankAccount $account, Collection $transactions): BankImportResult
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $ids = [];

        foreach ($transactions as $data) {
            if (! $data instanceof BankTransactionData) {
                $skipped++;

                continue;
            }

            $result = $this->importOne($account, $data);
            $ids[] = $result['transaction']->id;

            match ($result['action']) {
                'created' => $created++,
                'updated' => $updated++,
                default => $skipped++,
            };
        }

        return new BankImportResult(
            received: $transactions->count(),
            created: $created,
            updated: $updated,
            skipped: $skipped,
            transactionIds: array_values(array_unique($ids)),
        );
    }

    /**
     * @return array{transaction:BankTransaction,action:string}
     */
    private function importOne(BankAccount $account, BankTransactionData $data): array
    {
        return DB::transaction(function () use ($account, $data): array {
            $fingerprint = $this->fingerprint($account, $data);
            $query = BankTransaction::query()
                ->where('bank_account_id', $account->id)
                ->where(function ($query) use ($data, $fingerprint): void {
                    if ($data->operationId) {
                        $query->where('provider_operation_id', $data->operationId)
                            ->orWhere('fingerprint', $fingerprint);
                    } else {
                        $query->where('fingerprint', $fingerprint);
                    }
                });
            /** @var BankTransaction|null $transaction */
            $transaction = $query->lockForUpdate()->first();
            $attributes = $this->attributes($account, $data, $fingerprint);
            $payloadHash = hash('sha256', $this->canonicalJson($data->rawPayload));

            if (! $transaction) {
                $transaction = BankTransaction::query()->create([
                    ...$attributes,
                    'raw_payload' => $data->rawPayload,
                    'imported_at' => now(),
                    'reconciliation_status' => ReconciliationStatus::Unmatched,
                ]);
                $transaction->revisions()->create([
                    'status' => $data->status,
                    'payload_hash' => $payloadHash,
                    'changed_fields' => ['initial_import'],
                    'raw_payload' => $data->rawPayload,
                    'recorded_at' => now(),
                ]);
                $this->audit->record('bank.transaction.imported', $transaction, [
                    'account_id' => $account->id,
                    'operation_id_hash' => $data->operationId ? hash('sha256', $data->operationId) : null,
                ]);
                BankTransactionImported::dispatch($transaction);

                return ['transaction' => $transaction, 'action' => 'created'];
            }

            $changedFields = $this->changedFields($transaction, $attributes);
            $lastRevision = $transaction->revisions()->latest('id')->first();

            if ($changedFields === [] && $lastRevision?->payload_hash === $payloadHash) {
                $transaction->forceFill(['imported_at' => now()])->save();

                return ['transaction' => $transaction, 'action' => 'skipped'];
            }

            $oldPayload = $transaction->raw_payload ?? [];
            $oldStatus = $transaction->status;
            $transaction->revisions()->firstOrCreate(
                ['payload_hash' => $payloadHash],
                [
                    'status' => $data->status,
                    'changed_fields' => $changedFields,
                    'raw_payload' => $data->rawPayload,
                    'recorded_at' => now(),
                ]
            );
            $transaction->forceFill([
                ...$attributes,
                'raw_payload' => $data->rawPayload,
                'imported_at' => now(),
            ])->save();

            $allocationSensitive = $changedFields !== [];

            if (
                $allocationSensitive
                || $data->status !== BankTransactionStatus::Posted
                || $oldStatus !== BankTransactionStatus::Posted
            ) {
                BankMatchSuggestion::query()
                    ->where('bank_transaction_id', $transaction->id)
                    ->whereIn('status', [
                        MatchSuggestionStatus::Pending->value,
                        MatchSuggestionStatus::Rejected->value,
                    ])
                    ->update(['status' => MatchSuggestionStatus::Expired->value]);
                $this->allocations->reverseForChangedTransaction(
                    $transaction,
                    'Bank operation changed after import.'
                );
            }

            $this->audit->record('bank.transaction.changed', $transaction, [
                'changed_fields' => $changedFields,
                'previous_payload_hash' => hash('sha256', $this->canonicalJson(is_array($oldPayload) ? $oldPayload : [])),
                'current_payload_hash' => $payloadHash,
                'requires_review' => $allocationSensitive,
            ]);
            BankTransactionChanged::dispatch($transaction, $changedFields);

            return ['transaction' => $transaction, 'action' => 'updated'];
        }, 3);
    }

    private function attributes(BankAccount $account, BankTransactionData $data, string $fingerprint): array
    {
        return [
            'bank_connection_id' => $account->bank_connection_id,
            'bank_account_id' => $account->id,
            'provider_operation_id' => $data->operationId,
            'fingerprint' => $fingerprint,
            'operation_date' => $data->operationDate->format('Y-m-d'),
            'posting_date' => $data->postingDate?->format('Y-m-d'),
            'value_date' => $data->valueDate?->format('Y-m-d'),
            'direction' => $data->direction,
            'amount' => $data->amount,
            'currency' => $data->currency,
            'status' => $data->status,
            'bank_document_number' => $data->documentNumber,
            'purpose' => $data->purpose,
            'payer_name' => $data->payerName,
            'payer_inn' => $data->payerInn,
            'payer_kpp' => $data->payerKpp,
            'payer_account' => $data->payerAccount,
            'payer_bank_name' => $data->payerBankName,
            'payer_bic' => $data->payerBic,
            'payer_corr_account' => $data->payerCorrAccount,
            'recipient_name' => $data->recipientName,
            'recipient_inn' => $data->recipientInn,
            'recipient_kpp' => $data->recipientKpp,
            'recipient_account' => $data->recipientAccount,
            'recipient_bank_name' => $data->recipientBankName,
            'recipient_bic' => $data->recipientBic,
            'recipient_corr_account' => $data->recipientCorrAccount,
            'entity_id' => $this->resolveEntityId($data),
            'bank_modified_at' => $data->bankModifiedAt,
        ];
    }

    private function resolveEntityId(BankTransactionData $data): ?int
    {
        $counterpartyInn = $data->direction === BankTransactionDirection::Credit
            ? $data->payerInn
            : $data->recipientInn;
        $counterpartyAccount = $data->direction === BankTransactionDirection::Credit
            ? $data->payerAccount
            : $data->recipientAccount;
        $inn = preg_replace('/\D+/', '', (string) $counterpartyInn);
        $account = preg_replace('/\D+/', '', (string) $counterpartyAccount);

        if ($inn === '' && $account === '') {
            return null;
        }

        $matches = Entity::query()
            ->where(function ($query) use ($inn, $account): void {
                if ($inn !== '') {
                    $query->orWhere('INN', $inn);
                }

                if ($account !== '') {
                    $query->orWhere('bank_account_number', $account);
                }
            })
            ->limit(2)
            ->pluck('id');

        return $matches->count() === 1 ? (int) $matches->first() : null;
    }

    private function fingerprint(BankAccount $account, BankTransactionData $data): string
    {
        $parts = $data->operationId
            ? [
                $account->bank_connection_id,
                $account->account_number,
                $data->operationDate->format('Y-m-d'),
                $data->operationId,
            ]
            : [
                $account->bank_connection_id,
                $account->account_number,
                $data->operationDate->format('Y-m-d'),
                $data->postingDate?->format('Y-m-d'),
                $data->direction->value,
                $data->amount,
                $data->currency,
                $data->documentNumber,
                $data->payerInn,
                $data->payerAccount,
                $data->purpose,
            ];

        return hash('sha256', implode("\x1f", array_map(
            static fn (mixed $value): string => (string) ($value ?? ''),
            $parts
        )));
    }

    private function changedFields(BankTransaction $transaction, array $attributes): array
    {
        $changed = [];

        foreach (self::MUTABLE_FIELDS as $field) {
            $new = $this->comparableValue($field, $attributes[$field] ?? null);
            $old = $this->comparableValue($field, $transaction->{$field});

            if ((string) ($old ?? '') !== (string) ($new ?? '')) {
                $changed[] = $field;
            }
        }

        return $changed;
    }

    private function comparableValue(string $field, mixed $value): mixed
    {
        if ($value instanceof \BackedEnum) {
            $value = $value->value;
        }

        if ($value === null || $value === '') {
            return null;
        }

        if ($field === 'amount') {
            return DecimalMoney::normalize(is_int($value) ? $value : (string) $value);
        }

        if (in_array($field, ['operation_date', 'posting_date', 'value_date'], true)) {
            return $value instanceof \DateTimeInterface
                ? $value->format('Y-m-d')
                : \Carbon\CarbonImmutable::parse((string) $value)->format('Y-m-d');
        }

        if ($field === 'bank_modified_at') {
            return $value instanceof \DateTimeInterface
                ? $value->format('Y-m-d H:i:s')
                : \Carbon\CarbonImmutable::parse((string) $value)->format('Y-m-d H:i:s');
        }

        if ($field === 'entity_id') {
            return (int) $value;
        }

        return (string) $value;
    }

    private function canonicalJson(array $payload): string
    {
        $sort = function (mixed $value) use (&$sort): mixed {
            if (! is_array($value)) {
                return $value;
            }

            if (! array_is_list($value)) {
                ksort($value);
            }

            foreach ($value as $key => $item) {
                $value[$key] = $sort($item);
            }

            return $value;
        };

        return json_encode($sort($payload), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }
}
