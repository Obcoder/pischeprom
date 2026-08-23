<?php

namespace App\Domain\AiSales\Scoring;

use App\Domain\AiSales\Enums\ScoreSnapshotOrigin;
use App\Models\UnitBusinessContext;
use App\Models\UnitGoodFitSnapshot;
use App\Models\UnitGoodMatch;
use App\Models\UnitProductMatch;
use App\Models\UnitProductRelevanceSnapshot;
use App\Models\UnitProspectPrioritySnapshot;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;

final class ProspectingScoreSnapshotWriter
{
    public function write(ScoreResult $result, ?int $actorId = null): Model
    {
        return DB::transaction(function () use ($result, $actorId): Model {
            return match ($result->input->level) {
                'product_relevance' => $this->writeProduct($result, $actorId),
                'good_fit' => $this->writeGood($result, $actorId),
                'prospect_priority' => $this->writePriority($result, $actorId),
                default => throw new LogicException('Unknown score snapshot level.'),
            };
        }, 3);
    }

    private function writeProduct(ScoreResult $result, ?int $actorId): UnitProductRelevanceSnapshot
    {
        $subject = UnitProductMatch::query()->without(['product'])->lockForUpdate()->findOrFail($result->input->subject['unit_product_match_id']);
        $this->assertBindings($subject, $result, 'unit_product_match_id');
        [$key, $existing] = $this->resolvedKey(UnitProductRelevanceSnapshot::class, 'unit_product_match_id', $subject->id, $result);
        if ($existing) {
            return $existing->load('factors');
        }
        $snapshot = UnitProductRelevanceSnapshot::query()->create([
            'unit_product_match_id' => $subject->id,
            'unit_id' => $subject->unit_id,
            'unit_business_context_id' => $subject->unit_business_context_id,
            ...$this->attributes($result, $key, $actorId),
        ]);
        $this->persistFactors($snapshot, $result, 'unit_product_relevance_snapshot_id');
        $this->supersede(UnitProductRelevanceSnapshot::class, 'unit_product_match_id', $subject->id, $snapshot);

        return $snapshot->load('factors');
    }

    private function writeGood(ScoreResult $result, ?int $actorId): UnitGoodFitSnapshot
    {
        $subject = UnitGoodMatch::query()->without(['good'])->lockForUpdate()->findOrFail($result->input->subject['unit_good_match_id']);
        $this->assertBindings($subject, $result, 'unit_good_match_id');
        if ((int) $subject->unit_product_match_id !== (int) $result->input->subject['unit_product_match_id']) {
            throw new LogicException('Good score input has an invalid Product binding.');
        }
        [$key, $existing] = $this->resolvedKey(UnitGoodFitSnapshot::class, 'unit_good_match_id', $subject->id, $result);
        if ($existing) {
            return $existing->load('factors');
        }
        $snapshot = UnitGoodFitSnapshot::query()->create([
            'unit_good_match_id' => $subject->id,
            'unit_product_match_id' => $subject->unit_product_match_id,
            'unit_id' => $subject->unit_id,
            'unit_business_context_id' => $subject->unit_business_context_id,
            ...$this->attributes($result, $key, $actorId),
        ]);
        $this->persistFactors($snapshot, $result, 'unit_good_fit_snapshot_id');
        $this->supersede(UnitGoodFitSnapshot::class, 'unit_good_match_id', $subject->id, $snapshot);

        return $snapshot->load('factors');
    }

    private function writePriority(ScoreResult $result, ?int $actorId): UnitProspectPrioritySnapshot
    {
        $subject = UnitBusinessContext::query()->lockForUpdate()->findOrFail($result->input->subject['unit_business_context_id']);
        if ((int) $subject->unit_id !== (int) $result->input->subject['unit_id']) {
            throw new LogicException('Priority score input has an invalid Unit binding.');
        }
        [$key, $existing] = $this->resolvedKey(UnitProspectPrioritySnapshot::class, 'unit_business_context_id', $subject->id, $result);
        if ($existing) {
            return $existing->load('factors');
        }
        $snapshot = UnitProspectPrioritySnapshot::query()->create([
            'unit_business_context_id' => $subject->id,
            'unit_id' => $subject->unit_id,
            ...$this->attributes($result, $key, $actorId),
        ]);
        $this->persistFactors($snapshot, $result, 'unit_prospect_priority_snapshot_id');
        $this->supersede(UnitProspectPrioritySnapshot::class, 'unit_business_context_id', $subject->id, $snapshot);

        return $snapshot->load('factors');
    }

    private function attributes(ScoreResult $result, string $key, ?int $actorId): array
    {
        return [
            'computed_score' => $result->computedScore,
            'effective_score' => $result->effectiveScore,
            'confidence' => $result->confidence,
            'band' => $result->band->value,
            'eligibility' => $result->eligibility->value,
            'review_status' => $result->reviewStatus->value,
            'next_best_action' => $result->nextBestAction,
            'definition_code' => $result->definition->code,
            'definition_version' => $result->definition->version,
            'definition_hash' => $result->definition->hash,
            'input_hash' => $result->input->inputHash,
            'evidence_hash' => $result->input->evidenceHash,
            'idempotency_key' => $key,
            'origin' => ScoreSnapshotOrigin::Deterministic->value,
            'computed_by' => $actorId,
        ];
    }

    private function persistFactors(Model $snapshot, ScoreResult $result, string $foreignKey): void
    {
        $now = now();
        $rows = array_map(static fn (ScoreFactorResult $factor): array => [
            $foreignKey => $snapshot->id,
            ...$factor->safeArray(),
            'created_at' => $now,
            'updated_at' => $now,
        ], $result->factors);
        $snapshot->factors()->getModel()->newQuery()->insert($rows);
    }

    private function supersede(string $model, string $subjectColumn, int $subjectId, Model $new): void
    {
        $previous = $model::query()->where($subjectColumn, $subjectId)
            ->where('id', '!=', $new->id)->whereNull('superseded_at')->orderByDesc('id')->first();
        if ($previous) {
            $previous->update(['superseded_at' => now(), 'superseded_by_snapshot_id' => $new->id]);
        }
    }

    private function assertBindings(Model $subject, ScoreResult $result, string $idKey): void
    {
        if ((int) $subject->id !== (int) $result->input->subject[$idKey]
            || (int) $subject->unit_id !== (int) $result->input->subject['unit_id']
            || (int) $subject->unit_business_context_id !== (int) $result->input->subject['unit_business_context_id']) {
            throw new LogicException('Score input has an invalid Unit/context binding.');
        }
    }

    private function key(ScoreResult $result, ScoreSnapshotOrigin $origin): string
    {
        return hash('sha256', implode('|', [
            $result->definition->hash,
            $result->input->inputHash,
            $result->input->evidenceHash,
            $origin->value,
            json_encode($result->input->subject, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
        ]));
    }

    private function resolvedKey(string $model, string $subjectColumn, int $subjectId, ScoreResult $result): array
    {
        $baseKey = $this->key($result, ScoreSnapshotOrigin::Deterministic);
        $currentMatch = $model::query()->where($subjectColumn, $subjectId)
            ->where('origin', ScoreSnapshotOrigin::Deterministic->value)
            ->where('definition_hash', $result->definition->hash)
            ->where('input_hash', $result->input->inputHash)
            ->where('evidence_hash', $result->input->evidenceHash)
            ->whereNull('stale_at')->whereNull('superseded_at')->first();
        if ($currentMatch) {
            return [$currentMatch->idempotency_key, $currentMatch];
        }

        $predecessor = $model::query()->where($subjectColumn, $subjectId)
            ->whereNull('superseded_at')->orderByDesc('id')->first();
        if ($predecessor === null) {
            return [$baseKey, null];
        }

        $resumedKey = hash('sha256', $baseKey.'|after|'.$predecessor->id);

        return [$resumedKey, $model::query()->where('idempotency_key', $resumedKey)->first()];
    }
}
