<?php

namespace App\Console\Commands;

use App\Services\Mail\LegacySentReconstruction;
use Illuminate\Console\Command;
use Throwable;

class ReconstructLegacySentCommand extends Command
{
    protected $signature = 'mail:reconstruct-legacy-sent {--mailbox= : Required mailbox} {--apply : Append verified local archive copies; otherwise dry run}';

    protected $description = 'Reconstruct legacy Sent archives in an empty folder without SMTP; never guess original identities';

    public function handle(LegacySentReconstruction $service): int
    {
        $address = strtolower(trim((string) $this->option('mailbox')));
        if (! filter_var($address, FILTER_VALIDATE_EMAIL)) {
            $this->error('A valid --mailbox is required.');

            return self::INVALID;
        }
        try {
            $rows = $service->run($address, (bool) $this->option('apply'));
            $this->info($this->option('apply') ? 'Server archive copies only; no SMTP delivery.' : 'Dry run: no messages changed.');
            $this->table(['Local ID', 'Result', 'Reason'], array_map(fn ($row) => array_values($row), $rows));

            return self::SUCCESS;
        } catch (Throwable) {
            $this->error('Reconstruction stopped: Sent must be empty or contain only verified previous reconstructions; all local data and IMAP access must be available. No SMTP delivery was attempted.');

            return self::FAILURE;
        }
    }
}
