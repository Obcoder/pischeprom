<?php

namespace App\Console\Commands;

use App\Services\CommercialOffers\RecipientSetService;
use Illuminate\Console\Command;

class MailingsImportContactsCommand extends Command
{
    protected $signature = 'mailings:import-contacts {file} {--set-id=} {--source-type=csv} {--contact-source=}';

    protected $description = 'Import mailing contacts from CSV.';

    public function handle(RecipientSetService $recipientSets): int
    {
        $import = $recipientSets->importCsv((string) $this->argument('file'), [
            'set_id' => $this->option('set-id') ? (int) $this->option('set-id') : null,
            'source_type' => $this->option('source-type'),
            'contact_source' => $this->option('contact-source'),
        ]);

        $this->info('Import completed.');
        $this->line(json_encode($import->only(['total_rows', 'imported_count', 'duplicate_count', 'invalid_count', 'skipped_count']), JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
