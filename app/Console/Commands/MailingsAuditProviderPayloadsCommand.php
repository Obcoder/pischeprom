<?php

namespace App\Console\Commands;

use App\Services\CommercialOffers\LegacyMailProviderPayloadService;
use Illuminate\Console\Command;

class MailingsAuditProviderPayloadsCommand extends Command
{
    protected $signature = 'mailings:provider-payloads:audit {--chunk=500 : Rows processed per bounded chunk}';

    protected $description = 'Audit legacy Unisender raw provider columns without displaying their contents.';

    public function handle(LegacyMailProviderPayloadService $service): int
    {
        $audit = $service->audit((int) $this->option('chunk'));
        $this->table(
            ['table', 'rows', 'approximate bytes', 'oldest', 'newest'],
            collect($audit['tables'])->map(fn (array $row, string $table): array => [
                $table,
                $row['rows'],
                $row['approximate_bytes'],
                $row['oldest_at'] ?? '-',
                $row['newest_at'] ?? '-',
            ])->values()->all(),
        );
        $this->info('Total affected rows: '.$audit['total_rows']);
        $this->info('Approximate bytes: '.$audit['total_approximate_bytes']);

        return self::SUCCESS;
    }
}
