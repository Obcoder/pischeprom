<?php

namespace App\Domain\AiSales\Campaigns;

final readonly class ClientAcquisitionCampaignStageOutcome
{
    private function __construct(
        public string $kind,
        public string $safeCode,
        public string $safeSummary,
        public array $metadata,
    ) {}

    public static function completed(array $metadata = []): self
    {
        return new self('completed', 'completed', 'Campaign stage completed.', self::safeMetadata($metadata));
    }

    public static function pending(string $code, string $summary, array $metadata = []): self
    {
        return new self('pending', $code, $summary, self::safeMetadata($metadata));
    }

    public static function requiresAction(string $code, string $summary, array $metadata = []): self
    {
        return new self('requires_action', $code, $summary, self::safeMetadata($metadata));
    }

    public static function blocked(string $code, string $summary, array $metadata = []): self
    {
        return new self('blocked', $code, $summary, self::safeMetadata($metadata));
    }

    private static function safeMetadata(array $metadata): array
    {
        return collect(array_slice($metadata, 0, 30, true))->mapWithKeys(function ($value, $key): array {
            $key = mb_substr(preg_replace('/[^a-z0-9_\-]/i', '', (string) $key) ?? '', 0, 64);
            if ($key === '' || ! (is_scalar($value) || $value === null)) {
                return [];
            }

            return [$key => is_string($value) ? mb_substr($value, 0, 255) : $value];
        })->all();
    }
}
