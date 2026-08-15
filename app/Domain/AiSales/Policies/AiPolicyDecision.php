<?php

namespace App\Domain\AiSales\Policies;

final readonly class AiPolicyDecision
{
    private function __construct(
        public bool $allowed,
        public string $code,
        public string $reason,
        public ?string $field = null,
    ) {}

    public static function allow(?string $field = null): self
    {
        return new self(true, 'allowed', 'The classified field is allowed for this context.', $field);
    }

    public static function deny(string $code, string $reason, ?string $field = null): self
    {
        return new self(false, $code, $reason, $field);
    }
}
