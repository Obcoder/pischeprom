<?php

namespace App\Console\Commands;

use App\Models\AvitoApiCall;
use App\Models\AvitoConnection;
use App\Models\AvitoWebhookEvent;
use App\Services\Avito\AvitoTokenManager;
use Illuminate\Console\Command;

class MaintainAvitoIntegrationCommand extends Command
{
    protected $signature = 'avito:maintain';

    protected $description = 'Refresh expiring Avito OAuth tokens and prune retained integration logs';

    public function handle(AvitoTokenManager $tokens): int
    {
        $refreshed = 0;
        $failed = 0;

        AvitoConnection::query()
            ->where('is_active', true)
            ->whereNotNull('token_expires_at')
            ->where('token_expires_at', '<=', now()->addHours(6))
            ->each(function (AvitoConnection $connection) use ($tokens, &$refreshed, &$failed): void {
                try {
                    $tokens->refresh($connection);
                    $refreshed++;
                } catch (\Throwable $exception) {
                    report($exception);
                    $failed++;
                }
            });

        $cutoff = now()->subDays((int) config('avito.log_retention_days'));
        $deletedCalls = AvitoApiCall::query()->where('created_at', '<', $cutoff)->delete();
        $deletedEvents = AvitoWebhookEvent::query()->where('created_at', '<', $cutoff)->delete();

        $this->info("OAuth: {$refreshed} обновлено, {$failed} ошибок. Удалено: {$deletedCalls} API-запросов, {$deletedEvents} webhook-событий.");

        return self::SUCCESS;
    }
}
