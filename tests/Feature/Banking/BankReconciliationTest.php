<?php

namespace Tests\Feature\Banking;

use App\Domain\Banking\Enums\AllocationSource;
use App\Domain\Banking\Exceptions\ReconciliationConflictException;
use App\Domain\Banking\Reconciliation\BankReconciliationService;
use App\Domain\Banking\Reconciliation\BankTransactionMatcher;
use App\Domain\Banking\Services\ManualBankReconciliationService;
use App\Domain\Banking\Services\PaymentAllocationService;
use App\Models\Sale;
use Illuminate\Support\Facades\Event;

class BankReconciliationTest extends BankingDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
    }

    public function test_exact_reference_inn_and_compatible_amount_are_auto_matched(): void
    {
        $entity = $this->createEntity();
        $sale = $this->createSale($entity);
        $account = $this->createAccount($this->createConnection());
        $transaction = $this->createTransaction($account);

        $service = app(BankReconciliationService::class);
        $service->reconcile($transaction);
        $service->reconcile($transaction);

        $this->assertDatabaseHas('bank_transaction_allocations', [
            'bank_transaction_id' => $transaction->id,
            'allocatable_id' => $sale->id,
            'amount' => 100,
            'source' => 'automatic',
            'is_active' => 1,
        ]);
        $this->assertSame('paid', $sale->fresh()->payment_status);
        $this->assertSame('100.00', $sale->fresh()->paid_amount);
        $this->assertSame('allocated', $transaction->fresh()->reconciliation_status->value);
        $this->assertDatabaseCount('bank_transaction_allocations', 1);
    }

    public function test_same_amount_alone_never_creates_an_automatic_match(): void
    {
        $entity = $this->createEntity();
        $this->createSale($entity);
        $account = $this->createAccount($this->createConnection());
        $transaction = $this->createTransaction($account, '100.00', [
            'purpose' => 'Оплата за товар по договору',
            'payer_inn' => null,
            'payer_account' => null,
        ]);

        $result = app(BankTransactionMatcher::class)->match($transaction);

        $this->assertNull($result->automaticCandidate);
        $this->assertCount(0, $result->candidates);
    }

    public function test_ambiguous_entity_candidates_are_suggestions_only(): void
    {
        $entity = $this->createEntity();
        $this->createSale($entity, '100.00', ['payment_reference' => 'INV-101']);
        $this->createSale($entity, '100.00', ['payment_reference' => 'INV-102']);
        $account = $this->createAccount($this->createConnection());
        $transaction = $this->createTransaction($account, '100.00', [
            'purpose' => 'Оплата за товар',
        ]);

        app(BankReconciliationService::class)->reconcile($transaction);

        $this->assertDatabaseCount('bank_transaction_allocations', 0);
        $this->assertDatabaseCount('bank_match_suggestions', 2);
        $this->assertSame('suggested', $transaction->fresh()->reconciliation_status->value);
        $this->assertSame('ambiguous_match', $transaction->fresh()->review_reason);
    }

    public function test_rejected_suggestion_is_not_recreated_by_the_same_algorithm(): void
    {
        $user = $this->createUser();
        $entity = $this->createEntity();
        $sale = $this->createSale($entity, '100.00', ['payment_reference' => 'INV-200']);
        $transaction = $this->createTransaction(
            $this->createAccount($this->createConnection()),
            '100.00',
            [
                'purpose' => 'Оплата по счёту № INV-200',
                'payer_inn' => null,
                'payer_account' => null,
            ],
        );
        $reconciliation = app(BankReconciliationService::class);
        $reconciliation->reconcile($transaction);
        $suggestion = $transaction->suggestions()->sole();

        app(ManualBankReconciliationService::class)->rejectSuggestion(
            $transaction,
            $suggestion,
            $user,
            'Не тот счёт',
        );
        $reconciliation->reconcile($transaction);

        $this->assertSame('rejected', $suggestion->fresh()->status->value);
        $this->assertSame(
            0,
            $transaction->suggestions()->where('status', 'pending')->count(),
        );
        $this->assertSame('unmatched', $transaction->fresh()->reconciliation_status->value);
        $this->assertSame($sale->id, $suggestion->suggestable_id);
    }

    public function test_partial_payment_recalculates_sale_atomically(): void
    {
        $entity = $this->createEntity();
        $sale = $this->createSale($entity, '100.00');
        $account = $this->createAccount($this->createConnection());
        $transaction = $this->createTransaction($account, '40.00');

        app(BankReconciliationService::class)->reconcile($transaction);

        $sale->refresh();
        $this->assertSame('partially_paid', $sale->payment_status);
        $this->assertSame('40.00', $sale->paid_amount);
        $this->assertSame('60.00', $sale->outstanding_amount);
        $this->assertSame('allocated', $transaction->fresh()->reconciliation_status->value);
    }

    public function test_new_sale_starts_with_its_full_outstanding_amount(): void
    {
        $entity = $this->createEntity();
        $sale = Sale::query()->create([
            'date' => '2026-07-24',
            'entity_id' => $entity->id,
            'total' => '725.40',
        ]);

        $this->assertSame('unpaid', $sale->payment_status);
        $this->assertSame('0.00', $sale->paid_amount);
        $this->assertSame('725.40', $sale->outstanding_amount);
        $this->assertSame('0.00', $sale->overpaid_amount);
    }

    public function test_overpayment_pays_sale_and_leaves_excess_on_transaction_for_review(): void
    {
        $entity = $this->createEntity();
        $sale = $this->createSale($entity, '100.00');
        $account = $this->createAccount($this->createConnection());
        $transaction = $this->createTransaction($account, '125.00');

        app(BankReconciliationService::class)->reconcile($transaction);

        $sale->refresh();
        $transaction->refresh();
        $this->assertSame('paid', $sale->payment_status);
        $this->assertSame('100.00', $sale->paid_amount);
        $this->assertSame('0.00', $sale->outstanding_amount);
        $this->assertSame('overpaid', $transaction->reconciliation_status->value);
        $this->assertSame('overpayment', $transaction->review_reason);
        $this->assertSame(
            '25.00',
            app(PaymentAllocationService::class)->transactionUnallocatedAmount($transaction)
        );
    }

    public function test_manual_allocation_marks_a_settled_sale_remainder_as_overpayment(): void
    {
        $user = $this->createUser();
        $sale = $this->createSale($this->createEntity(), '100.00');
        $transaction = $this->createTransaction(
            $this->createAccount($this->createConnection()),
            '125.00'
        );

        app(ManualBankReconciliationService::class)->allocate(
            $transaction,
            [['sale_id' => $sale->id, 'amount' => '100.00']],
            $user,
            'Проверено вручную'
        );

        $this->assertSame('paid', $sale->fresh()->payment_status);
        $this->assertSame('overpaid', $transaction->fresh()->reconciliation_status->value);
        $this->assertSame('overpayment', $transaction->fresh()->review_reason);
        $this->assertSame(
            '25.00',
            app(PaymentAllocationService::class)->transactionUnallocatedAmount($transaction)
        );
    }

    public function test_multiple_payments_can_pay_one_sale_and_one_payment_can_be_split(): void
    {
        $user = $this->createUser();
        $entity = $this->createEntity();
        $firstSale = $this->createSale($entity, '100.00', ['payment_reference' => 'INV-A']);
        $secondSale = $this->createSale($entity, '30.00', ['payment_reference' => 'INV-B']);
        $account = $this->createAccount($this->createConnection());
        $firstPayment = $this->createTransaction($account, '40.00');
        $secondPayment = $this->createTransaction($account, '90.00');
        $allocations = app(PaymentAllocationService::class);

        $allocations->allocate($firstPayment, $firstSale, '40.00', AllocationSource::Manual, $user);
        $allocations->allocate($secondPayment, $firstSale, '60.00', AllocationSource::Manual, $user);
        $allocations->allocate($secondPayment, $secondSale, '30.00', AllocationSource::Manual, $user);

        $this->assertSame('paid', $firstSale->fresh()->payment_status);
        $this->assertSame('paid', $secondSale->fresh()->payment_status);
        $this->assertSame('allocated', $secondPayment->fresh()->reconciliation_status->value);
        $this->assertDatabaseCount('bank_transaction_allocations', 3);
    }

    public function test_transaction_amount_cannot_be_allocated_twice(): void
    {
        $user = $this->createUser();
        $entity = $this->createEntity();
        $firstSale = $this->createSale($entity, '100.00', ['payment_reference' => 'INV-A']);
        $secondSale = $this->createSale($entity, '100.00', ['payment_reference' => 'INV-B']);
        $account = $this->createAccount($this->createConnection());
        $transaction = $this->createTransaction($account, '100.00');
        $service = app(PaymentAllocationService::class);
        $service->allocate($transaction, $firstSale, '100.00', AllocationSource::Manual, $user);

        $this->expectException(ReconciliationConflictException::class);

        $service->allocate($transaction, $secondSale, '1.00', AllocationSource::Manual, $user);
    }

    public function test_transaction_marked_not_required_cannot_be_allocated(): void
    {
        $entity = $this->createEntity();
        $sale = $this->createSale($entity);
        $transaction = $this->createTransaction(
            $this->createAccount($this->createConnection()),
            '100.00',
            [
                'no_reconciliation_required' => true,
                'reconciliation_status' => 'not_required',
            ]
        );

        $this->expectException(ReconciliationConflictException::class);

        app(PaymentAllocationService::class)->allocate(
            $transaction,
            $sale,
            '100.00',
            AllocationSource::Manual,
        );
    }

    public function test_reversing_allocation_restores_sale_and_transaction_status(): void
    {
        $user = $this->createUser();
        $entity = $this->createEntity();
        $sale = $this->createSale($entity);
        $account = $this->createAccount($this->createConnection());
        $transaction = $this->createTransaction($account);
        $service = app(PaymentAllocationService::class);
        $allocation = $service->allocate(
            $transaction,
            $sale,
            '100.00',
            AllocationSource::Manual,
            $user,
        );

        $service->reverse($allocation, $user, 'Ошибочная сверка');
        app(BankReconciliationService::class)->reconcile($transaction);

        $this->assertSame('unpaid', $sale->fresh()->payment_status);
        $this->assertSame('0.00', $sale->fresh()->paid_amount);
        $this->assertSame('needs_review', $transaction->fresh()->reconciliation_status->value);
        $this->assertSame('allocation_reversed', $transaction->fresh()->review_reason);
        $this->assertFalse($allocation->fresh()->is_active);
        $this->assertSame(1, $transaction->allocations()->count());
    }
}
