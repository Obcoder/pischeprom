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
        'include_in_sitemap',
        'include_in_yandex_feed',

        'index_now_sent_at',
        'last_generated_at',

        'yandex_direct_title_1',
        'yandex_direct_title_2',
        'yandex_direct_text',
        'utm_template',

        'availability_status',
        'min_order',
        'delivery_note',
        'payment_note',
        'faq',
    ];

    protected $casts = [
        'semantic_core' => 'array',
        'keywords' => 'array',
        'search_queries' => 'array',
        'structured_data' => 'array',
        'faq' => 'array',

        'is_active' => 'boolean',
        'include_in_sitemap' => 'boolean',
        'include_in_yandex_feed' => 'boolean',

        'index_now_sent_at' => 'datetime',
        'last_generated_at' => 'datetime',
    ];

    public function good(): BelongsTo
    {
        return $this->belongsTo(Good::class);
    }
}
