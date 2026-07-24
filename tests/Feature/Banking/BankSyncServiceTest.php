<?php

namespace Tests\Feature\Banking;

use App\Domain\Banking\Contracts\BankProviderInterface;
use App\Domain\Banking\DTO\BankBalanceData;
use App\Domain\Banking\DTO\BankStatementData;
use App\Domain\Banking\Services\BankAccountSynchronizer;
use App\Domain\Banking\Services\BankSyncService;
use App\Models\BankAccountBalanceSnapshot;
use Carbon\CarbonImmutable;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Mockery;

class BankSyncServiceTest extends BankingDatabaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('bank_account_balance_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('bank_account_id');
            $table->string('type');
            $table->decimal('amount', 20, 2);
            $table->char('currency', 3);
            $table->date('statement_date')->nullable();
            $table->timestamp('as_of');
            $table->string('source');
            $table->timestamps();
        });
        Schema::create('bank_sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('bank_connection_id');
            $table->unsignedBigInteger('bank_account_id')->nullable();
            $table->string('type');
            $table->date('period_from')->nullable();
            $table->date('period_to')->nullable();
            $table->string('status');
            $table->string('cursor')->nullable();
            $table->unsignedInteger('received_count')->default(0);
            $table->unsignedInteger('created_count')->default(0);
            $table->unsignedInteger('updated_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->unsignedInteger('matched_count')->default(0);
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->uuid('correlation_id');
            $table->string('safe_error_message')->nullable();
            $table->timestamps();
        });
    }

    public function test_daily_sync_persists_the_documented_balance_summary_snapshot(): void
    {
        $connection = $this->createConnection();
        $account = $this->createAccount($connection);
        $bankDay = CarbonImmutable::parse('2026-07-24', 'Europe/Moscow');
        $provider = Mockery::mock(BankProviderInterface::class);
        $provider->shouldReceive('getDailyStatement')
            ->once()
            ->withArgs(fn ($actualConnection, $actualAccount, $date): bool => $actualConnection->is($connection)
                && $actualAccount->is($account)
                && $date->format('Y-m-d') === '2026-07-24')
            ->andReturn(new BankStatementData(
                transactions: collect(),
                balances: collect(),
                cursor: null,
            ));
        $provider->shouldReceive('getDailyBalance')
            ->once()
            ->withArgs(fn ($actualConnection, $actualAccount, $date): bool => $actualConnection->is($connection)
                && $actualAccount->is($account)
                && $date->format('Y-m-d') === '2026-07-24')
            ->andReturn(new BankBalanceData(
                type: 'closing',
                amount: '123456.78',
                currency: 'RUB',
                statementDate: $bankDay,
                asOf: CarbonImmutable::parse('2026-07-24 18:30:00', 'Europe/Moscow'),
                source: 'statement.summary',
            ));
        $this->app->instance(BankProviderInterface::class, $provider);

        $runs = app(BankSyncService::class)->syncRange(
            $connection,
            $bankDay,
            $bankDay,
        );

        $this->assertCount(1, $runs);
        $this->assertSame('succeeded', $runs[0]->status->value);
        $this->assertSame('123456.78', $account->fresh()->current_balance);
        $this->assertSame('2026-07-24', $account->fresh()->balance_statement_date->format('Y-m-d'));
        $snapshot = BankAccountBalanceSnapshot::query()->sole();
        $this->assertSame($account->id, $snapshot->bank_account_id);
        $this->assertSame('closing', $snapshot->type);
        $this->assertSame('123456.78', $snapshot->amount);
        $this->assertSame('RUB', $snapshot->currency);
        $this->assertSame('2026-07-24', $snapshot->statement_date->format('Y-m-d'));
        $this->assertSame('statement.summary', $snapshot->source);
    }

    public function test_historical_snapshot_does_not_replace_a_newer_last_known_balance(): void
    {
        $account = $this->createAccount($this->createConnection());
        $synchronizer = new BankAccountSynchronizer(
            Mockery::mock(BankProviderInterface::class)
        );
        $newer = new BankBalanceData(
            type: 'closing',
            amount: '500.00',
            currency: 'RUB',
            statementDate: CarbonImmutable::parse('2026-07-24', 'Europe/Moscow'),
            asOf: CarbonImmutable::parse('2026-07-24 18:00:00', 'Europe/Moscow'),
            source: 'statement.summary',
        );
        $older = new BankBalanceData(
            type: 'closing',
            amount: '100.00',
            currency: 'RUB',
            statementDate: CarbonImmutable::parse('2026-06-01', 'Europe/Moscow'),
            asOf: CarbonImmutable::parse('2026-07-25 09:00:00', 'Europe/Moscow'),
            source: 'statement.summary',
        );

        $synchronizer->storeBalances($account, collect([$newer]));
        $synchronizer->storeBalances($account, collect([$older]));

        $account->refresh();
        $this->assertSame('500.00', $account->current_balance);
        $this->assertSame('2026-07-24', $account->balance_statement_date->toDateString());
        $this->assertCount(2, $account->balanceSnapshots);
    }
}
