<?php

namespace App\Domain\AiSales\DTO;

use App\Domain\AiSales\Enums\DataClassification;
use App\Domain\AiSales\Enums\UnitVisibilityScope;
use InvalidArgumentException;

final readonly class AiUntrustedContentEnvelope
{
    public string $boundedText;

    public string $contentHash;

    public string $trustLevel;

    public string $instructionAuthority;

    public function __construct(
        public string $sourceType,
        public string $sourceReference,
        string $text,
        public DataClassification $classification,
        public UnitVisibilityScope $visibilityScope,
    ) {
        if ($sourceType === '' || mb_strlen($sourceType) > 64 || mb_strlen($sourceReference) > 512) {
            throw new InvalidArgumentException('Untrusted content provenance must be bounded.');
        }

        $normalized = trim(preg_replace('/\s+/u', ' ', $text) ?? '');
        $this->boundedText = mb_substr($normalized, 0, 8_192);
        $this->contentHash = hash('sha256', $this->boundedText);
        $this->trustLevel = 'untrusted';
        $this->instructionAuthority = 'none';
    }

    public function asData(): array
    {
        return [
            'source_type' => $this->sourceType,
            'source_reference' => mb_substr($this->sourceReference, 0, 512),
            'content_hash' => $this->contentHash,
            'bounded_text' => $this->boundedText,
            'classification' => $this->classification->value,
            'visibility_scope' => $this->visibilityScope->value,
            'trust_level' => $this->trustLevel,
            'instruction_authority' => $this->instructionAuthority,
        ];
    }
}
