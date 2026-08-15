<?php

namespace App\Domain\AiSales\DTO\Providers;

use App\Domain\AiSales\Enums\AiModelProfile;
use App\Domain\AiSales\Enums\AiProcessingContour;
use InvalidArgumentException;

final readonly class AiProviderRequest
{
    public array $inputItems;

    public array $toolSchemas;

    public function __construct(
        public string $runPublicId,
        public int $stepSequence,
        public AiProcessingContour $contour,
        public AiModelProfile $modelProfile,
        array $inputItems,
        public array $responseSchema,
        array $toolSchemas,
        public AiRequestRequirements $requirements,
        public string $idempotencyKey,
        public string $policyDecisionHash,
        public string $promptHash,
        public string $schemaHash,
        public string $sanitizedPayloadHash,
        public array $classificationSummary,
        public bool $containsLocalOnlyData,
        public int $timeoutSeconds,
    ) {
        if ($contour === AiProcessingContour::None) {
            throw new InvalidArgumentException('A provider request cannot target the NONE contour.');
        }

        foreach ([$policyDecisionHash, $promptHash, $schemaHash, $sanitizedPayloadHash] as $hash) {
            if (! preg_match('/^[a-f0-9]{64}$/', $hash)) {
                throw new InvalidArgumentException('Provider request hashes must be SHA-256 values.');
            }
        }

        if ($timeoutSeconds < 1 || $timeoutSeconds > 60) {
            throw new InvalidArgumentException('Provider request timeout is outside the Stage 04 bounds.');
        }

        if (count($inputItems) < 1 || count($inputItems) > 16) {
            throw new InvalidArgumentException('Provider request input item count is outside its bounds.');
        }

        foreach ($inputItems as $item) {
            if (! $item instanceof AiProviderInputItem) {
                throw new InvalidArgumentException('Provider inputs must use AiProviderInputItem.');
            }
        }

        if (count($toolSchemas) > 8) {
            throw new InvalidArgumentException('Provider tool schema count exceeds its cap.');
        }

        $this->inputItems = array_values($inputItems);
        $this->toolSchemas = array_values($toolSchemas);
    }

    public function store(): bool
    {
        return false;
    }
}
