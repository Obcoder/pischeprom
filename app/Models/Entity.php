<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Entity extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'entity_classification_id',
        'INN',
        'OGRN',
        'country_id',
    ];

    protected $with = [
        'buildings',
        'classification',
        'country',
    ];

    public function buildings(): BelongsToMany
    {
        return $this->belongsToMany(Building::class, 'building_entities');
    }
    public function chats(): BelongsToMany
    {
        return $this->belongsToMany(Chat::class);
    }
    public function cities(): BelongsToMany
    {
        return $this->belongsToMany(City::class);
    }
    public function classification()
    {
        return $this->belongsTo(EntityClassification::class , 'entity_classification_id')
            ->withDefault();
    }
    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }
    public function telephones(): BelongsToMany
    {
        return $this->belongsToMany(Telephone::class);
    }
    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class);
    }
    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class)
            ->orderByDesc('date');
    }
}
