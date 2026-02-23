<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    protected $with = [
        'emails.sendings',
        'fields',
        'labels',
        'telephones',
        'uris',
    ];

    //      S C O P E S
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
    // E N D  S C O P E S

    public function buildings()
    {
        return $this->belongsToMany(Building::class)
            ->using(building_unit::class);
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
    public function industries(): BelongsToMany
    {
        return $this->belongsToMany(Industry::class)
            ->withPivot('is_primary')
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

    //  Q U O T A T I O N S
    public function quotations(): BelongsToMany
    {
        return $this->belongsToMany(Quotation::class);
    }
    public function stages()
    {
        return $this->belongsToMany(Stage::class)
            ->using(stage_unit::class)
            ->withPivot('startDate', 'endDate', 'isActive');
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
