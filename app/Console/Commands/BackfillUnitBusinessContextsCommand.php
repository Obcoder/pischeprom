<?php

namespace App\Console\Commands;

use App\Domain\AiSales\Services\UnitContextBackfillService;
use Illuminate\Console\Command;

class BackfillUnitBusinessContextsCommand extends Command
{
    protected $signature = 'ai-sales:backfill-unit-contexts
        {--apply : Persist missing contexts; without this option the command is a dry-run}
        {--chunk=200 : Units per chunk, from 1 to 1000}';

    protected $description = 'Report or idempotently backfill Unit business contexts from linked Entity transactions and legacy role flags';

    public function handle(UnitContextBackfillService $backfill): int
    {
        $chunk = filter_var($this->option('chunk'), FILTER_VALIDATE_INT);

        if ($chunk === false || $chunk < 1 || $chunk > 1000) {
            $this->error('--chunk must be an integer from 1 to 1000.');

            return self::INVALID;
        }

        $apply = (bool) $this->option('apply');

        if ($apply && ! app()->environment(['local', 'testing', 'staging'])) {
            $this->error('Stage 03 permits apply only in an explicitly local, testing or staging environment. Production requires a separately approved owner rollout.');

            return self::FAILURE;
        }

        $report = $backfill->run($apply, $chunk);

        $this->table([
            'mode',
            'units',
            'candidates',
            'would create',
            'created',
            'existing',
            'review',
        ], [[
            $report['mode'],
            $report['units_scanned'],
            $report['context_candidates'],
            $report['would_create'],
            $report['created'],
            $report['already_present'],
            $report['review_required'],
        ]]);

        foreach ($report['review'] as $message) {
            $this->warn($message);
        }

        if (! $apply) {
            $this->comment('Dry-run completed: no roles, contexts, Unit flags, Entity records, links or transactions were changed.');
        } else {
            $this->info('Apply completed idempotently in chunks. Entity and transaction data were not changed or copied.');
        }

        return self::SUCCESS;
    }
}
