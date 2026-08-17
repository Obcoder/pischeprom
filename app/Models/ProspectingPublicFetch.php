<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ProspectingPublicFetch extends Model
{
    protected $fillable = [
        'prospecting_search_result_id', 'status', 'final_url', 'final_url_hash',
        'registrable_domain', 'content_type', 'byte_count', 'duration_ms', 'page_title',
        'meta_description', 'headings', 'text_excerpt', 'same_domain_links',
        'protected_channels', 'channel_count', 'content_hash', 'trust_level',
        'instruction_authority', 'robots_status', 'error_category', 'error_code', 'fetched_at',
    ];

    protected function casts(): array
    {
        return [
            'headings' => 'array',
            'same_domain_links' => 'array',
            'protected_channels' => 'encrypted:array',
            'fetched_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new LogicException('Public fetch provenance is retained, not deleted.');
        });
    }

    public function searchResult(): BelongsTo
    {
        return $this->belongsTo(ProspectingSearchResult::class);
    }
}
