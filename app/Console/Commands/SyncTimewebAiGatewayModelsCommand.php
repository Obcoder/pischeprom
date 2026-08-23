<?php

namespace App\Console\Commands;

use App\Domain\AiSales\Enums\AiProviderRoute;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Infrastructure\AiSales\Timeweb\TimewebModelInventoryService;
use App\Infrastructure\AiSales\Timeweb\TimewebProbeBudgetGuard;
use App\Infrastructure\AiSales\Timeweb\TimewebTransportException;
use Illuminate\Console\Command;

class SyncTimewebAiGatewayModelsCommand extends Command
{
    protected $signature = 'ai:timeweb-models:sync
        {--route= : local_ru or external_sanitized}
        {--dry-run : Preview normalized inventory without writing}
        {--apply : Persist normalized inventory state}
        {--confirm-apply : Explicitly confirm the non-production inventory write}
        {--synthetic : Confirm that this operational request contains no domain data}
        {--operator-reference=stage05-cli : Bounded non-secret audit reference}';

    protected $description = 'Synchronize safe Timeweb model inventory using a guarded synthetic-only request';

    public function handle(TimewebModelInventoryService $inventory, TimewebProbeBudgetGuard $budget): int
    {
        $route = AiProviderRoute::tryFrom((string) $this->option('route'));
        $apply = (bool) $this->option('apply');

        if (! $route || ! (bool) $this->option('synthetic')) {
            $this->error('A valid --route and explicit --synthetic confirmation are required.');

            return self::INVALID;
        }

        if ($apply && (! (bool) $this->option('confirm-apply') || (bool) $this->option('dry-run'))) {
            $this->error('Apply requires --apply --confirm-apply and cannot be combined with --dry-run.');

            return self::INVALID;
        }

        try {
            $result = $inventory->sync(
                $route,
                $apply,
                (string) $this->option('operator-reference'),
                $budget,
            );
        } catch (PolicyViolation|TimewebTransportException $exception) {
            $code = $exception instanceof PolicyViolation ? $exception->errorCode : $exception->safeCode;
            $this->error("Timeweb inventory sync blocked safely: {$code}.");

            return self::FAILURE;
        }

        $this->info($result->applied ? 'Normalized inventory applied.' : 'Dry-run only; database unchanged.');
        $this->line(json_encode([
            'route' => $result->route,
            'discovered' => $result->discovered,
            'created' => $result->created,
            'updated' => $result->updated,
            'marked_inactive' => $result->markedInactive,
            'model_ids' => $result->modelIds,
            'request_id' => $result->requestId,
            'result_hash' => $result->resultHash,
            'budget' => $result->budget,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
