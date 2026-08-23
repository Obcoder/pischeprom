<?php

namespace App\Console\Commands;

use App\Domain\AiSales\Services\ProspectingRetentionService;
use Illuminate\Console\Command;
use Throwable;

class PruneProspectingCandidatesCommand extends Command
{
    protected $signature = 'ai-sales:prune-prospecting-candidates
        {--apply : Apply anonymization; default is dry-run}
        {--yes : Confirm the explicit apply operation}
        {--chunk=100 : Rows per chunk, 1..500}';

    protected $description = 'Dry-run or anonymize expired transient prospecting candidates in bounded chunks';

    public function handle(ProspectingRetentionService $retention): int
    {
        $chunk = filter_var($this->option('chunk'), FILTER_VALIDATE_INT);
        if ($chunk === false || $chunk < 1 || $chunk > 500) {
            $this->error('--chunk must be an integer from 1 to 500.');

            return self::INVALID;
        }
        $apply = (bool) $this->option('apply');
        if ($apply && ! (bool) $this->option('yes')) {
            $this->error('Blocked: --apply requires explicit --yes confirmation.');

            return self::FAILURE;
        }
        try {
            $report = $retention->prune($apply, $chunk);
        } catch (Throwable $exception) {
            $this->error('Blocked: '.$exception->getMessage());

            return self::FAILURE;
        }
        $this->table(['mode', 'eligible', 'personal due', 'anonymized', 'channels deleted', 'sources sanitized'], [[
            $report['dry_run'] ? 'dry-run' : 'apply',
            $report['eligible_candidates'],
            $report['eligible_personal_channels'],
            $report['anonymized_candidates'],
            $report['deleted_channels'],
            $report['sanitized_sources'],
        ]]);
        $this->comment('Output contains counters only; no candidate values, channel values, prompts, or provider bodies.');

        return self::SUCCESS;
    }
}
