<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class YandexDirectAd extends Model
{
    use HasFactory;

    public const STATUS_DRAFT = 'draft';
    public const STATUS_READY = 'ready';
    public const STATUS_SYNCING = 'syncing';
    public const STATUS_SENT = 'sent';
    public const STATUS_MODERATION = 'moderation';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_SUSPENDED = 'suspended';
    public const STATUS_ERROR = 'error';

    protected $fillable = [
        'yandex_direct_ad_group_id',
        'good_id',
        'good_seo_id',
        'external_ad_id',
        'title_1',
        'title_2',
        'text',
        'href',
        'utm_template',
        'image_url',
        'status',
        'moderation_status',
        'validation_errors',
        'last_synced_at',
        'error_message',
    ];

    protected $casts = [
        'validation_errors' => 'array',
        'last_synced_at' => 'datetime',
    ];

    public function adGroup(): BelongsTo
    {
        return $this->belongsTo(YandexDirectAdGroup::class, 'yandex_direct_ad_group_id');
    }

    public function good(): BelongsTo
    {
        return $this->belongsTo(Good::class);
    }

    public function seo(): BelongsTo
    {
        return $this->belongsTo(GoodSeo::class, 'good_seo_id');
    }

}
