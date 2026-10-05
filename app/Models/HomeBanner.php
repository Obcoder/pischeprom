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

    public const POSITIONS = [
        'left top', 'center top', 'right top',
        'left center', 'center center', 'right center',
        'left bottom', 'center bottom', 'right bottom',
    ];

    protected $attributes = [
        'size' => 'compact',
        'is_published' => false,
        'show_on_desktop' => true,
        'show_on_mobile' => true,
        'sort_order' => 500,
        'content_mode' => 'image',
        'image_fit' => 'contain',
        'image_position' => 'center center',
        'mobile_image_fit' => 'contain',
        'mobile_image_position' => 'center center',
        'text_align' => 'left',
        'vertical_align' => 'center',
        'open_in_new_tab' => false,
    ];

    protected $appends = ['published_status'];

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
        'slot_number',
        'content_mode',
        'image_fit',
        'image_position',
        'mobile_image_fit',
        'mobile_image_position',
        'text_align',
        'vertical_align',
        'alt_text',
        'open_in_new_tab',
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
        'slot_number' => 'integer',
        'open_in_new_tab' => 'boolean',
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
                    ->orWhere('ends_at', '>', now());
            });
    }

    public function getPublishedStatusAttribute(): string
    {
        if (! $this->is_published) {
            return 'draft';
        }
        if (! $this->slot_number) {
            return 'unassigned';
        }
        if (! $this->show_on_desktop && ! $this->show_on_mobile) {
            return 'hidden';
        }
        if ($this->ends_at && $this->ends_at->lte(now())) {
            return 'expired';
        }
        if ($this->starts_at && $this->starts_at->gt(now())) {
            return 'scheduled';
        }

        return 'active';
    }
}
