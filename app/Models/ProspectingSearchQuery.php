<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class ProspectingSearchQuery extends Model
{
    protected $fillable = [
        'prospecting_search_job_id', 'sequence', 'template_code', 'template_version',
        'template_hash', 'product_scope_hash', 'plan_hash', 'plan_status',
        'plan_approved_by', 'plan_approved_at', 'query_hash', 'safe_display_query', 'language',
        'geography', 'industry_intent', 'status', 'result_count', 'candidate_count',
        'search_provider_reference', 'executed_at',
    ];

    protected function casts(): array
    {
        return [
            'plan_approved_at' => 'datetime',
            'executed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(static function (self $query): void {
            if ($query->executed_at !== null || $query->search_provider_reference !== null) {
                throw new LogicException('Stage 08 search query history cannot represent live execution.');
            }
        });
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(ProspectingSearchJob::class, 'prospecting_search_job_id');
    }

    public function candidates(): HasMany
    {
        return $this->hasMany(ProspectingCandidate::class);
    }

    public function executions(): HasMany
    {
        return $this->hasMany(ProspectingSearchExecution::class);
    }

    public function searchResults(): HasMany
    {
        return $this->hasMany(ProspectingSearchResult::class);
    }

    public function planApprover(): BelongsTo
    {
        return $this->belongsTo(User::class, 'plan_approved_by');
    }
}
