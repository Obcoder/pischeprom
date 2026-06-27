<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HomeBanner extends Model
{
    use HasFactory;

    public const SIZES = [
        'wide',
        'standard',
        'compact',
    ];

    protected $fillable = [
        'title',
        'eyebrow',
        'subtitle',
        'description',
        'image_url',
        'mobile_image_url',
        'cta_label',
        'cta_url',
        'good_id',
        'product_id',
        'category_id',
        'size',
        'is_published',
        'show_on_desktop',
        'show_on_mobile',
        'sort_order',
        'background_color',
        'text_color',
        'accent_color',
        'starts_at',
        'ends_at',
    ];

    protected $casts = [
        'good_id' => 'integer',
        'product_id' => 'integer',
        'category_id' => 'integer',
        'is_published' => 'boolean',
        'show_on_desktop' => 'boolean',
        'show_on_mobile' => 'boolean',
        'sort_order' => 'integer',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
    ];

    public function good(): BelongsTo
    {
        return $this->belongsTo(Good::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query
            ->where(function (Builder $dateQuery): void {
                $dateQuery
                    ->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', now());
            })
            ->where(function (Builder $dateQuery): void {
                $dateQuery
                    ->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', now());
            });
    }
}
