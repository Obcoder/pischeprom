<?php

namespace App\Domain\AiSales\Scoring;

use App\Models\UnitGoodFitSnapshot;
use App\Models\UnitProductRelevanceSnapshot;
use App\Models\UnitProspectPrioritySnapshot;
use DomainException;

final class ProspectingScoreStalenessService
{
    private const REASONS = [
        'product_match_changed', 'good_match_changed', 'evidence_changed', 'context_changed',
        'communication_state_changed', 'product_mapping_changed', 'same_lane_aggregate_changed',
        'definition_changed', 'override_expired',
    ];

    public function markContext(int $contextId, string $reason): int
    {
        if (! in_array($reason, self::REASONS, true)) {
            throw new DomainException('Unknown score staleness reason.');
        }

        $count = 0;
        foreach ([UnitProductRelevanceSnapshot::class, UnitGoodFitSnapshot::class, UnitProspectPrioritySnapshot::class] as $model) {
            $model::query()->where('unit_business_context_id', $contextId)
                ->whereNull('stale_at')->whereNull('superseded_at')->orderBy('id')
                ->chunkById(100, function ($rows) use ($reason, &$count): void {
                    foreach ($rows as $row) {
                        $row->update(['stale_at' => now(), 'stale_reason_code' => $reason]);
                        $count++;
                    }
                });
        }

        return $count;
    }

    public function markExpiredOverrides(): int
    {
        $count = 0;
        foreach ([UnitProductRelevanceSnapshot::class, UnitGoodFitSnapshot::class, UnitProspectPrioritySnapshot::class] as $model) {
            $model::query()->where('origin', 'manual_override')->whereNull('stale_at')->whereNull('superseded_at')
                ->whereNotNull('override_expires_at')->where('override_expires_at', '<=', now())->orderBy('id')
                ->chunkById(100, function ($rows) use (&$count): void {
                    foreach ($rows as $row) {
                        $row->update(['stale_at' => now(), 'stale_reason_code' => 'override_expired']);
                        $count++;
                    }
                });
        }

        return $count;
    }
}
