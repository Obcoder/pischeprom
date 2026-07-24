<?php

namespace App\Console\Commands;

use App\Domain\Banking\Services\SberHealthService;
use Illuminate\Console\Command;

class BankSberHealthCommand extends Command
{
    protected $signature = 'bank:sber:health
        {--if-enabled : Return successfully without inspecting credentials when banking or Sber API is disabled}';

    protected $description = 'Validate the local read-only Sber configuration without exposing secrets.';

    public function handle(SberHealthService $health): int
    {
        if (
            $this->option('if-enabled')
            && (
                ! (bool) config('banking.enabled')
                || ! (bool) config('banking.sber.enabled')
            )
        ) {
            $this->info('Sber health check skipped because the integration is disabled.');
            $this->line('No network request was made. No secret value or secret-file path was printed.');

            return self::SUCCESS;
        }

        $result = $health->inspect();

        foreach ($result['checks'] as $check) {
            $line = sprintf('[%s] %s: %s', strtoupper($check['status']), $check['name'], $check['message']);

            match ($check['status']) {
                'ok' => $this->info($line),
                'warning' => $this->warn($line),
                default => $this->error($line),
            };
        }

        $this->newLine();
        $this->line('No network request was made. No secret value or secret-file path was printed.');

        return $result['status'] === 'error' ? self::FAILURE : self::SUCCESS;
    }
}
