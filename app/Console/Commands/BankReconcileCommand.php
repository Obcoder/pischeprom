<?php

namespace App\Console\Commands;

use App\Jobs\Banking\ReconcileBankTransactionsJob;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class BankReconcileCommand extends Command
{
    protected $signature = 'bank:reconcile
        {--from= : First operation date (YYYY-MM-DD)}
        {--to= : Last operation date (YYYY-MM-DD)}
        {--sync : Run in the current process instead of dispatching to the banking queue}';

    protected $description = 'Queue conservative reconciliation of imported incoming bank transactions.';

    public function handle(): int
    {
        $from = $this->option('from');
        $to = $this->option('to');

        if (($from === null) !== ($to === null)) {
            $this->error('--from and --to must be supplied together.');

            return self::INVALID;
        }

        if ($from !== null && ! $this->validRange((string) $from, (string) $to)) {
            return self::INVALID;
        }

        $job = new ReconcileBankTransactionsJob(from: $from, to: $to);

        if ($this->option('sync')) {
            dispatch_sync($job);
            $this->info('Reconciliation completed in the current process.');
        } else {
            dispatch($job);
            $this->info('Reconciliation was queued on the banking queue.');
        }

        return self::SUCCESS;
    }

    private function validRange(string $from, string $to): bool
    {
        try {
            $timezone = (string) config('banking.bank_timezone', 'Europe/Moscow');
            $fromDate = CarbonImmutable::createFromFormat('!Y-m-d', $from, $timezone);
            $toDate = CarbonImmutable::createFromFormat('!Y-m-d', $to, $timezone);
        } catch (\Throwable) {
            $this->error('Dates must use YYYY-MM-DD.');

            return false;
        }

        if (
            ! $fromDate
            || ! $toDate
            || $fromDate->format('Y-m-d') !== $from
            || $toDate->format('Y-m-d') !== $to
            || $toDate->lessThan($fromDate)
            || $fromDate->diffInDays($toDate) > 366
        ) {
            $this->error('Date range is invalid or longer than 366 days.');

            return false;
        }

        return true;
    }
}
