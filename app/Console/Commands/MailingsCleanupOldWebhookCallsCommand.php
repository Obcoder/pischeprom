<?php

namespace App\Console\Commands;

use App\Services\CommercialOffers\LegacyMailProviderPayloadService;
use Illuminate\Console\Command;
use RuntimeException;

class MailingsCleanupOldWebhookCallsCommand extends Command
{
    protected $signature = 'mailings:cleanup-old-webhook-calls {--days=30} {--apply : Apply safe anonymization instead of dry-run}';

    protected $description = 'Deprecated alias for safe legacy provider-payload audit/anonymization.';

    public function handle(LegacyMailProviderPayloadService $service): int
    {
        $this->warn('No webhook rows are deleted. --days is retained only for CLI compatibility.');

        try {
            $result = $service->purge((bool) $this->option('apply'));
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(($result['applied'] ? 'Anonymized' : 'Dry-run affected').' rows: '.$result['before']['total_rows']);
        $this->info('Raw values were not displayed.');

        return self::SUCCESS;
    }
}
