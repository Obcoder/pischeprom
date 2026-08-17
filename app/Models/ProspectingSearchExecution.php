<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use LogicException;

class ProspectingSearchExecution extends Model
{
    protected $fillable = [
        'public_id', 'prospecting_search_job_id', 'prospecting_search_query_id', 'initiated_by',
        'profile_code', 'provider_code', 'request_hash', 'plan_hash', 'status', 'attempt',
        'request_count', 'result_count', 'duplicate_count', 'blocked_result_count',
        'duration_ms', 'safe_request_id',
        'error_category', 'error_code', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(static function (self $execution): void {
            $execution->public_id ??= (string) Str::uuid();
        });
        static::deleting(static function (): never {
            throw new LogicException('Search execution audit rows cannot be deleted.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(ProspectingSearchJob::class, 'prospecting_search_job_id');
    }

    public function searchQuery(): BelongsTo
    {
        return $this->belongsTo(ProspectingSearchQuery::class, 'prospecting_search_query_id');
    }

    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }

    public function results(): HasMany
    {
        return $this->hasMany(ProspectingSearchResult::class);
    }

    public function usage(): HasOne
    {
        return $this->hasOne(ProspectingSearchUsageRecord::class);
    }
}
