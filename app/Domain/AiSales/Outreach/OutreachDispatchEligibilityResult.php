<?php

namespace App\Domain\AiSales\Outreach;

final readonly class OutreachDispatchEligibilityResult
{
    public function __construct(
        public bool $eligible,
        public bool $contentReady,
        public array $blockReasons,
        public ?int $permissionId,
    ) {}

    public function toArray(): array
    {
        return [
            'eligible' => $this->eligible,
            'content_ready' => $this->contentReady,
            'block_reasons' => $this->blockReasons,
            'permission_id' => $this->permissionId,
            'dispatch_endpoint_available' => false,
        ];
    }
}
