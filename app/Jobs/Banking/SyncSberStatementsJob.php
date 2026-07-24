<?php

namespace App\Jobs\Banking;

use App\Domain\Banking\Enums\BankSyncType;
use App\Domain\Banking\Exceptions\BankingException;
use App\Domain\Banking\Services\BankSyncService;
use App\Models\BankConnection;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class SyncSberStatementsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 5;

    public int $uniqueFor = 1800;

    public function __construct(
        public readonly int $connectionId,
        public readonly string $mode = 'incremental',
        public readonly ?string $from = null,
        public readonly ?string $to = null,
        public readonly ?int $accountId = null,
    ) {
        $this->onConnection((string) config('banking.queue_connection', 'redis'));
        $this->onQueue((string) config('banking.queue', 'banking'));
    }

    public function uniqueId(): string
    {
        return implode(':', [
            'sber-statements',
            $this->connectionId,
            $this->accountId ?? 'all',
            $this->mode,
            $this->from ?? '-',
            $this->to ?? '-',
        ]);
    }

    public function uniqueVia(): Repository
    {
        return Cache::store((string) config('banking.lock_store', 'redis'));
    }

    public function backoff(): array
    {
        return [60 + random_int(0, 20), 300 + random_int(0, 60), 900 + random_int(0, 120), 1800];
    }

    public function handle(BankSyncService $sync): void
    {
        $connection = BankConnection::query()->with('accounts')->findOrFail($this->connectionId);

        try {
            if ($this->mode === 'incremental') {
                $accounts = $connection->accounts
                    ->where('status', 'active')
                    ->when($this->accountId, fn ($items) => $items->where('id', $this->accountId));

                foreach ($accounts as $account) {
                    $sync->syncIncremental($connection, $account, $this->attempts());
                }

                return;
            }

            [$from, $to, $type] = $this->range();
            $sync->syncRange(
                $connection,
                $from,
                $to,
                $type,
                $this->attempts()
            );
        } catch (BankingException $exception) {
            if ($exception->retryAfterSeconds !== null) {
                $this->release($exception->retryAfterSeconds + random_int(0, 10));

                return;
            }

            if (! $exception->retryable) {
                $this->fail($exception);

                return;
            }

            throw $exception;
        }
    }

    private function range(): array
    {
        $timezone = (string) config('banking.bank_timezone', 'Europe/Moscow');
        $today = CarbonImmutable::now($timezone)->startOfDay();

        return match ($this->mode) {
            'initial' => [
                $today->subDays(max(1, (int) config('banking.sber.initial_import_days', 90)) - 1),
                $today,
                BankSyncType::Initial,
            ],
            'control' => [
                $today->subDays(max(0, (int) config('banking.sber.control_sync_days', 3))),
                $today,
                BankSyncType::Control,
            ],
            'manual' => [
                CarbonImmutable::parse((string) $this->from, $timezone)->startOfDay(),
                CarbonImmutable::parse((string) $this->to, $timezone)->startOfDay(),
                BankSyncType::Manual,
            ],
            default => throw new \InvalidArgumentException('Unknown Sber statement synchronization mode.'),
        };
    }
}
