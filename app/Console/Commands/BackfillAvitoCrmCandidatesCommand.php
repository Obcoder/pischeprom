<?php

namespace App\Console\Commands;

use App\Models\AvitoContactCandidate;
use App\Models\AvitoMessage;
use App\Services\Avito\AvitoContactDetector;
use Illuminate\Console\Command;

class BackfillAvitoCrmCandidatesCommand extends Command
{
    protected $signature = 'avito:crm-backfill {--chat= : Process one local Avito chat ID}';

    protected $description = 'Detect reviewable phone and address candidates in the local Avito message archive';

    public function handle(AvitoContactDetector $detector): int
    {
        $query = AvitoMessage::query()
            ->where('direction', 'in')
            ->where('remote_type', '!=', 'deleted')
            ->whereNull('crm_scanned_at')
            ->when(
                filled($this->option('chat')),
                fn ($messages) => $messages->where('avito_chat_id', (int) $this->option('chat')),
            );
        $total = (clone $query)->count();
        $before = AvitoContactCandidate::query()->count();
        $processed = 0;

        $this->info("Проверяем {$total} входящих сообщений Avito.");

        $query->orderBy('id')->chunkById(250, function ($messages) use ($detector, &$processed): void {
            foreach ($messages as $message) {
                $detector->detectMessage($message);
                $processed++;
            }
        });

        $created = AvitoContactCandidate::query()->count() - $before;
        $this->info("Готово: {$processed} сообщений, {$created} новых кандидатов.");

        return self::SUCCESS;
    }
}
