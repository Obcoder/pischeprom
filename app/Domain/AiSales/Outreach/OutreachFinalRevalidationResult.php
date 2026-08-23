<?php

namespace App\Domain\AiSales\Outreach;

final readonly class OutreachFinalRevalidationResult
{
    /** @param list<string> $blockReasons */
    public function __construct(
        public bool $eligible,
        public array $blockReasons,
        public ?int $permissionId,
        public string $decisionHash,
        public string $revisionHash,
        public string $permissionScopeHash,
        public string $senderConfigHash,
    ) {}

    public function toArray(): array
    {
        return [
            'eligible' => $this->eligible,
            'block_reasons' => $this->blockReasons,
            'permission_id' => $this->permissionId,
            'decision_hash' => $this->decisionHash,
        ];
    }
}
