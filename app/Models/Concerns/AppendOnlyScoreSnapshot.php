<?php

namespace App\Models\Concerns;

use LogicException;

trait AppendOnlyScoreSnapshot
{
    public function initializeAppendOnlyScoreSnapshot(): void
    {
        $this->fillable = array_merge($this->scoreSubjectFillable ?? [], $this->scoreSnapshotFillable());
        $this->guarded = ['*'];
    }

    protected static function bootAppendOnlyScoreSnapshot(): void
    {
        static::updating(static function ($snapshot): void {
            $allowed = ['stale_at', 'stale_reason_code', 'superseded_at', 'superseded_by_snapshot_id', 'updated_at'];
            $dirty = array_keys($snapshot->getDirty());
            if (array_diff($dirty, $allowed) !== []) {
                throw new LogicException('Score snapshots are append-only; create a new snapshot.');
            }
        });
        static::deleting(static function (): never {
            throw new LogicException('Score snapshots cannot be deleted.');
        });
    }

    protected function scoreSnapshotCasts(): array
    {
        return [
            'computed_score' => 'integer',
            'effective_score' => 'integer',
            'confidence' => 'integer',
            'override_expires_at' => 'datetime',
            'reviewed_at' => 'datetime',
            'stale_at' => 'datetime',
            'superseded_at' => 'datetime',
        ];
    }

    protected function scoreSnapshotFillable(): array
    {
        return [
            'computed_score', 'effective_score', 'confidence', 'band', 'eligibility', 'review_status',
            'next_best_action', 'definition_code', 'definition_version', 'definition_hash', 'input_hash',
            'evidence_hash', 'idempotency_key', 'origin', 'base_snapshot_id', 'override_reason_code',
            'override_safe_note', 'override_expires_at', 'computed_by', 'reviewed_by', 'reviewed_at',
            'stale_at', 'stale_reason_code', 'superseded_at', 'superseded_by_snapshot_id',
        ];
    }
}
