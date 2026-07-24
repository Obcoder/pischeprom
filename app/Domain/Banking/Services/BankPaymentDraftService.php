<?php

namespace App\Domain\Banking\Services;

use App\Domain\Banking\Enums\PaymentDraftStatus;
use App\Domain\Banking\Exceptions\ReconciliationConflictException;
use App\Models\BankAccount;
use App\Models\BankPaymentOrderDraft;
use App\Models\Entity;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Local-only service by design. It has no dependency on any bank provider or
 * HTTP client and cannot send, sign, execute, cancel, or repeat a bank payment.
 */
class BankPaymentDraftService
{
    public function __construct(private readonly BankAuditLogger $audit) {}

    public function create(array $data, User $user): BankPaymentOrderDraft
    {
        return DB::transaction(function () use ($data, $user): BankPaymentOrderDraft {
            $account = BankAccount::query()->with('connection.ownerEntity')->findOrFail($data['payer_bank_account_id']);
            $recipient = Entity::query()->findOrFail($data['recipient_entity_id']);
            $purchase = isset($data['purchase_id'])
                ? Purchase::query()->findOrFail($data['purchase_id'])
                : null;
            $this->assertPurchaseRecipient($purchase, $recipient);
            $draft = BankPaymentOrderDraft::query()->create([
                ...$this->attributes($data, $account, $recipient),
                'number' => $data['number'] ?? $this->nextNumber(),
                'document_date' => $data['document_date'],
                'status' => PaymentDraftStatus::Draft,
                'payer_bank_account_id' => $account->id,
                'recipient_entity_id' => $recipient->id,
                'purchase_id' => $purchase?->id,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);
            $this->audit->record('bank.payment_draft.created', $draft, [
                'purchase_id' => $purchase?->id,
                'amount' => (string) $draft->amount,
                'currency' => $draft->currency,
            ], $user);

            return $draft->fresh(['payerAccount', 'recipientEntity', 'purchase']);
        }, 3);
    }

    public function update(BankPaymentOrderDraft $draft, array $data, User $user): BankPaymentOrderDraft
    {
        return DB::transaction(function () use ($draft, $data, $user): BankPaymentOrderDraft {
            $locked = BankPaymentOrderDraft::query()->whereKey($draft->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== PaymentDraftStatus::Draft) {
                throw new ReconciliationConflictException('Only a draft document can be edited.');
            }

            $account = BankAccount::query()->with('connection.ownerEntity')->findOrFail($data['payer_bank_account_id']);
            $recipient = Entity::query()->findOrFail($data['recipient_entity_id']);
            $purchase = isset($data['purchase_id'])
                ? Purchase::query()->findOrFail($data['purchase_id'])
                : null;
            $this->assertPurchaseRecipient($purchase, $recipient);
            $locked->forceFill([
                ...$this->attributes($data, $account, $recipient),
                'number' => $data['number'] ?? $locked->number,
                'document_date' => $data['document_date'],
                'payer_bank_account_id' => $account->id,
                'recipient_entity_id' => $recipient->id,
                'purchase_id' => $purchase?->id,
                'updated_by' => $user->id,
            ])->save();
            $this->audit->record('bank.payment_draft.updated', $locked, [
                'amount' => (string) $locked->amount,
                'currency' => $locked->currency,
            ], $user);

            return $locked->fresh(['payerAccount', 'recipientEntity', 'purchase']);
        }, 3);
    }

    public function markExported(BankPaymentOrderDraft $draft, User $user): BankPaymentOrderDraft
    {
        return DB::transaction(function () use ($draft, $user): BankPaymentOrderDraft {
            $locked = BankPaymentOrderDraft::query()->whereKey($draft->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === PaymentDraftStatus::Cancelled) {
                throw new ReconciliationConflictException('A cancelled draft cannot be exported.');
            }

            $locked->forceFill([
                'status' => PaymentDraftStatus::Exported,
                'exported_at' => now(),
                'updated_by' => $user->id,
            ])->save();
            $this->audit->record('bank.payment_draft.exported_locally', $locked, [
                'notice' => 'Local export only; not sent to a bank.',
            ], $user);

            return $locked->fresh();
        }, 3);
    }

    public function cancel(BankPaymentOrderDraft $draft, User $user): BankPaymentOrderDraft
    {
        return DB::transaction(function () use ($draft, $user): BankPaymentOrderDraft {
            $locked = BankPaymentOrderDraft::query()->whereKey($draft->id)->lockForUpdate()->firstOrFail();
            $locked->forceFill([
                'status' => PaymentDraftStatus::Cancelled,
                'updated_by' => $user->id,
            ])->save();
            $this->audit->record('bank.payment_draft.cancelled_locally', $locked, [], $user);

            return $locked->fresh();
        }, 3);
    }

    private function attributes(array $data, BankAccount $account, Entity $recipient): array
    {
        $owner = $account->connection->ownerEntity;

        if (! $owner) {
            throw new ReconciliationConflictException('The bank connection has no owner Entity.');
        }

        $currency = mb_strtoupper((string) $data['currency']);

        if (! hash_equals(mb_strtoupper((string) $account->currency), $currency)) {
            throw new ReconciliationConflictException('The draft currency must match the selected payer account.');
        }

        return [
            'amount' => DecimalMoney::normalize((string) $data['amount']),
            'currency' => $currency,
            'payer_name' => $data['payer_name'] ?? ($owner->full_name ?: $owner->name),
            'payer_inn' => $data['payer_inn'] ?? $owner->INN,
            'payer_kpp' => $data['payer_kpp'] ?? $owner->KPP,
            'payer_account' => $data['payer_account'] ?? $account->account_number,
            'payer_bank_name' => $data['payer_bank_name'],
            'payer_bic' => $data['payer_bic'] ?? data_get($account->normalized_requisites, 'bic'),
            'payer_corr_account' => $data['payer_corr_account'] ?? data_get($account->normalized_requisites, 'corr_account'),
            'recipient_name' => $data['recipient_name'] ?? ($recipient->full_name ?: $recipient->name),
            'recipient_inn' => $data['recipient_inn'] ?? $recipient->INN,
            'recipient_kpp' => $data['recipient_kpp'] ?? $recipient->KPP,
            'recipient_account' => $data['recipient_account'] ?? $recipient->bank_account_number,
            'recipient_bank_name' => $data['recipient_bank_name'] ?? $recipient->bank_name,
            'recipient_bic' => $data['recipient_bic'] ?? $recipient->bank_bic,
            'recipient_corr_account' => $data['recipient_corr_account'] ?? $recipient->bank_corr_account,
            'purpose' => $data['purpose'],
            'vat_type' => $data['vat_type'],
            'vat_rate' => $data['vat_rate'] ?? null,
            'vat_amount' => isset($data['vat_amount']) && $data['vat_amount'] !== ''
                ? DecimalMoney::normalize((string) $data['vat_amount'])
                : null,
            'payment_priority' => (int) $data['payment_priority'],
            'budget_fields' => $data['budget_fields'] ?? null,
        ];
    }

    private function assertPurchaseRecipient(?Purchase $purchase, Entity $recipient): void
    {
        if ($purchase && (int) $purchase->entity_id !== (int) $recipient->id) {
            throw new ReconciliationConflictException('The purchase supplier does not match the draft recipient.');
        }
    }

    private function nextNumber(): string
    {
        $date = now()->format('Ymd');
        $lastId = (int) (BankPaymentOrderDraft::query()->lockForUpdate()->max('id') ?? 0);

        return sprintf('DRAFT-%s-%06d', $date, $lastId + 1);
    }
}
