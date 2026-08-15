<?php

namespace App\Console\Commands;

use App\Domain\AiSales\Enums\AiProviderRoute;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Infrastructure\AiSales\Timeweb\TimewebPricingSnapshotService;
use Illuminate\Console\Command;

class RecordTimewebPricingSnapshotCommand extends Command
{
    protected $signature = 'ai:timeweb-pricing:record
        {--route= : local_ru or external_sanitized}
        {--model= : Exact allowlisted inventory model ID}
        {--verifier-id= : Active human verifier user ID}
        {--input-per-million= : RUB per million input tokens}
        {--output-per-million= : RUB per million output tokens}
        {--reasoning-per-million= : Optional RUB per million reasoning tokens}
        {--source-reference= : Safe panel/support/contract/public-doc reference}
        {--source-hash= : SHA-256 of the reviewed pricing evidence}
        {--confirm-human-reviewed : Confirm exact-model pricing was reviewed by a human}';

    protected $description = 'Record an immutable evidence-backed Timeweb exact-model RUB pricing snapshot';

    public function handle(TimewebPricingSnapshotService $service): int
    {
        $route = AiProviderRoute::tryFrom((string) $this->option('route'));

        if (! $route || ! (bool) $this->option('confirm-human-reviewed')) {
            $this->error('A valid --route and explicit --confirm-human-reviewed are required.');

            return self::INVALID;
        }

        try {
            $snapshot = $service->record(
                $route,
                (string) $this->option('model'),
                (int) $this->option('verifier-id'),
                (string) $this->option('input-per-million'),
                (string) $this->option('output-per-million'),
                $this->option('reasoning-per-million') === null ? null : (string) $this->option('reasoning-per-million'),
                (string) $this->option('source-reference'),
                (string) $this->option('source-hash'),
            );
        } catch (PolicyViolation $violation) {
            $this->error("Timeweb pricing snapshot blocked safely: {$violation->errorCode}.");

            return self::FAILURE;
        }

        $this->line(json_encode([
            'provider' => $snapshot->provider_code,
            'route' => $snapshot->provider_route,
            'model_id' => $snapshot->model_id,
            'version' => $snapshot->version,
            'currency' => $snapshot->currency,
            'input_per_million' => $snapshot->input_per_million,
            'output_per_million' => $snapshot->output_per_million,
            'reasoning_per_million' => $snapshot->reasoning_per_million,
            'source_reference' => $snapshot->source_reference,
            'source_hash' => $snapshot->source_hash,
            'effective_at' => $snapshot->effective_at?->toISOString(),
            'expires_at' => $snapshot->expires_at?->toISOString(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
