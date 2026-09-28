<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Building extends Model
{
    use HasFactory;

    protected $fillable = [
        'city_id',
        'building_type_id',
        'address',
        'postcode',
    ];

    protected $casts = [
        'city_id' => 'integer',
        'building_type_id' => 'integer',
    ];

    protected $with = [
        'city',
        'buildingType',
        'apartments',
    ];

    protected $appends = ['apartment'];

    public function apartments(): HasMany
    {
        return $this->hasMany(Apartment::class)->orderBy('number')->orderBy('id');
    }

    public function getApartmentAttribute(): ?Apartment
    {
        $id = $this->pivot?->apartment_id;

        return $id ? $this->apartments->firstWhere('id', (int) $id) : null;
    }

    public function getAddressWithApartmentAttribute(): string
    {
        return collect([$this->address, $this->apartment?->label])->filter()->implode(', ');
    }

    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class)
            ->withDefault();
    }

    public function buildingType(): BelongsTo
    {
        return $this->belongsTo(BuildingType::class)
            ->withDefault();
    }

    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class)->withPivot('apartment_id');
    }

    public function entities(): BelongsToMany
    {
        return $this->belongsToMany(Entity::class, 'building_entities')
            ->withPivot('apartment_id')
            ->withTimestamps();
    }

    public function orders(): BelongsToMany
    {
        return $this->belongsToMany(Order::class)
            ->withPivot(['role', 'position', 'apartment_id'])
            ->withTimestamps();
    }
}
