<?php

namespace App\Console\Commands;

use App\Services\CommercialOffers\LegacyMailProviderPayloadService;
use Illuminate\Console\Command;
use RuntimeException;

class MailingsPurgeProviderPayloadsCommand extends Command
{
    protected $signature = 'mailings:provider-payloads:purge
        {--apply : Apply anonymization; omitted means dry-run}
        {--chunk=500 : Rows processed per bounded chunk}';

    protected $description = 'Dry-run or purge deprecated Unisender raw provider columns with safe counters only.';

    public function handle(LegacyMailProviderPayloadService $service): int
    {
        try {
            $result = $service->purge((bool) $this->option('apply'), (int) $this->option('chunk'));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->line($result['applied'] ? 'Mode: apply' : 'Mode: dry-run');
        foreach ($result['before']['tables'] as $table => $summary) {
            $updated = $result['updated_rows'][$table] ?? 0;
            $this->line(sprintf('%s rows=%d bytes=%d updated=%d', $table, $summary['rows'], $summary['approximate_bytes'], $updated));
        }
        $this->info('Raw values were not displayed.');

        return self::SUCCESS;
    }
}
