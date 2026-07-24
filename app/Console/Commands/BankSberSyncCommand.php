<?php

namespace App\Console\Commands;

use App\Jobs\Banking\SyncSberStatementsJob;
use App\Models\BankConnection;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class BankSberSyncCommand extends Command
{
    protected $signature = 'bank:sber:sync
        {--connection= : Bank connection ID}
        {--from= : First bank day (YYYY-MM-DD)}
        {--to= : Last bank day (YYYY-MM-DD)}
        {--incremental : Request an incremental statement}
        {--control : Reload the configured control period}
        {--sandbox-smoke : Explicitly allow a sandbox smoke synchronization}
        {--sync : Run in the current process instead of dispatching to the banking queue}';

    protected $description = 'Queue a safe read-only Sber statement synchronization.';

    public function handle(): int
    {
        if (! (bool) config('banking.enabled') || ! (bool) config('banking.sber.enabled')) {
            $this->error('Sber API is disabled; no request was made.');

            return self::FAILURE;
        }

        if (! (bool) config('banking.sber.read_only')) {
            $this->error('SBER_READ_ONLY must be true; no request was made.');

            return self::FAILURE;
        }

        $connectionId = filter_var($this->option('connection'), FILTER_VALIDATE_INT);

        if ($connectionId === false || $connectionId < 1) {
            $this->error('--connection must be a positive connection ID.');

            return self::INVALID;
        }

        $connection = BankConnection::query()->find($connectionId);

        if (! $connection || $connection->provider->value !== 'sber') {
            $this->error('Sber connection was not found.');

            return self::FAILURE;
        }

        if ($this->option('sandbox-smoke') && $connection->environment->value !== 'sandbox') {
            $this->error('--sandbox-smoke is permitted only for a sandbox connection.');

            return self::INVALID;
        }

        if ($connection->environment->value === 'sandbox' && ! $this->option('sandbox-smoke')) {
            $this->error('A sandbox synchronization requires the explicit --sandbox-smoke flag.');

            return self::INVALID;
        }

        [$mode, $from, $to] = $this->resolveMode();

        if ($mode === null) {
            return self::INVALID;
        }

        $job = new SyncSberStatementsJob($connection->id, $mode, $from, $to);

        if ($this->option('sync')) {
            dispatch_sync($job);
            $this->info('Read-only synchronization completed in the current process.');
        } else {
            dispatch($job);
            $this->info('Read-only synchronization was queued on the banking queue.');
        }

        return self::SUCCESS;
    }

    private function resolveMode(): array
    {
        if ($this->option('incremental')) {
            if ($this->option('from') || $this->option('to') || $this->option('control')) {
                $this->error('--incremental cannot be combined with date range or --control.');

                return [null, null, null];
            }

            return ['incremental', null, null];
        }

        if ($this->option('control')) {
            if ($this->option('from') || $this->option('to')) {
                $this->error('--control cannot be combined with a date range.');

                return [null, null, null];
            }

            return ['control', null, null];
        }

        $from = $this->option('from');
        $to = $this->option('to');

        if ($from === null && $to === null) {
            return ['initial', null, null];
        }

        if (! is_string($from) || ! is_string($to)) {
            $this->error('--from and --to must be supplied together.');

            return [null, null, null];
        }

        try {
            $timezone = (string) config('banking.bank_timezone', 'Europe/Moscow');
            $fromDate = CarbonImmutable::createFromFormat('!Y-m-d', $from, $timezone);
            $toDate = CarbonImmutable::createFromFormat('!Y-m-d', $to, $timezone);
        } catch (\Throwable) {
            $this->error('Dates must use YYYY-MM-DD.');

            return [null, null, null];
        }

        if (
            ! $fromDate
            || ! $toDate
            || $fromDate->format('Y-m-d') !== $from
            || $toDate->format('Y-m-d') !== $to
            || $toDate->lessThan($fromDate)
            || $fromDate->diffInDays($toDate) > 366
            || $toDate->isFuture()
        ) {
            $this->error('Date range is invalid, in the future, or longer than 366 days.');

            return [null, null, null];
        }

        return ['manual', $from, $to];
    }
}
