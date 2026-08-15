<?php

namespace App\Domain\AiSales\Services;

use App\Models\Unit;
use App\Models\UnitBusinessContext;
use App\Models\UnitDossierAuditEvent;
use App\Models\User;

class UnitDossierAuditLogger
{
    private const BLOCKED_METADATA_KEYS = [
        'password', 'token', 'api_key', 'authorization', 'bearer', 'secret', 'cookie', 'session',
        'private_key', '.env', 'env', 'body', 'html', 'headers', 'attachment',
    ];

    public function record(
        Unit $unit,
        string $eventType,
        string $summary,
        ?User $actor = null,
        ?UnitBusinessContext $context = null,
        ?string $subjectType = null,
        ?int $subjectId = null,
        array $metadata = [],
    ): UnitDossierAuditEvent {
        return UnitDossierAuditEvent::query()->create([
            'unit_id' => $unit->id,
            'unit_name_snapshot' => mb_substr($unit->name, 0, 255),
            'unit_business_context_id' => $context?->id,
            'event_type' => mb_substr($eventType, 0, 96),
            'subject_type' => $subjectType ? mb_substr($subjectType, 0, 48) : null,
            'subject_id' => $subjectId,
            'actor_type' => $actor ? 'human' : 'system',
            'actor_user_id' => $actor?->id,
            'summary' => mb_substr(trim($summary), 0, 512),
            'metadata' => $this->safeMetadata($metadata),
            'created_at' => now(),
        ]);
    }

    private function safeMetadata(array $metadata): array
    {
        $safe = [];

        foreach (array_slice($metadata, 0, 30, true) as $key => $value) {
            $normalizedKey = mb_strtolower((string) $key);

            if (collect(self::BLOCKED_METADATA_KEYS)->contains(fn (string $blocked) => str_contains($normalizedKey, $blocked))) {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $safe[mb_substr((string) $key, 0, 64)] = is_string($value) ? mb_substr($value, 0, 512) : $value;

                continue;
            }

            if (is_array($value)) {
                $safe[mb_substr((string) $key, 0, 64)] = collect(array_slice($value, 0, 25))
                    ->filter(fn (mixed $item) => is_scalar($item) || $item === null)
                    ->map(fn (mixed $item) => is_string($item) ? mb_substr($item, 0, 255) : $item)
                    ->values()
                    ->all();
            }
        }

        return $safe;
    }
}
