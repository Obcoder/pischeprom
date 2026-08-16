<?php

namespace App\Domain\AiSales\Tools;

use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\BusinessLane;
use App\Domain\AiSales\Exceptions\PolicyViolation;
use App\Domain\AiSales\Services\DeterministicAiPayloadScanner;

class AiToolDlpGuard
{
    private const SECRET_KEYS = [
        'password', 'passwd', 'api_key', 'apikey', 'token', 'authorization', 'bearer',
        'cookie', 'session', 'private_key', 'client_secret', 'remember_token', '.env',
    ];

    private const RAW_CORRESPONDENCE_KEYS = [
        'mail_body', 'email_body', 'message_body', 'raw_body', 'raw_html', 'headers', 'attachments',
    ];

    private const INJECTION_MARKERS = [
        'ignore previous', 'ignore all previous', 'system prompt', 'developer message',
        'reveal your instructions', 'disable dlp', 'change workflow', 'call another tool',
        'игнорируй предыдущ', 'системный промпт', 'отключи dlp', 'измени workflow',
    ];

    public function __construct(private readonly DeterministicAiPayloadScanner $scanner) {}

    public function assertSafe(array $payload, AiToolExecutionContext $context): AiToolDlpResult
    {
        return $this->assertPayloadSafe($payload, $context->contour, $context->lane);
    }

    public function assertPayloadSafe(
        array $payload,
        AiProcessingContour $contour,
        BusinessLane $lane,
    ): AiToolDlpResult {
        $scan = $this->scanner->scan($payload, $contour);

        if ($scan->blocked()) {
            throw new PolicyViolation('tool_dlp_sensitive_material_blocked', 'Tool DLP blocked sensitive material.');
        }

        $findings = 0;
        $this->walk($payload, $contour, $lane, $findings);

        return new AiToolDlpResult('allow', $findings);
    }

    private function walk(array $payload, AiProcessingContour $contour, BusinessLane $lane, int &$findings): void
    {
        foreach ($payload as $key => $value) {
            $normalizedKey = mb_strtolower((string) $key);

            if ($this->containsAny($normalizedKey, self::SECRET_KEYS)) {
                throw new PolicyViolation('tool_dlp_secret_blocked', 'Credential or secret material is always blocked.');
            }

            if ($this->containsAny($normalizedKey, self::RAW_CORRESPONDENCE_KEYS)) {
                throw new PolicyViolation('tool_dlp_raw_correspondence_blocked', 'Raw correspondence is not a tool input or output.');
            }

            if ($contour === AiProcessingContour::ExternalSanitized
                && $this->containsAny($normalizedKey, ['inn', 'kpp', 'ogrn', 'bank_account', 'bank_bic', 'registry_identifier'])) {
                throw new PolicyViolation('tool_dlp_registry_identifier_blocked', 'Registry and banking identifiers are blocked externally.');
            }

            if ($contour === AiProcessingContour::ExternalSanitized
                && $this->containsAny($normalizedKey, [
                    'person_name', 'contact_name', 'first_name', 'last_name', 'middle_name', 'full_name', 'fio',
                ])) {
                throw new PolicyViolation('tool_dlp_personal_marker_blocked', 'Person-specific name fields are blocked externally.');
            }

            if (is_array($value)) {
                $this->walk($value, $contour, $lane, $findings);

                continue;
            }

            if (! is_string($value) || $value === '') {
                continue;
            }

            $normalized = mb_strtolower($value);

            if ($this->containsAny($normalized, self::INJECTION_MARKERS)) {
                throw new PolicyViolation('tool_untrusted_instruction_blocked', 'Untrusted content cannot alter the server-owned workflow.');
            }

            if (preg_match('/<\/?(?:script|iframe|object|embed|style|form)\b/i', $value) === 1
                || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value) === 1) {
                throw new PolicyViolation('tool_dlp_active_content_blocked', 'Active HTML or control content is blocked.');
            }

            if ($this->containsAny($normalized, ['authorization:', 'cookie:', 'set-cookie:', 'begin private key', 'app_key='])) {
                throw new PolicyViolation('tool_dlp_secret_blocked', 'Credential or configuration material is always blocked.');
            }

            if ($lane === BusinessLane::Sales
                && $this->containsAny($normalized, ['supplier_secret', 'procurement_secret', 'purchase_cost', 'supplier_margin'])) {
                throw new PolicyViolation('tool_dlp_cross_lane_blocked', 'Opposite-lane material is blocked.');
            }

            if ($lane === BusinessLane::Procurement
                && $this->containsAny($normalized, ['customer_secret', 'sales_secret', 'customer_price', 'sales_margin'])) {
                throw new PolicyViolation('tool_dlp_cross_lane_blocked', 'Opposite-lane material is blocked.');
            }

        }
    }

    private function containsAny(string $value, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($value, $needle)) {
                return true;
            }
        }

        return false;
    }
}
