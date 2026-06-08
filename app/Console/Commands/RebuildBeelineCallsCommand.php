<?php

namespace App\Console\Commands;

use App\Models\PhoneCall;
use App\Services\Telephony\BeelinePbxService;
use Illuminate\Console\Command;

class RebuildBeelineCallsCommand extends Command
{
    protected $signature = 'beeline:rebuild-calls
        {--limit=1000 : Maximum stored calls to rebuild}
        {--dry-run : Show counters without writing changes}';

    protected $description = 'Rebuild stored Beeline calls from raw payloads using the current normalizer';

    public function handle(BeelinePbxService $service): int
    {
        $limit = max((int) $this->option('limit'), 1);
        $dryRun = (bool) $this->option('dry-run');
        $rebuilt = 0;
        $skipped = 0;

        PhoneCall::query()
            ->where('provider', 'beeline')
            ->whereNotNull('raw_payload')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->each(function (PhoneCall $call) use ($service, $dryRun, &$rebuilt, &$skipped) {
                if (!$call->raw_payload) {
                    $skipped++;
                    return;
                }

                if (!$dryRun) {
                    $service->rebuildStoredCall($call);
                }

                $rebuilt++;
            });

        $this->info('Rebuilt: ' . $rebuilt);
        $this->info('Skipped: ' . $skipped);

        if ($dryRun) {
            $this->warn('Dry run: no calls were changed.');
        }

        return self::SUCCESS;
    }
}
