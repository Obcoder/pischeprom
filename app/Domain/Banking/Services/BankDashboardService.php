<?php

namespace App\Domain\Banking\Services;

use App\Domain\Banking\Enums\BankTransactionDirection;
use App\Models\BankConnection;
use App\Models\BankSyncError;
use App\Models\BankSyncRun;
use App\Models\BankTransaction;
use App\Models\Sale;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class BankDashboardService
{
    public function summary(bool $canViewSensitive): array
    {
        $timezone = (string) config('banking.bank_timezone', 'Europe/Moscow');
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $connection = BankConnection::query()
            ->with(['accounts' => fn ($query) => $query->orderBy('id')])
            ->latest('id')
            ->first();

        return [
            'enabled' => (bool) config('banking.enabled'),
            'read_only' => (bool) config('banking.sber.read_only'),
            'connection' => $connection ? [
                'id' => $connection->id,
                'provider' => $connection->provider->value,
                'environment' => $connection->environment->value,
                'status' => $connection->status->value,
                'connected_at' => $connection->connected_at?->toISOString(),
                'last_successful_sync_at' => $connection->last_successful_sync_at?->toISOString(),
                'last_error_at' => $connection->last_error_at?->toISOString(),
                'access_token_expires_at' => $connection->access_token_expires_at?->toISOString(),
                'refresh_token_expires_at' => $connection->refresh_token_expires_at?->toISOString(),
                'client_secret_expires_at' => $connection->client_secret_expires_at?->toISOString(),
                'mtls_certificate_expires_at' => $connection->mtls_certificate_expires_at?->toISOString(),
                'scopes' => $connection->scopes ?? [],
            ] : null,
            'accounts' => $connection
                ? $connection->accounts->map(fn ($account): array => [
                    'id' => $account->id,
                    'name' => $account->name,
                    'number' => $canViewSensitive ? $account->account_number : $account->masked_number,
                    'masked_number' => $account->masked_number,
                    'currency' => $account->currency,
                    'status' => $account->status,
                    'balance' => $account->current_balance,
                    'balance_as_of' => $account->balance_as_of?->toISOString(),
                    'balance_statement_date' => $account->balance_statement_date?->toDateString(),
                    'last_synced_at' => $account->last_synced_at?->toISOString(),
                ])->values()
                : [],
            'totals' => [
                'credits_today' => $this->sum(BankTransactionDirection::Credit, $today),
                'credits_week' => $this->sum(BankTransactionDirection::Credit, $today->startOfWeek()),
                'credits_month' => $this->sum(BankTransactionDirection::Credit, $today->startOfMonth()),
                'debits_today' => $this->sum(BankTransactionDirection::Debit, $today),
                'debits_week' => $this->sum(BankTransactionDirection::Debit, $today->startOfWeek()),
                'debits_month' => $this->sum(BankTransactionDirection::Debit, $today->startOfMonth()),
            ],
            'counters' => [
                'unmatched' => BankTransaction::query()
                    ->credits()
                    ->posted()
                    ->where('reconciliation_status', 'unmatched')
                    ->count(),
                'suggested' => BankTransaction::query()
                    ->where('reconciliation_status', 'suggested')
                    ->count(),
                'partial' => Sale::query()->where('payment_status', 'partially_paid')->count(),
                'overpayments' => BankTransaction::query()
                    ->where('reconciliation_status', 'overpaid')
                    ->count(),
                'errors_requiring_intervention' => BankSyncError::query()
                    ->where('requires_intervention', true)
                    ->whereNull('resolved_at')
                    ->count(),
                'running_syncs' => BankSyncRun::query()
                    ->whereIn('status', ['queued', 'running'])
                    ->count(),
            ],
        ];
    }

    private function sum(BankTransactionDirection $direction, CarbonImmutable $from): string
    {
        return DecimalMoney::normalize((string) (DB::table('bank_transactions')
            ->where('direction', $direction->value)
            ->where('status', 'posted')
            ->whereDate('operation_date', '>=', $from->toDateString())
            ->selectRaw('COALESCE(SUM(amount), 0) AS aggregate')
            ->value('aggregate') ?? '0'));
    }
}
