<?php

namespace App\Models;

use App\Domain\AiSales\Outreach\Enums\OutreachFollowUpStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class OutreachFollowUpPlan extends Model
{
    protected $fillable = [
        'public_id', 'outreach_dispatch_id', 'status', 'max_follow_ups', 'earliest_at',
        'recommendation_code', 'cancellation_reason', 'recommendation_hash',
        'created_by', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return ['status' => OutreachFollowUpStatus::class, 'earliest_at' => 'datetime', 'reviewed_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new LogicException('Outreach follow-up plans use cancellation, not deletion.');
        });
    }

    public function dispatch(): BelongsTo
    {
        return $this->belongsTo(OutreachDispatch::class, 'outreach_dispatch_id');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(OutreachFollowUpStep::class);
    }
}
