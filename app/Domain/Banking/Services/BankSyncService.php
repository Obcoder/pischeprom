<?php

namespace App\Domain\Banking\Services;

use App\Domain\Banking\Contracts\BankProviderInterface;
use App\Domain\Banking\Enums\BankConnectionStatus;
use App\Domain\Banking\Enums\BankSyncRunStatus;
use App\Domain\Banking\Enums\BankSyncType;
use App\Domain\Banking\Events\BankSyncFailed;
use App\Domain\Banking\Exceptions\BankingException;
use App\Jobs\Banking\ReconcileBankTransactionsJob;
use App\Models\BankAccount;
use App\Models\BankConnection;
use App\Models\BankSyncRun;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class BankSyncService
{
    public function __construct(
        private readonly BankProviderInterface $provider,
        private readonly BankAccountSynchronizer $accounts,
        private readonly BankTransactionImporter $transactions,
    ) {}

    public function syncAccounts(BankConnection $connection, int $attemptCount = 1): BankSyncRun
    {
        $run = $this->newRun($connection, null, BankSyncType::Accounts);

        try {
            $result = $this->accounts->synchronize($connection);
            $run->forceFill([
                'status' => BankSyncRunStatus::Succeeded,
                'received_count' => $result['received'],
                'created_count' => $result['created'],
                'updated_count' => $result['updated'],
                'finished_at' => now(),
            ])->save();
            $connection->forceFill([
                'status' => BankConnectionStatus::Active,
                'last_successful_sync_at' => now(),
                'last_error_at' => null,
            ])->save();

            return $run->fresh();
        } catch (Throwable $exception) {
            $this->failRun($run, $connection, $exception, 'oauth.user_info', $attemptCount);
            throw $exception;
        }
    }

    /** @return array<int, BankSyncRun> */
    public function syncRange(
        BankConnection $connection,
        CarbonImmutable $from,
        CarbonImmutable $to,
        BankSyncType $type = BankSyncType::Manual,
        int $attemptCount = 1,
    ): array {
        if ($to->lessThan($from) || $from->diffInDays($to) > 366) {
            throw new \InvalidArgumentException('Bank sync date range must be ordered and no longer than 366 days.');
        }

        $runs = [];

        foreach ($connection->accounts()->where('status', 'active')->get() as $account) {
            $runs[] = $this->syncAccountRange(
                $connection,
                $account,
                $from,
                $to,
                $type,
                $attemptCount
            );
        }

        return $runs;
    }

    public function syncIncremental(
        BankConnection $connection,
        BankAccount $account,
        int $attemptCount = 1,
    ): BankSyncRun {
        $run = $this->newRun($connection, $account, BankSyncType::Incremental);
        $lock = Cache::store((string) config('banking.lock_store', 'redis'))
            ->lock("banking:sber:sync:{$connection->id}:{$account->id}", 900);

        if (! $lock->get()) {
            $run->forceFill([
                'status' => BankSyncRunStatus::Skipped,
                'safe_error_message' => 'Another synchronization is already running.',
                'finished_at' => now(),
            ])->save();

            return $run;
        }

        try {
            $cursor = $account->last_incremental_cursor_at
                ? CarbonImmutable::instance($account->last_incremental_cursor_at)
                : CarbonImmutable::now((string) config('banking.bank_timezone'))->startOfDay();
            $cursor = $cursor->subSeconds((int) config('banking.sber.incremental_overlap_seconds', 120));
            $statement = $this->provider->getIncrementalStatement($connection, $account, $cursor);
            $result = $this->transactions->import($account, $statement->transactions);
            $this->accounts->storeBalances($account, $statement->balances);

            if ($statement->reloadTime !== null) {
                $daily = $this->provider->getDailyStatement(
                    $connection,
                    $account,
                    $statement->reloadTime
                );
                $reloadResult = $this->transactions->import($account, $daily->transactions);
                $this->accounts->storeBalances($account, $daily->balances);
                $result = new \App\Domain\Banking\DTO\BankImportResult(
                    received: $result->received + $reloadResult->received,
                    created: $result->created + $reloadResult->created,
                    updated: $result->updated + $reloadResult->updated,
                    skipped: $result->skipped + $reloadResult->skipped,
                    transactionIds: array_values(array_unique([
                        ...$result->transactionIds,
                        ...$reloadResult->transactionIds,
                    ])),
                );
            }

            $balance = $this->provider->getDailyBalance(
                $connection,
                $account,
                CarbonImmutable::now((string) config('banking.bank_timezone', 'Europe/Moscow')),
            );

            if ($balance) {
                $this->accounts->storeBalances($account, collect([$balance]));
            }

            $account->forceFill([
                'last_incremental_cursor_at' => $statement->cursor ?? now(),
                'last_synced_at' => now(),
            ])->save();
            $this->succeedRun($run, $connection, $result, $statement->cursor?->toISOString());
            $this->dispatchReconciliation($result->transactionIds, $run->id);

            return $run->fresh();
        } catch (Throwable $exception) {
            $this->failRun($run, $connection, $exception, 'statement.increment', $attemptCount);
            throw $exception;
        } finally {
            $lock->release();
        }
    }

    private function syncAccountRange(
        BankConnection $connection,
        BankAccount $account,
        CarbonImmutable $from,
        CarbonImmutable $to,
        BankSyncType $type,
        int $attemptCount,
    ): BankSyncRun {
        $run = $this->newRun($connection, $account, $type, $from, $to);
        $lock = Cache::store((string) config('banking.lock_store', 'redis'))
            ->lock("banking:sber:sync:{$connection->id}:{$account->id}", 1800);

        if (! $lock->get()) {
            $run->forceFill([
                'status' => BankSyncRunStatus::Skipped,
                'safe_error_message' => 'Another synchronization is already running.',
                'finished_at' => now(),
            ])->save();

            return $run;
        }

        $received = $created = $updated = $skipped = 0;
        $transactionIds = [];

        try {
            for ($date = $from->startOfDay(); $date->lessThanOrEqualTo($to); $date = $date->addDay()) {
                $statement = $this->provider->getDailyStatement($connection, $account, $date);
                $result = $this->transactions->import($account, $statement->transactions);
                $this->accounts->storeBalances($account, $statement->balances);
                $balance = $this->provider->getDailyBalance($connection, $account, $date);

                if ($balance) {
                    $this->accounts->storeBalances($account, collect([$balance]));
                }

                $received += $result->received;
                $created += $result->created;
                $updated += $result->updated;
                $skipped += $result->skipped;
                $transactionIds = [...$transactionIds, ...$result->transactionIds];
            }

            $result = new \App\Domain\Banking\DTO\BankImportResult(
                received: $received,
                created: $created,
                updated: $updated,
                skipped: $skipped,
                transactionIds: array_values(array_unique($transactionIds)),
            );
            $account->forceFill(['last_synced_at' => now()])->save();
            $this->succeedRun($run, $connection, $result);
            $this->dispatchReconciliation($result->transactionIds, $run->id);

            return $run->fresh();
        } catch (Throwable $exception) {
            $this->failRun($run, $connection, $exception, 'statement.daily', $attemptCount);
            throw $exception;
        } finally {
            $lock->release();
        }
    }

    private function newRun(
        BankConnection $connection,
        ?BankAccount $account,
        BankSyncType $type,
        ?CarbonImmutable $from = null,
        ?CarbonImmutable $to = null,
    ): BankSyncRun {
        return BankSyncRun::query()->create([
            'bank_connection_id' => $connection->id,
            'bank_account_id' => $account?->id,
            'type' => $type,
            'period_from' => $from?->format('Y-m-d'),
            'period_to' => $to?->format('Y-m-d'),
            'status' => BankSyncRunStatus::Running,
            'started_at' => now(),
            'correlation_id' => (string) Str::uuid(),
        ]);
    }

    private function succeedRun(
        BankSyncRun $run,
        BankConnection $connection,
        \App\Domain\Banking\DTO\BankImportResult $result,
        ?string $cursor = null,
    ): void {
        $run->forceFill([
            'status' => BankSyncRunStatus::Succeeded,
            'cursor' => $cursor,
            'received_count' => $result->received,
            'created_count' => $result->created,
            'updated_count' => $result->updated,
            'skipped_count' => $result->skipped,
            'finished_at' => now(),
        ])->save();
        $connection->forceFill([
            'status' => BankConnectionStatus::Active,
            'last_successful_sync_at' => now(),
            'last_error_at' => null,
        ])->save();
    }

    private function failRun(
        BankSyncRun $run,
        BankConnection $connection,
        Throwable $exception,
        string $endpointAlias,
        int $attemptCount,
    ): void {
        $bankException = $exception instanceof BankingException ? $exception : null;
        $safeMessage = $bankException?->getMessage() ?? 'Unexpected banking synchronization error.';
        $run->forceFill([
            'status' => BankSyncRunStatus::Failed,
            'safe_error_message' => mb_substr($safeMessage, 0, 1024),
            'finished_at' => now(),
        ])->save();
        $run->errors()->create([
            'category' => $bankException?->category ?? 'unexpected',
            'http_status' => $bankException?->httpStatus,
            'bank_cause' => $bankException?->bankCause,
            'safe_message' => mb_substr($safeMessage, 0, 1024),
            'endpoint_alias' => $bankException?->endpointAlias ?? $endpointAlias,
            'attempt_count' => min(65535, max(1, $attemptCount)),
            'requires_intervention' => ! ($bankException?->retryable ?? false),
        ]);
        $connection->forceFill([
            'status' => $bankException?->category === 'authentication'
                ? BankConnectionStatus::ReauthorizationRequired
                : BankConnectionStatus::Error,
            'last_error_at' => now(),
        ])->save();
        Log::channel('banking')->error('Bank synchronization failed.', [
            'sync_run_id' => $run->id,
            'correlation_id' => $run->correlation_id,
            'category' => $bankException?->category ?? 'unexpected',
            'exception' => $exception::class,
            'endpoint_alias' => $endpointAlias,
        ]);
        BankSyncFailed::dispatch($run->fresh());
    }

    private function dispatchReconciliation(array $transactionIds, int $syncRunId): void
    {
        if ($transactionIds !== []) {
            ReconcileBankTransactionsJob::dispatch(
                transactionIds: $transactionIds,
                syncRunId: $syncRunId,
            )
                ->onQueue((string) config('banking.queue', 'banking'));
        }
    }
}
