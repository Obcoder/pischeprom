<?php

namespace App\Models\Concerns;

use LogicException;

trait ImmutableScoreFactor
{
    public function initializeImmutableScoreFactor(): void
    {
        $this->fillable = array_merge($this->scoreFactorSubjectFillable ?? [], $this->scoreFactorFillable());
        $this->guarded = ['*'];
    }

    protected static function bootImmutableScoreFactor(): void
    {
        static::updating(static function (): never {
            throw new LogicException('Score factors are immutable.');
        });
        static::deleting(static function (): never {
            throw new LogicException('Score factors cannot be deleted.');
        });
    }

    protected function scoreFactorFillable(): array
    {
        return [
            'factor_code', 'polarity', 'normalized_state', 'weight', 'contribution', 'confidence',
            'evidence_type', 'evidence_reference', 'evidence_hash', 'evidence_at', 'status', 'safe_rationale',
        ];
    }

    protected function scoreFactorCasts(): array
    {
        return ['weight' => 'integer', 'contribution' => 'integer', 'confidence' => 'integer', 'evidence_at' => 'datetime'];
    }
}
