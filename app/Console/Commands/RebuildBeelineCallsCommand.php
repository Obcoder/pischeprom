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
        $cleanup = [
            'phone_calls_cleared' => 0,
            'leads_deleted' => 0,
            'telephones_deleted' => 0,
            'entities_deleted' => 0,
        ];

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

        if (!$dryRun) {
            $cleanup = $service->cleanupLikelyServiceClients();
        }

        $this->info('Rebuilt: ' . $rebuilt);
        $this->info('Skipped: ' . $skipped);
        $this->info('Service phone calls cleared: ' . $cleanup['phone_calls_cleared']);
        $this->info('Service leads deleted: ' . $cleanup['leads_deleted']);
        $this->info('Service telephones deleted: ' . $cleanup['telephones_deleted']);
        $this->info('Service entities deleted: ' . $cleanup['entities_deleted']);

        if ($dryRun) {
            $this->warn('Dry run: no calls were changed.');
        }

        return self::SUCCESS;
    }
}
