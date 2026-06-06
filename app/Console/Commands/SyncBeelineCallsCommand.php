<?php

namespace App\Console\Commands;

use App\Services\Telephony\BeelinePbxService;
use Illuminate\Console\Command;

class SyncBeelineCallsCommand extends Command
{
    protected $signature = 'beeline:sync-calls
        {--period=today : today, yesterday, this_week, last_week, this_month, last_month}
        {--start= : UTC start time in YYYYmmddTHHMMSSZ format}
        {--end= : UTC end time in YYYYmmddTHHMMSSZ format}
        {--type=all : all, in, out, missed}
        {--limit=500 : Maximum rows requested from Beeline}
        {--api-url= : Override Beeline PBX API URL}
        {--dry-run : Fetch and parse calls without writing to database}';

    protected $description = 'Sync call history from Beeline Cloud PBX into Ameise CRM';

    public function handle(BeelinePbxService $service): int
    {
        try {
            $result = $service->syncHistory([
                'period' => $this->option('period'),
                'start' => $this->option('start'),
                'end' => $this->option('end'),
                'type' => $this->option('type'),
                'limit' => (int) $this->option('limit'),
                'api_url' => $this->option('api-url'),
            ], (bool) $this->option('dry-run'));
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Fetched: ' . $result['fetched']);
        $this->info('Stored: ' . $result['stored']);
        $this->info('Skipped: ' . $result['skipped']);

        if ($this->option('dry-run') && !empty($result['sample'])) {
            $this->line('Sample:');
            $this->line(json_encode($result['sample'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }

        return self::SUCCESS;
    }
}
