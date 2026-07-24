<?php

namespace Tests\Feature\Banking;

use App\Domain\Banking\DTO\BankTransactionData;
use App\Domain\Banking\Enums\AllocationSource;
use App\Domain\Banking\Enums\BankTransactionDirection;
use App\Domain\Banking\Enums\BankTransactionStatus;
use App\Domain\Banking\Reconciliation\BankReconciliationService;
use App\Domain\Banking\Services\BankTransactionImporter;
use App\Domain\Banking\Services\PaymentAllocationService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Event;

class BankTransactionImporterTest extends BankingDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
    }

    public function test_repeated_import_is_idempotent(): void
    {
        $account = $this->createAccount($this->createConnection());
        $data = $this->transactionData();
        $importer = app(BankTransactionImporter::class);

        $first = $importer->import($account, collect([$data]));
        $second = $importer->import($account, collect([$data]));

        $this->assertSame(1, $first->created);
        $this->assertSame(1, $second->skipped);
        $this->assertDatabaseCount('bank_transactions', 1);
        $this->assertDatabaseCount('bank_transaction_revisions', 1);
    }

    public function test_changed_or_cancelled_operation_is_updated_and_allocations_are_reversed(): void
    {
        $user = $this->createUser();
        $entity = $this->createEntity();
        $sale = $this->createSale($entity);
        $account = $this->createAccount($this->createConnection());
        $importer = app(BankTransactionImporter::class);
        $initial = $importer->import($account, collect([$this->transactionData()]));
        $transactionId = $initial->transactionIds[0];
        app(PaymentAllocationService::class)->allocate(
            $transactionId,
            $sale,
            '100.00',
            AllocationSource::Manual,
            $user,
        );

        $changed = $importer->import($account, collect([
            $this->transactionData(
                status: BankTransactionStatus::Cancelled,
                rawPayload: ['operationId' => 'operation-1', 'status' => 'CANCELLED'],
            ),
        ]));

        $this->assertSame(1, $changed->updated);
        $this->assertDatabaseHas('bank_transactions', [
            'id' => $transactionId,
            'status' => 'cancelled',
            'reconciliation_status' => 'needs_review',
        ]);
        $this->assertDatabaseHas('bank_transaction_allocations', [
            'bank_transaction_id' => $transactionId,
            'is_active' => 0,
        ]);
        $this->assertSame('unpaid', $sale->fresh()->payment_status);
        $this->assertDatabaseCount('bank_transaction_revisions', 2);
    }

    public function test_changed_posted_operation_remains_for_manual_review(): void
    {
        $user = $this->createUser();
        $entity = $this->createEntity();
        $sale = $this->createSale($entity);
        $account = $this->createAccount($this->createConnection());
        $importer = app(BankTransactionImporter::class);
        $transactionId = $importer
            ->import($account, collect([$this->transactionData()]))
            ->transactionIds[0];
        app(PaymentAllocationService::class)->allocate(
            $transactionId,
            $sale,
            '100.00',
            AllocationSource::Manual,
            $user,
        );

        $importer->import($account, collect([
            $this->transactionData(
                rawPayload: ['operationId' => 'operation-1', 'amount' => '90.00'],
                amount: '90.00',
            ),
        ]));
        $transaction = \App\Models\BankTransaction::query()->findOrFail($transactionId);

        app(BankReconciliationService::class)->reconcile($transaction);

        $this->assertSame('needs_review', $transaction->fresh()->reconciliation_status->value);
        $this->assertSame('bank_operation_changed', $transaction->fresh()->review_reason);
        $this->assertDatabaseMissing('bank_transaction_allocations', [
            'bank_transaction_id' => $transactionId,
            'is_active' => 1,
        ]);
        $this->assertSame('unpaid', $sale->fresh()->payment_status);
    }

    public function test_debit_transaction_resolves_the_recipient_as_counterparty(): void
    {
        $supplier = $this->createEntity([
            'INN' => '7700000000',
            'bank_account_number' => '40702810000000000001',
        ]);
        $account = $this->createAccount($this->createConnection());

        $result = app(BankTransactionImporter::class)->import($account, collect([
            $this->transactionData(direction: BankTransactionDirection::Debit),
        ]));

        $this->assertDatabaseHas('bank_transactions', [
            'id' => $result->transactionIds[0],
            'direction' => 'debit',
            'entity_id' => $supplier->id,
        ]);
    }

    private function transactionData(
        BankTransactionStatus $status = BankTransactionStatus::Posted,
        array $rawPayload = ['operationId' => 'operation-1', 'status' => 'POSTED'],
        BankTransactionDirection $direction = BankTransactionDirection::Credit,
        string $amount = '100.00',
    ): BankTransactionData {
        return new BankTransactionData(
            operationId: 'operation-1',
            operationDate: CarbonImmutable::parse('2026-07-24'),
            postingDate: CarbonImmutable::parse('2026-07-24'),
            valueDate: null,
            direction: $direction,
            amount: $amount,
            currency: 'RUB',
            status: $status,
            documentNumber: '42',
            purpose: 'Оплата по счёту № INV-100',
            payerName: 'ООО Покупатель',
            payerInn: '7701234567',
            payerKpp: '770101001',
            payerAccount: '40702810000000000099',
            payerBankName: 'Банк',
            payerBic: '044525225',
            payerCorrAccount: '30101810400000000225',
            recipientName: 'ООО Пищепром',
            recipientInn: '7700000000',
            recipientKpp: '770001001',
            recipientAccount: '40702810000000000001',
            recipientBankName: 'Сбер',
            recipientBic: '044525225',
            recipientCorrAccount: '30101810400000000225',
            bankModifiedAt: CarbonImmutable::parse('2026-07-24 12:00:00'),
            rawPayload: $rawPayload,
        );
    }
}
