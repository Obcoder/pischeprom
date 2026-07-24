<?php

namespace App\Jobs\Banking;

use App\Domain\Banking\Reconciliation\BankReconciliationService;
use App\Models\BankSyncRun;
use App\Models\BankTransaction;
use Carbon\CarbonImmutable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;

class ReconcileBankTransactionsJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 4;

    public int $uniqueFor = 900;

    /**
     * @param  array<int, int>  $transactionIds
     */
    public function __construct(
        public readonly array $transactionIds = [],
        public readonly ?string $from = null,
        public readonly ?string $to = null,
        public readonly ?int $syncRunId = null,
    ) {
        $this->onConnection((string) config('banking.queue_connection', 'redis'));
        $this->onQueue((string) config('banking.queue', 'banking'));
    }

    public function uniqueId(): string
    {
        $ids = $this->transactionIds;
        sort($ids);

        return 'bank-reconcile:'.hash('sha256', json_encode([
            $ids,
            $this->from,
            $this->to,
            $this->syncRunId,
        ], JSON_THROW_ON_ERROR));
    }

    public function uniqueVia(): Repository
    {
        return Cache::store((string) config('banking.lock_store', 'redis'));
    }

    public function backoff(): array
    {
        return [15 + random_int(0, 10), 60 + random_int(0, 20), 300];
    }

    public function handle(BankReconciliationService $reconciliation): void
    {
        $matched = 0;
        $query = BankTransaction::query()
            ->credits()
            ->posted()
            ->where('no_reconciliation_required', false);

        if ($this->transactionIds !== []) {
            $query->whereIn('id', $this->transactionIds);
        } elseif ($this->from && $this->to) {
            $timezone = (string) config('banking.bank_timezone', 'Europe/Moscow');
            $from = CarbonImmutable::parse($this->from, $timezone)->toDateString();
            $to = CarbonImmutable::parse($this->to, $timezone)->toDateString();
            $query->whereBetween('operation_date', [$from, $to]);
        } else {
            $query->whereIn('reconciliation_status', ['unmatched', 'suggested', 'needs_review']);
        }

        $query->orderBy('id')->chunkById(100, function ($transactions) use ($reconciliation, &$matched): void {
            foreach ($transactions as $transaction) {
                $before = $transaction->activeAllocations()->count();
                $result = $reconciliation->reconcile($transaction);

                if ($result->activeAllocations()->count() > $before) {
                    $matched++;
                }
            }
        });

        if ($this->syncRunId !== null && $matched > 0) {
            BankSyncRun::query()->whereKey($this->syncRunId)->increment('matched_count', $matched);
        }
    }
}
