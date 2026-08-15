<?php

namespace App\Domain\AiSales\Services;

use App\Domain\AiSales\DTO\Routing\AiDlpFinding;
use App\Domain\AiSales\DTO\Routing\AiDlpScanResult;
use App\Domain\AiSales\Enums\AiProcessingContour;

class DeterministicAiPayloadScanner
{
    private const SECRET_KEY_MARKERS = [
        'password', 'passwd', 'api_key', 'apikey', 'access_token', 'refresh_token',
        'authorization', 'bearer', 'cookie', 'session', 'private_key', 'client_secret',
        'two_factor_secret', 'remember_token', '.env',
    ];

    public function scan(array $payload, AiProcessingContour $contour): AiDlpScanResult
    {
        $findings = [];
        $secretCount = 0;
        $personalCount = 0;
        $this->walk($payload, '$', $contour, $findings, $secretCount, $personalCount);

        return new AiDlpScanResult($findings, $secretCount, $personalCount);
    }

    private function walk(
        array $payload,
        string $path,
        AiProcessingContour $contour,
        array &$findings,
        int &$secretCount,
        int &$personalCount,
    ): void {
        foreach ($payload as $key => $value) {
            $childPath = $path.'.'.(string) $key;
            $normalizedKey = mb_strtolower((string) $key);

            if ($this->containsMarker($normalizedKey, self::SECRET_KEY_MARKERS)) {
                $secretCount++;
                $findings[] = $this->finding('field_name', 'credential_key', 'secret', 'block', $childPath);

                continue;
            }

            if (is_array($value)) {
                $this->walk($value, $childPath, $contour, $findings, $secretCount, $personalCount);

                continue;
            }

            if (! is_string($value) || $value === '') {
                continue;
            }

            if ($this->containsSecretValue($value)) {
                $secretCount++;
                $findings[] = $this->finding('value_pattern', 'credential_material', 'secret', 'block', $childPath);

                continue;
            }

            if ($contour === AiProcessingContour::ExternalSanitized && $this->containsPersonalData($value)) {
                $personalCount++;
                $findings[] = $this->finding('value_pattern', 'direct_identifier', 'personal_data', 'block', $childPath);
            }
        }
    }

    private function containsSecretValue(string $value): bool
    {
        return preg_match('/-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/i', $value) === 1
            || preg_match('/\bBearer\s+[A-Za-z0-9._~+\/-]{16,}/i', $value) === 1
            || preg_match('/\beyJ[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\.[A-Za-z0-9_-]{10,}\b/', $value) === 1
            || preg_match('/(?:password|api[_-]?key|client[_-]?secret|access[_-]?token)\s*[:=]\s*[^\s]{8,}/i', $value) === 1;
    }

    private function containsPersonalData(string $value): bool
    {
        return preg_match('/\b[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}\b/i', $value) === 1
            || preg_match('/(?<!\d)(?:\+7|8)[\s()\-]*\d{3}[\s()\-]*\d{3}[\s\-]*\d{2}[\s\-]*\d{2}(?!\d)/u', $value) === 1;
    }

    private function containsMarker(string $value, array $markers): bool
    {
        foreach ($markers as $marker) {
            if (str_contains($value, $marker)) {
                return true;
            }
        }

        return false;
    }

    private function finding(string $detector, string $rule, string $type, string $action, string $path): AiDlpFinding
    {
        return new AiDlpFinding($detector, $rule, $type, $action, hash('sha256', $path));
    }
}
