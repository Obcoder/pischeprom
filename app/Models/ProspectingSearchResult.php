<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;
use LogicException;

class ProspectingSearchResult extends Model
{
    protected $fillable = [
        'public_id', 'prospecting_search_execution_id', 'prospecting_search_job_id',
        'prospecting_search_query_id', 'rank', 'result_type', 'title', 'snippet', 'url',
        'canonical_url', 'url_hash', 'registrable_domain', 'domain_hash', 'result_hash',
        'duplicate_of_id', 'prospecting_candidate_id', 'trust_level',
        'instruction_authority', 'fetch_status', 'research_status',
    ];

    protected static function booted(): void
    {
        static::creating(static function (self $result): void {
            $result->public_id ??= (string) Str::uuid();
        });
        static::deleting(static function (): never {
            throw new LogicException('Search result provenance rows cannot be deleted.');
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function execution(): BelongsTo
    {
        return $this->belongsTo(ProspectingSearchExecution::class, 'prospecting_search_execution_id');
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(ProspectingSearchJob::class, 'prospecting_search_job_id');
    }

    public function searchQuery(): BelongsTo
    {
        return $this->belongsTo(ProspectingSearchQuery::class, 'prospecting_search_query_id');
    }

    public function duplicateOf(): BelongsTo
    {
        return $this->belongsTo(self::class, 'duplicate_of_id');
    }

    public function candidate(): BelongsTo
    {
        return $this->belongsTo(ProspectingCandidate::class, 'prospecting_candidate_id');
    }

    public function publicFetch(): HasOne
    {
        return $this->hasOne(ProspectingPublicFetch::class);
    }

    public function research(): HasOne
    {
        return $this->hasOne(ProspectingPublicResearchRecord::class);
    }
}
