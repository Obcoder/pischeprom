<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodSeo extends Model
{
    protected $fillable = [
        'good_id',
        'meta_title',
        'meta_description',
        'h1',
        'slug_override',
        'canonical_url',
        'robots',
        'og_title',
        'og_description',
        'og_image',
        'twitter_title',
        'twitter_description',
        'twitter_image',
        'short_seo_text',
        'seo_text',
        'semantic_core',
        'keywords',
        'search_queries',
        'structured_data',
        'focus_keyword',
        'breadcrumbs_title',
        'is_active',
    ];

    protected $casts = [
        'semantic_core' => 'array',
        'keywords' => 'array',
        'search_queries' => 'array',
        'structured_data' => 'array',
        'is_active' => 'boolean',
    ];

    public function good(): BelongsTo
    {
        return $this->belongsTo(Good::class);
    }
}
