<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Policies\AiDataClassificationRegistry;
use App\Domain\AiSales\Policies\AiDisclosureContext;
use App\Domain\AiSales\Policies\AiDisclosurePolicy;
use App\Domain\AiSales\Policies\AiPolicyDecision;

class AiFieldAuthorizationService
{
    public function __construct(
        private readonly AiDataClassificationRegistry $registry,
        private readonly AiDisclosurePolicy $policy,
    ) {}

    public function decide(string $subject, string $field, AiDisclosureContext $context): AiPolicyDecision
    {
        $classifiedField = $this->registry->find($subject, $field);

        if (! $classifiedField) {
            return AiPolicyDecision::deny(
                'unclassified_field',
                'Fields absent from the code-owned registry are blocked.',
                $subject.'.'.$field,
            );
        }

        return $this->policy->decide($context, $classifiedField);
    }

    public function authorize(string $subject, string $field, AiDisclosureContext $context): void
    {
        $decision = $this->decide($subject, $field, $context);

        if (! $decision->allowed) {
            throw new PolicyViolation($decision->code, $decision->reason, $decision->field);
        }
    }
}
