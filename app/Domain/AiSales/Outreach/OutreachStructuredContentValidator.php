<?php

namespace App\Domain\AiSales\Outreach;

use App\Domain\AiSales\Exceptions\PolicyViolation;

class OutreachStructuredContentValidator
{
    private const KEYS = [
        'subject', 'greeting', 'introduction', 'value_proposition', 'evidence_points',
        'call_to_action', 'closing', 'claims',
    ];

    private const CLAIM_KEYS = ['type', 'text', 'evidence_type', 'evidence_reference', 'evidence_hash'];

    public function validate(array $content): array
    {
        $unknown = array_diff(array_keys($content), self::KEYS);
        $missing = array_diff(self::KEYS, array_keys($content));
        if ($unknown !== [] || $missing !== []) {
            throw new PolicyViolation('outreach_schema_invalid', 'Outreach structured content does not match the code-owned schema.');
        }

        $limits = [
            'subject' => (int) config('ai-sales.outreach.limits.subject_chars', 160),
            'greeting' => 160, 'introduction' => 1000, 'value_proposition' => 2000,
            'call_to_action' => 1000, 'closing' => 500,
        ];
        foreach ($limits as $field => $limit) {
            if (! is_string($content[$field]) || trim($content[$field]) === '' || mb_strlen($content[$field]) > $limit) {
                throw new PolicyViolation('outreach_schema_invalid', 'Outreach field is missing or exceeds its limit.', $field);
            }
        }

        if (! is_array($content['evidence_points']) || count($content['evidence_points']) > 10
            || collect($content['evidence_points'])->contains(fn ($value) => ! is_string($value) || mb_strlen($value) > 1000)) {
            throw new PolicyViolation('outreach_schema_invalid', 'Outreach evidence points are invalid.', 'evidence_points');
        }

        if (! is_array($content['claims']) || count($content['claims']) > (int) config('ai-sales.outreach.limits.claims', 10)) {
            throw new PolicyViolation('outreach_schema_invalid', 'Outreach claims are invalid.', 'claims');
        }
        foreach ($content['claims'] as $claim) {
            if (! is_array($claim) || array_diff(array_keys($claim), self::CLAIM_KEYS) !== []
                || array_diff(self::CLAIM_KEYS, array_keys($claim)) !== []
                || ! in_array($claim['type'], ['product_relevance', 'good_offer_fit'], true)
                || ! in_array($claim['evidence_type'], ['unit_product_match', 'unit_good_match'], true)
                || ! is_string($claim['text']) || mb_strlen($claim['text']) > 500
                || ! is_string($claim['evidence_reference']) || mb_strlen($claim['evidence_reference']) > 512
                || ! is_string($claim['evidence_hash']) || ! preg_match('/^[a-f0-9]{64}$/', $claim['evidence_hash'])) {
                throw new PolicyViolation('outreach_claim_schema_invalid', 'Outreach claim lacks allowed evidence.');
            }
        }

        return $content;
    }
}
