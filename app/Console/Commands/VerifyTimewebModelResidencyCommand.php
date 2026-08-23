<?php

namespace App\Console\Commands;

use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Infrastructure\AiSales\Timeweb\TimewebResidencyVerificationService;
use Illuminate\Console\Command;

class VerifyTimewebModelResidencyCommand extends Command
{
    protected $signature = 'ai:timeweb-residency:verify
        {--model= : Exact allowlisted local model ID}
        {--verifier-id= : Active human verifier user ID}
        {--evidence-reference= : Safe panel/support/contract/public-doc reference}
        {--evidence-hash= : SHA-256 of evidence reviewed outside the application}
        {--confirm-human-reviewed : Confirm a human reviewed exact-model RU residency evidence}';

    protected $description = 'Record short-lived human RU residency evidence for an exact Timeweb model';

    public function handle(TimewebResidencyVerificationService $service): int
    {
        if (! (bool) $this->option('confirm-human-reviewed')) {
            $this->error('Explicit --confirm-human-reviewed is required.');

            return self::INVALID;
        }

        try {
            $verification = $service->verify(
                (string) $this->option('model'),
                (int) $this->option('verifier-id'),
                (string) $this->option('evidence-reference'),
                (string) $this->option('evidence-hash'),
            );
        } catch (PolicyViolation $violation) {
            $this->error("Timeweb residency verification blocked safely: {$violation->errorCode}.");

            return self::FAILURE;
        }

        $this->line(json_encode([
            'provider' => $verification->provider_code,
            'route' => $verification->provider_route,
            'model_id' => $verification->model_id,
            'status' => $verification->status->value,
            'evidence_reference' => $verification->evidence_reference,
            'evidence_hash' => $verification->evidence_hash,
            'verified_by' => $verification->verified_by,
            'verified_at' => $verification->verified_at?->toISOString(),
            'expires_at' => $verification->expires_at?->toISOString(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        return self::SUCCESS;
    }
}
