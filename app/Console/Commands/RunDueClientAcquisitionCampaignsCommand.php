<?php

namespace App\Console\Commands;

use App\Domain\AiSales\Campaigns\ClientAcquisitionCampaignFeatureGuard;
use App\Domain\AiSales\Campaigns\StartClientAcquisitionCampaignRun;
use App\Jobs\AiSales\ExecuteClientAcquisitionCampaignRunJob;
use App\Models\ClientAcquisitionCampaign;
use Illuminate\Console\Command;
use Throwable;

final class RunDueClientAcquisitionCampaignsCommand extends Command
{
    protected $signature = 'ai-sales:run-due-campaigns {--apply : Dispatch due campaign runs in a non-production environment}';

    protected $description = 'Dry-run or dispatch a bounded batch of due approved AI Sales campaigns';

    public function handle(ClientAcquisitionCampaignFeatureGuard $features, StartClientAcquisitionCampaignRun $starter): int
    {
        $apply = (bool) $this->option('apply');
        $batch = max(0, (int) config('ai-sales.campaigns.limits.scheduler_batch', 0));
        if ($apply) {
            $features->scheduler();
            if ($batch < 1) {
                $this->error('blocked=1 reason=scheduler_batch_zero');

                return self::FAILURE;
            }
        }
        $query = ClientAcquisitionCampaign::query()
            ->where('status', 'scheduled')
            ->whereNotNull('approval_snapshot_hash')
            ->whereNotNull('next_run_at')
            ->where('next_run_at', '<=', now())
            ->orderBy('next_run_at')->orderBy('id');
        $due = $batch > 0 ? $query->limit($batch)->get() : collect();
        $counters = ['due' => $due->count(), 'dispatched' => 0, 'blocked' => 0];
        if ($apply) {
            foreach ($due as $campaign) {
                try {
                    $actor = $campaign->owner()->firstOrFail();
                    $slot = $campaign->next_run_at->copy();
                    $run = $starter->handle($campaign, $actor, 'scheduler:'.$slot->copy()->utc()->toIso8601String(), $slot);
                    ExecuteClientAcquisitionCampaignRunJob::dispatch($run->id, $actor->id);
                    $counters['dispatched']++;
                } catch (Throwable) {
                    $counters['blocked']++;
                }
            }
        }
        $this->line(sprintf(
            'mode=%s due=%d dispatched=%d blocked=%d retries=0 failovers=0',
            $apply ? 'apply' : 'dry-run', $counters['due'], $counters['dispatched'], $counters['blocked'],
        ));

        return self::SUCCESS;
    }
}
