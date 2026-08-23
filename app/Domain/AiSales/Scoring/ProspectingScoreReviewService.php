<?php

namespace App\Domain\AiSales\Scoring;

use App\Domain\AiSales\Enums\AiProcessingContour;
use App\Domain\AiSales\Enums\ScoreReviewStatus;
use App\Domain\AiSales\Enums\ScoreSnapshotOrigin;
use App\Domain\AiSales\Services\UnitDossierAuditLogger;
use App\Domain\AiSales\Support\AiCanonicalJson;
use App\Domain\AiSales\Tools\AiToolDlpGuard;
use App\Models\UnitGoodFitSnapshot;
use App\Models\UnitProductRelevanceSnapshot;
use App\Models\UnitProspectPrioritySnapshot;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ProspectingScoreReviewService
{
    public function __construct(
        private readonly AiToolDlpGuard $dlp,
        private readonly UnitDossierAuditLogger $audit,
    ) {}

    public function review(Model $base, ScoreReviewStatus $status, User $actor): Model
    {
        if (! in_array($base::class, [UnitProductRelevanceSnapshot::class, UnitGoodFitSnapshot::class, UnitProspectPrioritySnapshot::class], true)) {
            throw ValidationException::withMessages(['snapshot' => 'Unsupported score snapshot.']);
        }

        return DB::transaction(function () use ($base, $status, $actor): Model {
            $snapshot = $this->copy($base, [
                'review_status' => $status->value,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
                'origin' => ScoreSnapshotOrigin::ReviewCorrection->value,
                'base_snapshot_id' => $base->id,
            ], ['review', $status->value, (string) $actor->id]);
            $this->recordAudit($snapshot, 'unit.score.reviewed', 'Score snapshot reviewed.', $actor, ['review_status' => $status->value]);

            return $snapshot;
        }, 3);
    }

    public function override(Model $base, int $effectiveScore, string $reasonCode, string $safeNote, ?string $expiresAt, User $actor): Model
    {
        if (! in_array($base::class, [UnitProductRelevanceSnapshot::class, UnitGoodFitSnapshot::class, UnitProspectPrioritySnapshot::class], true)) {
            throw ValidationException::withMessages(['snapshot' => 'Unsupported score snapshot.']);
        }
        $context = $base->businessContext()->select(['id', 'lane'])->firstOrFail();
        $this->dlp->assertPayloadSafe(['review_note' => $safeNote], AiProcessingContour::LocalRu, $context->lane);
        if (str_starts_with((string) $base->eligibility, 'blocked_')) {
            $effectiveScore = 0;
        }

        return DB::transaction(function () use ($base, $effectiveScore, $reasonCode, $safeNote, $expiresAt, $actor): Model {
            $snapshot = $this->copy($base, [
                'effective_score' => max(0, min(100, $effectiveScore)),
                'origin' => ScoreSnapshotOrigin::ManualOverride->value,
                'base_snapshot_id' => $base->id,
                'override_reason_code' => mb_substr($reasonCode, 0, 64),
                'override_safe_note' => mb_substr($safeNote, 0, 500),
                'override_expires_at' => $expiresAt,
                'review_status' => ScoreReviewStatus::Reviewed->value,
                'reviewed_by' => $actor->id,
                'reviewed_at' => now(),
            ], ['override', (string) $effectiveScore, $reasonCode, $safeNote, (string) $expiresAt, (string) $actor->id]);
            $this->recordAudit($snapshot, 'unit.score.overridden', 'Append-only score override created.', $actor, [
                'base_snapshot_id' => $base->id,
                'reason_code' => $reasonCode,
                'computed_score' => $snapshot->computed_score,
                'effective_score' => $snapshot->effective_score,
                'eligibility' => $snapshot->eligibility,
            ]);

            return $snapshot;
        }, 3);
    }

    private function copy(Model $base, array $changes, array $keyParts): Model
    {
        return DB::transaction(function () use ($base, $changes, $keyParts): Model {
            $locked = $base::query()->lockForUpdate()->with('factors')->findOrFail($base->id);
            if ($locked->superseded_at !== null || $locked->stale_at !== null) {
                throw ValidationException::withMessages(['snapshot' => 'Only a current score snapshot can be reviewed or overridden.']);
            }
            $attributes = $locked->getAttributes();
            unset($attributes['id'], $attributes['created_at'], $attributes['updated_at'], $attributes['stale_at'], $attributes['stale_reason_code'], $attributes['superseded_at'], $attributes['superseded_by_snapshot_id']);
            $attributes = [...$attributes, ...$changes];
            $attributes['input_hash'] = AiCanonicalJson::hash(['base_input_hash' => $locked->input_hash, 'change' => $keyParts]);
            $attributes['idempotency_key'] = AiCanonicalJson::hash(['model' => $locked::class, 'base' => $locked->id, 'change' => $keyParts]);
            $existing = $base::query()->where('idempotency_key', $attributes['idempotency_key'])->first();
            if ($existing) {
                return $existing->load('factors');
            }
            $new = $base::query()->create($attributes);
            $foreignKey = match ($base::class) {
                UnitProductRelevanceSnapshot::class => 'unit_product_relevance_snapshot_id',
                UnitGoodFitSnapshot::class => 'unit_good_fit_snapshot_id',
                UnitProspectPrioritySnapshot::class => 'unit_prospect_priority_snapshot_id',
            };
            $now = now();
            $rows = $locked->factors->map(function ($factor) use ($foreignKey, $new, $now): array {
                $row = $factor->getAttributes();
                unset($row['id'], $row['created_at'], $row['updated_at']);
                foreach (array_keys($row) as $key) {
                    if (str_ends_with($key, '_snapshot_id')) {
                        unset($row[$key]);
                    }
                }

                return [$foreignKey => $new->id, ...$row, 'created_at' => $now, 'updated_at' => $now];
            })->all();
            $new->factors()->getModel()->newQuery()->insert($rows);
            $locked->update(['superseded_at' => now(), 'superseded_by_snapshot_id' => $new->id]);

            return $new->load('factors');
        }, 3);
    }

    private function recordAudit(Model $snapshot, string $event, string $summary, User $actor, array $metadata): void
    {
        $unit = $snapshot->unit()->without(['businessContexts'])->select(['units.id', 'units.name'])->firstOrFail();
        $context = $snapshot->businessContext()->select(['id', 'unit_id', 'lane', 'role_code'])->firstOrFail();
        $this->audit->record(
            $unit, $event, $summary, $actor, $context,
            match ($snapshot::class) {
                UnitProductRelevanceSnapshot::class => 'product_score_snapshot',
                UnitGoodFitSnapshot::class => 'good_score_snapshot',
                UnitProspectPrioritySnapshot::class => 'priority_score_snapshot',
            },
            $snapshot->id,
            $metadata,
        );
    }
}
