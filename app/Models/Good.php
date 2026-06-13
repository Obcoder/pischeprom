<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Good extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'denominator',
        'ava_image',
        'ava_thumb',
        'description',
        'slug',
        'is_published',
        'vat_rate_id',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'denominator' => 'float',
    ];

    protected static function booted()
    {
        static::saving(function (Good $good) {
            if (!$good->isDirty('name') && !$good->isDirty('slug') && filled($good->slug)) {
                return;
            }

            $source = $good->isDirty('slug') && filled($good->slug)
                ? $good->slug
                : $good->name;

            $good->slug = static::uniqueSlug((string) $source, $good->exists ? $good->id : null);
        });
    }

    private static function uniqueSlug(string $source, ?int $exceptId = null): string
    {
        $base = Str::slug($source) ?: Str::random(8);
        $slug = $base;
        $i = 2;

        while (
            Good::where('slug', $slug)
                ->when($exceptId, fn($q) => $q->where('id', '!=', $exceptId))
                ->exists()
        ) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeSearch($q, ?string $search)
    {
        return $q->when($search, function ($qq) use ($search) {
            $qq->where('name', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%");
        });
    }

    public function scopePublished($query, $published)
    {
        if ($published === null) {
            return $query; // ✅ "Все" — без фильтра
        }

        return $query->where('is_published', (bool) $published);
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    public function entityClassifications(): BelongsToMany
    {
        return $this->belongsToMany(EntityClassification::class, 'entity_classification_good')
            ->withTimestamps();
    }

    public function industries(): BelongsToMany
    {
        return $this->belongsToMany(Industry::class)
            ->withTimestamps();
    }

    public function purchases(): BelongsToMany
    {
        return $this->belongsToMany(Purchase::class, 'good_purchase')
            ->withPivot([
                            'id',
                            'quantity',
                            'measure_id',
                            'price',
                            'currency_id',
                            'total',
                            'created_at',
                            'updated_at',
                        ])
            ->withTimestamps();
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class)->latest();
    }

    public function sales(): BelongsToMany
    {
        return $this->belongsToMany(Sale::class)
            ->withPivot('price', 'quantity', 'measure_id')
            ->orderByDesc('date');
    }

    public function vatRate(): BelongsTo
    {
        return $this->belongsTo(VatRate::class);
    }

    public function mediaFolders(): HasMany
    {
        return $this->hasMany(GoodMediaFolder::class);
    }

    public function media(): HasMany
    {
        return $this->hasMany(GoodMedia::class)->orderBy('sort_order')->orderBy('id');
    }

    public function publishedMedia(): HasMany
    {
        return $this->hasMany(GoodMedia::class)
            ->where('is_published', true)
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(GoodMedia::class)
            ->where('type', 'image')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function videos(): HasMany
    {
        return $this->hasMany(GoodMedia::class)
            ->where('type', 'video')
            ->orderBy('sort_order')
            ->orderBy('id');
    }

    public function seo(): HasOne
    {
        return $this->hasOne(GoodSeo::class);
    }

    public function priceFormulas(): HasMany
    {
        return $this->hasMany(GoodPriceFormula::class);
    }

    public function priceCalculations(): HasMany
    {
        return $this->hasMany(GoodPriceCalculation::class)->latest();
    }

    public function priceTypeValues(): HasMany
    {
        return $this->hasMany(GoodPriceTypeValue::class);
    }
}
