<?php

namespace App\Domain\AiSales\Outreach;

use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Enums\UnitRoleCode;
use App\Models\UnitBusinessContext;

class DeterministicOutreachDlpGuard
{
    /** @var array<string, string> */
    private const PATTERNS = [
        'credential_material' => '/(?:password|passwd|api[_ -]?key|access[_ -]?token|session[_ -]?id|cookie|private\s+key|-----BEGIN|\.env\b)/iu',
        'raw_correspondence' => '/(?:raw[_ -]?headers?|in-reply-to|references:|переписк[а-я]*|исходное\s+письмо)/iu',
        'procurement_secret' => '/(?:purchase\s+price|supplier\s+secret|закупочн(?:ая|ые)\s+цен[аы]|марж[а-я]*|себестоимост[а-я]*|секрет\s+поставщик[а-я]*)/iu',
        'supplier_identity' => '/(?:supplier\s+(?:identity|contact)|контакт(?:ы)?\s+поставщик[а-я]*|поставщик\s*[:=])/iu',
        'restricted_business_record' => '/(?:\b(?:contract|invoice|payment)\b|договор\s*(?:№|номер|:)|сч[её]т\s*(?:№|номер|:)|плат[её]ж\s*(?:№|номер|:)|\b(?:ИНН|ОГРН|БИК)\b)/iu',
        'contact_data' => '/(?:mailto:|\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b|(?<![\pL\pN])(?:\+7|8)[\s()\-]*\d{3}[\s()\-]*\d{3}[\s\-]*\d{2}[\s\-]*\d{2}(?!\d))/iu',
        'unsupported_commercial_fact' => '/(?:\b(?:price|stock|discount|moq)\b|цен[аы]\s*[:=]|в\s+наличии|скидк[а-я]*|минимальн[а-я ]+парти[яи])/iu',
        'arbitrary_url' => '/(?:https?:\/\/|www\.)/iu',
        'deceptive_claim' => '/(?:\bguarantee(?:d)?\b|гарантир(?:уем|ован)[а-я]*|100\s*%|только\s+сегодня|срочно\s+ответьте)/iu',
        'prompt_injection_residue' => '/(?:ignore\s+(?:all\s+)?previous|system\s+prompt|developer\s+message|следуй\s+инструкци[а-я]*|игнорируй\s+предыдущ[а-я]*|<\|(?:im_start|system|assistant)\|>)/iu',
        'active_content' => '/(?:<script|javascript:|data:text\/html|onerror\s*=|onclick\s*=)/iu',
    ];

    public function inspect(UnitBusinessContext $context, array $structured, array $rendered): OutreachDlpResult
    {
        $codes = [];
        if ($context->lane !== BusinessLane::Sales) {
            $codes[] = 'cross_lane_context';
        }
        if (! in_array($context->role_code, [UnitRoleCode::Customer, UnitRoleCode::ProspectiveCustomer], true)) {
            $codes[] = 'recipient_role_not_sales';
        }

        $text = implode("\n", array_filter([
            $structured['subject'] ?? null,
            $structured['greeting'] ?? null,
            $structured['introduction'] ?? null,
            $structured['value_proposition'] ?? null,
            ...($structured['evidence_points'] ?? []),
            $structured['call_to_action'] ?? null,
            $structured['closing'] ?? null,
            ...collect($structured['claims'] ?? [])->pluck('text')->all(),
            $rendered['subject'] ?? null,
            $rendered['plaintext'] ?? null,
            $rendered['html'] ?? null,
        ]));
        foreach (self::PATTERNS as $code => $pattern) {
            if (preg_match($pattern, $text)) {
                $codes[] = $code;
            }
        }

        $codes = array_values(array_unique($codes));

        return new OutreachDlpResult($codes === [], $codes);
    }
}
