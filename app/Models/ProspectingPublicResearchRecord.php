<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class ProspectingPublicResearchRecord extends Model
{
    protected $fillable = [
        'prospecting_search_result_id', 'workflow_code', 'workflow_version', 'workflow_hash',
        'status', 'input_hash', 'output_hash', 'schema_valid', 'safe_summary',
        'activity_mentions', 'location_hints', 'product_mentions', 'provider_code',
        'model_id', 'safe_request_id', 'error_category', 'error_code', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'schema_valid' => 'boolean',
            'activity_mentions' => 'array',
            'location_hints' => 'array',
            'product_mentions' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(static function (): never {
            throw new LogicException('Research provenance is retained, not deleted.');
        });
    }

    public function searchResult(): BelongsTo
    {
        return $this->belongsTo(ProspectingSearchResult::class);
    }
}
