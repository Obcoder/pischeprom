<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class City extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'wiki',
        'wiki_title',
        'wiki_summary',
        'wiki_thumbnail',
        'wiki_page_id',
        'wiki_lang',
        'population',
        'region_id',
        'yandexmapsgeo',
        'twogis',
        'latitude',
        'longitude',
    ];

    protected $casts = [
        'region_id' => 'integer',
        'population' => 'integer',
        'wiki_page_id' => 'integer',
        'latitude' => 'float',
        'longitude' => 'float',
    ];

    protected $with = [
        'region',
    ];

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function buildings(): HasMany
    {
        return $this->hasMany(Building::class, 'city_id', 'id');
    }

    public function entities(): BelongsToMany
    {
        return $this->belongsToMany(Entity::class);
    }

    public function region(): BelongsTo
    {
        return $this->belongsTo(Region::class)
            ->withDefault();
    }

    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class)
            ->using(city_unit::class)
            ->withTimestamps();
    }

    public function populations(): HasMany
    {
        return $this->hasMany(CityPopulation::class)
            ->orderByDesc('year');
    }

    public function latestPopulation(): HasOne
    {
        return $this->hasOne(CityPopulation::class)
            ->latestOfMany('year');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeForCitiesTable(Builder $query): Builder
    {
        return $query->with([
                                'region.country',
                                'latestPopulation',
                            ]);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        $search = trim((string) $search);

        if ($search === '') {
            return $query;
        }

        return $query->where(function (Builder $query) use ($search) {
            $query
                ->where('name', 'like', "%{$search}%")
                ->orWhere('wiki_title', 'like', "%{$search}%")
                ->orWhere('wiki_summary', 'like', "%{$search}%")
                ->orWhereHas('region', function (Builder $query) use ($search) {
                    $query->where('name', 'like', "%{$search}%");
                })
                ->orWhereHas('region.country', function (Builder $query) use ($search) {
                    $query->where('name', 'like', "%{$search}%");
                });
        });
    }

    public function scopeFilterRegion(Builder $query, mixed $regionId): Builder
    {
        if (empty($regionId)) {
            return $query;
        }

        return $query->where('region_id', $regionId);
    }

    public function scopeFilterCountry(Builder $query, mixed $countryId): Builder
    {
        if (empty($countryId)) {
            return $query;
        }

        return $query->whereHas('region', function (Builder $query) use ($countryId) {
            $query->where('country_id', $countryId);
        });
    }

    public function scopeOrderByLatestPopulation(Builder $query, string $direction = 'desc'): Builder
    {
        $direction = strtolower($direction) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy(
            CityPopulation::select('population')
                ->whereColumn('city_populations.city_id', 'cities.id')
                ->orderByDesc('year')
                ->limit(1),
            $direction
        );
    }

    public function scopeFilterUnit(Builder $query, mixed $unitId): Builder
    {
        if (empty($unitId)) {
            return $query;
        }

        return $query->whereHas('units', function (Builder $query) use ($unitId) {
            $query->where('units.id', $unitId);
        });
    }

    public function scopeFilterEntity(Builder $query, mixed $entityId): Builder
    {
        if (empty($entityId)) {
            return $query;
        }

        return $query->whereHas('entities', function (Builder $query) use ($entityId) {
            $query->where('entities.id', $entityId);
        });
    }

    public function scopeFilterHasBuildings(Builder $query, mixed $value): Builder
    {
        if ($value === null || $value === '') {
            return $query;
        }

        $hasBuildings = filter_var($value, FILTER_VALIDATE_BOOLEAN);

        return $hasBuildings
            ? $query->has('buildings')
            : $query->doesntHave('buildings');
    }
}
