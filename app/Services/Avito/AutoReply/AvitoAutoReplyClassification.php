<?php

namespace App\Services\Avito\AutoReply;

final readonly class AvitoAutoReplyClassification
{
    public function __construct(
        public string $intent,
        public float $confidence,
        public float $runnerUpConfidence,
        public bool $unsafe,
        public bool $mixed,
        public string $reasonCode,
        public string $model,
        public string $externalRequestId,
        public int $inputTokens,
        public int $outputTokens,
        public int $latencyMs,
        public array $raw,
    ) {}
}
