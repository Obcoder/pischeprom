<?php

namespace Tests\Feature\Banking;

use App\Domain\Banking\Enums\AllocationSource;
use App\Domain\Banking\Services\BankAccountMasker;
use App\Domain\Banking\Services\BankAuditLogger;
use App\Domain\Banking\Services\PaymentAllocationService;
use App\Jobs\Banking\SyncSberStatementsJob;
use App\Models\Purchase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

class BankingHttpFeatureTest extends BankingDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Event::fake();
    }

    public function test_bank_page_requires_authentication_and_bank_view_permission(): void
    {
        $this->get('/Ameise/bank')->assertRedirect('/login');

        $user = $this->createUser();
        $this->actingAs($user)->get('/Ameise/bank')->assertForbidden();

        $this->grantBankPermissions($user, 'bank.view');
        $this->actingAs($user)
            ->get('/Ameise/bank')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Ameise/Bank/Index')
                ->where('permissions.view', true)
                ->where('readOnly', true));

        config(['banking.sber.read_only' => false]);
        $this->actingAs($user)
            ->get('/Ameise/bank')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('readOnly', false));
    }

    public function test_connection_management_is_admin_only_even_if_permission_is_assigned_directly(): void
    {
        $manager = $this->createUser();
        $this->grantBankPermissions($manager, 'bank.view', 'bank.manage_connection');
        $this->actingAs($manager)
            ->getJson('/Ameise/bank/sber/health')
            ->assertForbidden();

        $admin = $this->createUser();
        $role = Role::query()->create(['name' => 'admin', 'guard_name' => 'crm']);
        $admin->assignRole($role);
        $this->flushSession();
        $this->actingAs($admin)
            ->getJson('/Ameise/bank/sber/health')
            ->assertOk()
            ->assertJsonStructure(['data' => ['status', 'checks']]);
    }

    public function test_account_and_counterparty_numbers_are_masked_without_sensitive_permission(): void
    {
        $user = $this->createUser();
        $this->grantBankPermissions($user, 'bank.view');
        $account = $this->createAccount($this->createConnection());
        $transaction = $this->createTransaction($account);

        $this->actingAs($user)
            ->getJson('/Ameise/bank/transactions')
            ->assertOk()
            ->assertJsonPath('data.0.account.number', $account->masked_number)
            ->assertJsonPath(
                'data.0.payer_account',
                BankAccountMasker::mask($transaction->payer_account)
            )
            ->assertJsonMissing(['payer_account' => $transaction->payer_account]);

        $this->grantBankPermissions($user, 'bank.view_sensitive');
        $this->actingAs($user)
            ->getJson('/Ameise/bank/transactions')
            ->assertOk()
            ->assertJsonPath('data.0.account.number', $account->account_number)
            ->assertJsonPath('data.0.payer_account', $transaction->payer_account);
    }

    public function test_sensitive_draft_data_requires_both_draft_and_sensitive_permissions(): void
    {
        $user = $this->createUser();
        $this->grantBankPermissions($user, 'bank.view', 'bank.manage_payment_drafts');
        $entity = $this->createEntity();
        $account = $this->createAccount(
            $this->createConnection(['owner_entity_id' => $entity->id])
        );

        $this->assertArrayNotHasKey('bank_account_number', $entity->toArray());

        $this->actingAs($user)
            ->getJson('/Ameise/bank/drafts')
            ->assertForbidden();
        $this->actingAs($user)
            ->getJson('/Ameise/bank/drafts/options')
            ->assertForbidden();

        $this->grantBankPermissions($user, 'bank.view_sensitive');
        $this->actingAs($user)
            ->getJson('/Ameise/bank/drafts')
            ->assertOk();
        $this->actingAs($user)
            ->getJson('/Ameise/bank/drafts/options')
            ->assertOk()
            ->assertJsonPath('data.accounts.0.number', $account->account_number)
            ->assertJsonPath(
                'data.entities.0.bank_account_number',
                $entity->bank_account_number
            );
    }

    public function test_transaction_audit_requires_audit_permission(): void
    {
        $user = $this->createUser();
        $this->grantBankPermissions($user, 'bank.view');
        $transaction = $this->createTransaction(
            $this->createAccount($this->createConnection())
        );
        app(BankAuditLogger::class)->record('bank.transaction.tested', $transaction);

        $this->actingAs($user)
            ->getJson("/Ameise/bank/transactions/{$transaction->id}")
            ->assertOk()
            ->assertJsonPath('data.audit', []);

        $this->grantBankPermissions($user, 'bank.view_audit');
        $this->actingAs($user)
            ->getJson("/Ameise/bank/transactions/{$transaction->id}")
            ->assertOk()
            ->assertJsonPath('data.audit.0.action', 'bank.transaction.tested');
    }

    public function test_draft_options_include_a_requested_purchase_and_its_supplier_first(): void
    {
        $user = $this->createUser();
        $this->grantBankPermissions(
            $user,
            'bank.view',
            'bank.view_sensitive',
            'bank.manage_payment_drafts'
        );
        $this->createEntity(['name' => 'Альфа']);
        $supplier = $this->createEntity([
            'name' => 'Янтарь',
            'INN' => '7812345678',
        ]);
        Purchase::query()->create([
            'date' => '2025-01-01',
            'entity_id' => $supplier->id,
            'amount' => '735.40',
        ]);
        $purchase = Purchase::query()->create([
            'date' => '2024-01-01',
            'entity_id' => $supplier->id,
            'amount' => '1250.00',
        ]);

        $this->actingAs($user)
            ->getJson("/Ameise/bank/drafts/options?purchase_id={$purchase->id}")
            ->assertOk()
            ->assertJsonPath('data.purchases.0.id', $purchase->id)
            ->assertJsonPath('data.entities.0.id', $supplier->id)
            ->assertJsonPath('data.entities.0.INN', $supplier->INN);
    }

    public function test_linked_and_partial_worklists_include_a_fully_allocated_partial_payment(): void
    {
        $user = $this->createUser();
        $this->grantBankPermissions($user, 'bank.view');
        $sale = $this->createSale($this->createEntity(), '100.00');
        $transaction = $this->createTransaction(
            $this->createAccount($this->createConnection()),
            '40.00'
        );
        app(PaymentAllocationService::class)->allocate(
            $transaction,
            $sale,
            '40.00',
            AllocationSource::Manual,
            $user,
        );

        $this->assertSame('allocated', $transaction->fresh()->reconciliation_status->value);
        $this->assertSame('partially_paid', $sale->fresh()->payment_status);

        foreach (['linked', 'partial_overpaid'] as $worklist) {
            $this->actingAs($user)
                ->getJson("/Ameise/bank/transactions?worklist={$worklist}")
                ->assertOk()
                ->assertJsonPath('data.0.id', $transaction->id);
        }
    }

    public function test_manual_allocation_endpoint_is_atomic_and_rejects_double_distribution(): void
    {
        $user = $this->createUser();
        $this->grantBankPermissions($user, 'bank.view', 'bank.reconcile');
        $entity = $this->createEntity();
        $firstSale = $this->createSale($entity, '100.00', ['payment_reference' => 'INV-A']);
        $secondSale = $this->createSale($entity, '100.00', ['payment_reference' => 'INV-B']);
        $account = $this->createAccount($this->createConnection());
        $transaction = $this->createTransaction($account);

        $this->actingAs($user)
            ->postJson("/Ameise/bank/transactions/{$transaction->id}/allocations", [
                'allocations' => [
                    ['sale_id' => $firstSale->id, 'amount' => '100.00'],
                ],
                'comment' => 'Проверено менеджером',
            ])
            ->assertCreated();

        $this->actingAs($user)
            ->postJson("/Ameise/bank/transactions/{$transaction->id}/allocations", [
                'allocations' => [
                    ['sale_id' => $secondSale->id, 'amount' => '1.00'],
                ],
            ])
            ->assertStatus(409)
            ->assertJsonPath('category', 'reconciliation_conflict');
        $this->assertDatabaseCount('bank_transaction_allocations', 1);
    }

    public function test_local_payment_draft_is_created_without_any_bank_http_request(): void
    {
        Http::preventStrayRequests();
        $user = $this->createUser();
        $this->grantBankPermissions(
            $user,
            'bank.view',
            'bank.view_sensitive',
            'bank.manage_payment_drafts'
        );
        $owner = $this->createEntity([
            'name' => 'ООО Пищепром',
            'full_name' => 'Общество с ограниченной ответственностью Пищепром',
            'INN' => '7700000000',
            'KPP' => '770001001',
            'bank_account_number' => '40702810000000000001',
        ]);
        $recipient = $this->createEntity([
            'name' => 'ООО Поставщик',
            'full_name' => 'Общество с ограниченной ответственностью Поставщик',
            'INN' => '7812345678',
            'KPP' => '781201001',
            'bank_account_number' => '40702810000000000002',
            'bank_name' => 'Банк поставщика',
            'bank_bic' => '044525999',
            'bank_corr_account' => '30101810000000000999',
        ]);
        $connection = $this->createConnection(['owner_entity_id' => $owner->id]);
        $account = $this->createAccount($connection, [
            'normalized_requisites' => [
                'bank_name' => 'Сбер',
                'bic' => '044525225',
                'corr_account' => '30101810400000000225',
            ],
        ]);

        $this->actingAs($user)
            ->postJson('/Ameise/bank/drafts', [
                'number' => 'LOCAL-1',
                'document_date' => '2026-07-24',
                'payer_bank_account_id' => $account->id,
                'recipient_entity_id' => $recipient->id,
                'purchase_id' => null,
                'amount' => '1250.40',
                'currency' => 'RUB',
                'payer_name' => $owner->full_name,
                'payer_inn' => $owner->INN,
                'payer_kpp' => $owner->KPP,
                'payer_account' => $account->account_number,
                'payer_bank_name' => 'Сбер',
                'payer_bic' => '044525225',
                'payer_corr_account' => '30101810400000000225',
                'recipient_name' => $recipient->full_name,
                'recipient_inn' => $recipient->INN,
                'recipient_kpp' => $recipient->KPP,
                'recipient_account' => $recipient->bank_account_number,
                'recipient_bank_name' => $recipient->bank_name,
                'recipient_bic' => $recipient->bank_bic,
                'recipient_corr_account' => $recipient->bank_corr_account,
                'purpose' => 'Оплата сырья по договору № 12',
                'vat_type' => 'without_vat',
                'vat_rate' => null,
                'vat_amount' => null,
                'payment_priority' => 5,
                'budget_fields' => null,
            ])
            ->assertCreated()
            ->assertJsonPath('data.status', 'draft');

        Http::assertNothingSent();
        $this->assertDatabaseHas('bank_payment_order_drafts', [
            'number' => 'LOCAL-1',
            'status' => 'draft',
            'amount' => 1250.40,
        ]);
        $this->assertDatabaseHas('bank_audit_events', [
            'action' => 'bank.payment_draft.created',
        ]);
    }

    public function test_manual_sync_endpoint_only_queues_work(): void
    {
        Queue::fake();
        $user = $this->createUser();
        $this->grantBankPermissions($user, 'bank.view', 'bank.sync');
        $connection = $this->createConnection();

        $this->actingAs($user)
            ->postJson('/Ameise/bank/sync', [
                'connection_id' => $connection->id,
                'mode' => 'incremental',
            ])
            ->assertStatus(202)
            ->assertJsonPath('status', 'queued');

        Queue::assertPushed(
            SyncSberStatementsJob::class,
            fn (SyncSberStatementsJob $job): bool => $job->connectionId === $connection->id
        );
    }

    public function test_manual_sync_endpoint_rejects_an_oversized_date_range(): void
    {
        Queue::fake();
        $user = $this->createUser();
        $this->grantBankPermissions($user, 'bank.view', 'bank.sync');
        $connection = $this->createConnection();

        $this->actingAs($user)
            ->postJson('/Ameise/bank/sync', [
                'connection_id' => $connection->id,
                'mode' => 'manual',
                'from' => '2024-01-01',
                'to' => '2026-01-01',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('to');

        Queue::assertNothingPushed();
    }

    public function test_no_route_can_send_sign_or_execute_a_payment(): void
    {
        $bankUris = collect(Route::getRoutes())
            ->map(fn ($route): string => mb_strtolower($route->uri()))
            ->filter(fn (string $uri): bool => str_contains($uri, 'bank'))
            ->values();

        foreach ($bankUris as $uri) {
            $this->assertStringNotContainsString('/payments', $uri);
            $this->assertStringNotContainsString('/send', $uri);
            $this->assertStringNotContainsString('/sign', $uri);
            $this->assertStringNotContainsString('/execute', $uri);
        }
    }
}
