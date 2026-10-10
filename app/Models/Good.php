<?php

namespace App\Models;

use App\Services\Goods\GoodMeasurement;
use App\Services\Goods\GoodTradeCodes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class Good extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'denominator',
        'measure_id',
        'unit_weight_kg',
        'ava_image',
        'ava_thumb',
        'description',
        'slug',
        'incoming_code',
        'is_published',
        'vat_rate_id',
        'country_id',
        ...GoodTradeCodes::FIELDS,
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'denominator' => 'float',
        'measure_id' => 'integer',
        'unit_weight_kg' => 'float',
        'country_id' => 'integer',
    ];

    protected $with = ['measure'];

    protected $appends = ['measurement'];

    protected static function booted()
    {
        static::creating(function (Good $good): void {
            // Older imports omit the new field; new goods default to the price
            // editor's historical base unit. Explicit null remains unconfigured.
            if (! array_key_exists('measure_id', $good->getAttributes()) && Schema::hasColumn('goods', 'measure_id')) {
                $good->measure_id = Measure::firstOrCreate(['name' => 'кг'])->id;
            }
        });

        static::saving(function (Good $good) {
            if ($good->isDirty('measure_id')) {
                $good->unsetRelation('measure');
            }
            if (($good->isDirty('measure_id') || $good->isDirty('unit_weight_kg'))
                && $good->measure_id && app(GoodMeasurement::class)->kilograms($good->measure?->name) !== null) {
                $good->unit_weight_kg = null;
            }
            $good->forceFill(GoodTradeCodes::normalize($good->getAttributes()));

            // A saved address belongs to the product, independently of its name.
            if (! $good->isDirty('slug') && filled($good->slug)) {
                return;
            }

            $source = $good->isDirty('slug') && filled($good->slug)
                ? $good->slug
                : $good->name;

            $good->slug = static::uniqueSlug((string) $source, $good->exists ? $good->id : null);
        });

        static::saved(function (Good $good) {
            $good->synchronizeSeoAddress($good->getOriginal('slug'));
        });
    }

    public function synchronizeSeoAddress(?string $previousSlug = null): void
    {
        // Earlier data migrations also save products before URL history exists.
        if (! Schema::hasTable('good_seos') || ! Schema::hasTable('good_url_aliases')) {
            return;
        }

        DB::transaction(function () use ($previousSlug): void {
            // Both product saves and SEO writes serialize around the current
            // primary address, including when their model snapshots are stale.
            $current = static::whereKey($this->id)->lockForUpdate()->firstOrFail();
            $seo = $current->seo()->first();
            $previousSlugs = [$previousSlug, $this->slug, $seo?->slug_override];
            foreach (array_unique(array_filter($previousSlugs, fn ($slug) => filled($slug))) as $slug) {
                if ($slug !== $current->slug) {
                    GoodUrlAlias::firstOrCreate(['slug' => $slug], ['good_id' => $current->id]);
                }
            }

            $seo?->fill([
                'slug_override' => $current->slug,
                'canonical_url' => route('public.goods.show', ['good' => $current->slug ?: (string) $current->id]),
            ]);
            if ($seo?->isDirty(['slug_override', 'canonical_url'])) {
                $seo->save();
            }
        });
        $this->unsetRelation('seo');
    }

    private static function uniqueSlug(string $source, ?int $exceptId = null): string
    {
        $base = Str::slug($source) ?: Str::random(8);
        $slug = $base;
        $i = 2;
        $seoAliasesAvailable = Schema::hasTable('good_seos') && Schema::hasColumn('good_seos', 'slug_override');
        $urlAliasesAvailable = Schema::hasTable('good_url_aliases');

        while (
            Good::where('slug', $slug)
                ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
                ->exists()
            || ($seoAliasesAvailable && GoodSeo::where('slug_override', $slug)
                ->when($exceptId, fn ($q) => $q->where('good_id', '!=', $exceptId))->exists())
            || ($urlAliasesAvailable && GoodUrlAlias::where('slug', $slug)
                ->when($exceptId, fn ($q) => $q->where('good_id', '!=', $exceptId))->exists())
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

    public function measure(): BelongsTo
    {
        return $this->belongsTo(Measure::class);
    }

    public function measurement(): array
    {
        return app(GoodMeasurement::class)->for($this);
    }

    public function getMeasurementAttribute(): array
    {
        return $this->measurement();
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

    public function fields(): BelongsToMany
    {
        return $this->belongsToMany(Field::class)
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

    public function unitGoodMatches(): HasMany
    {
        return $this->hasMany(UnitGoodMatch::class);
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

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
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

    public function yandexDirectAds(): HasMany
    {
        return $this->hasMany(YandexDirectAd::class);
    }

    public function yandexDirectKeywords(): HasMany
    {
        return $this->hasMany(YandexDirectKeyword::class);
    }

    public function yandexDirectDailyStats(): HasMany
    {
        return $this->hasMany(YandexDirectDailyStat::class);
    }

    public function directLaunchSessions(): HasMany
    {
        return $this->hasMany(DirectLaunchSession::class);
    }

    public function yandexDirectAiDecisions(): HasMany
    {
        return $this->hasMany(YandexDirectAiDecision::class);
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(GoodStockMovement::class);
    }

    public function stockAvailability(): HasOne
    {
        return $this->hasOne(GoodStockAvailability::class);
    }

    public function stockAlerts(): HasMany
    {
        return $this->hasMany(GoodStockAlert::class);
    }

    public function avitoListingLinks(): HasMany
    {
        return $this->hasMany(AvitoListingGoodLink::class);
    }

    public function avitoPublications(): HasMany
    {
        return $this->hasMany(AvitoPublication::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class, 'order_items')
            ->withPivot([
                'id',
                'quantity',
                'price_gross',
                'currency_code',
                'line_total',
            ])
            ->withTimestamps();
    }
}
