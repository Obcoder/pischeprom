<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Quotation;

class Unit extends Model
{
    use HasFactory;

    public const DETAIL_RELATIONS = [
        'fields',
        'labels',
        'telephones',
        'uris',

        'entities.classification',
        'entities.telephones',
        'entities.emails',
        'entities.sales',

        'buildings.city',
        'buildings.buildingType',
        'consumptions.product',
        'consumptions.measure',
        'manufactures',

        'emails',
        'emails.sendings',

        'stages',
        'supplierPipelineCards.pipeline',
        'supplierPipelineCards.stage',
        'quotations.good',
        'quotations.currency',
        'quotations.measure',
        'industries',
        'cities.region',
    ];

    protected $fillable = [
        'name',
        'is_customer',
        'is_supplier',
    ];

    protected $casts = [
        'is_customer' => 'boolean',
        'is_supplier' => 'boolean',
    ];

    protected $with = [
        'fields',
        'labels',
        'telephones',
        'uris',
    ];

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, fn ($q) =>
        $q->where('name', 'like', "%{$search}%")
        );
    }

    public function scopeForGood(Builder $query, int $goodId): Builder
    {
        return $query->whereHas('quotations', fn ($q) =>
        $q->where('good_id', $goodId)
        );
    }

    public function scopeLimitIfPresent(Builder $query, ?int $limit): Builder
    {
        return $query->when($limit, fn ($q) => $q->limit($limit));
    }

    public function buildings()
    {
        return $this->belongsToMany(Building::class)
            ->using(building_unit::class);
    }

    public function cities(): BelongsToMany
    {
        return $this->belongsToMany(City::class)
            ->using(city_unit::class)
            ->withTimestamps();
    }

    public function consumptions(): HasMany
    {
        return $this->hasMany(Consumption::class)
            ->orderByDesc('created_at');
    }

    public function emails(): BelongsToMany
    {
        return $this->belongsToMany(Email::class);
    }

    public function entities()
    {
        return $this->belongsToMany(Entity::class)
            ->using(entity_unit::class);
    }

    public function fields(): BelongsToMany
    {
        return $this->belongsToMany(Field::class);
    }

    public function fieldProducerMatches(): HasMany
    {
        return $this->hasMany(FieldMatch::class, 'producer_unit_id');
    }

    public function fieldConsumerMatches(): HasMany
    {
        return $this->hasMany(FieldMatch::class, 'consumer_unit_id');
    }

    public function industries(): BelongsToMany
    {
        return $this->belongsToMany(Industry::class)
            ->withPivot('is_primary')
            ->withTimestamps();
    }

    public function classifications(): BelongsToMany
    {
        return $this->belongsToMany(EntityClassification::class, 'entity_classification_unit')
            ->withTimestamps();
    }

    public function primaryIndustry()
    {
        return $this->belongsToMany(Industry::class)
            ->wherePivot('is_primary', true)
            ->withPivot('is_primary')
            ->first();
    }

    public function labels()
    {
        return $this->belongsToMany(Label::class)
            ->using(label_unit::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }

    public function phoneCalls(): HasMany
    {
        return $this->hasMany(PhoneCall::class);
    }

    public function manufactures(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'manufacturers', 'unit_id', 'product_id');
    }

    public function products()
    {
        return $this->belongsToMany(Product::class)
            ->using(product_unit::class)
            ->withPivot('action_id')
            ->withTimestamps();
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class);
    }

    public function stages()
    {
        return $this->belongsToMany(Stage::class)
            ->using(stage_unit::class)
            ->withPivot('startDate', 'endDate', 'isActive');
    }

    public function supplierPipelineCards(): HasMany
    {
        return $this->hasMany(SupplierPipelineCard::class);
    }

    public function telephones()
    {
        return $this->belongsToMany(Telephone::class)
            ->using(telephone_unit::class);
    }

    public function uris(): BelongsToMany
    {
        return $this->belongsToMany(Uri::class)
            ->using(unit_uri::class);
    }
}
