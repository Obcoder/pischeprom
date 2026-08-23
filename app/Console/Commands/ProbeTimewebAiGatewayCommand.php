<?php

namespace App\Console\Commands;

use App\Domain\AiSales\Enums\AiProviderRoute;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Infrastructure\AiSales\Timeweb\TimewebModelSelector;
use App\Infrastructure\AiSales\Timeweb\TimewebProbeBudgetGuard;
use App\Infrastructure\AiSales\Timeweb\TimewebSyntheticProbeService;
use App\Infrastructure\AiSales\Timeweb\TimewebTransportException;
use Illuminate\Console\Command;

class ProbeTimewebAiGatewayCommand extends Command
{
    protected $signature = 'ai:provider-probe
        {provider : Must be timeweb in Stage 05}
        {--route= : local_ru or external_sanitized}
        {--profile=all : all, basic, responses, structured, tools or store}
        {--model= : Optional exact allowlisted model ID}
        {--confirm-synthetic : Explicitly confirm repository-owned synthetic fixtures only}
        {--record-evidence : Persist safe capability metadata and hashes only}
        {--operator-reference=stage05-cli : Bounded non-secret audit reference}';

    protected $description = 'Run bounded synthetic-only Timeweb capability probes with no fallback';

    public function handle(
        TimewebModelSelector $models,
        TimewebSyntheticProbeService $probes,
        TimewebProbeBudgetGuard $budget,
    ): int {
        $route = AiProviderRoute::tryFrom((string) $this->option('route'));

        if ((string) $this->argument('provider') !== 'timeweb'
            || ! $route
            || ! (bool) $this->option('confirm-synthetic')) {
            $this->error('Provider timeweb, a valid --route and --confirm-synthetic are required.');

            return self::INVALID;
        }

        $configuredModels = $models->allowedModels($route);
        $requestedModel = trim((string) $this->option('model'));
        $modelIds = $requestedModel === '' ? $configuredModels : [$requestedModel];

        if ($modelIds === []) {
            $this->error('No exact server-side model IDs are configured for this route.');

            return self::FAILURE;
        }

        $safeResults = [];

        try {
            foreach ($modelIds as $modelId) {
                $models->assertAllowed($route, $modelId);
                $result = $probes->run(
                    $route,
                    $modelId,
                    (string) $this->option('profile'),
                    (bool) $this->option('record-evidence'),
                    (string) $this->option('operator-reference'),
                    $budget,
                );
                $safeResults[] = [
                    'route' => $result->route,
                    'model_id' => $result->modelId,
                    'capabilities' => $result->capabilities,
                    'evidence_recorded' => $result->evidenceRecorded,
                    'result_hash' => $result->resultHash,
                ];
            }
        } catch (PolicyViolation|TimewebTransportException $exception) {
            $code = $exception instanceof PolicyViolation ? $exception->errorCode : $exception->safeCode;
            $this->error("Timeweb synthetic probe blocked safely: {$code}.");

            return self::FAILURE;
        }

        $this->line(json_encode([
            'provider' => 'timeweb',
            'results' => $safeResults,
            'budget' => $budget->summary(),
            'raw_bodies_persisted' => false,
            'fallback_attempted' => false,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
