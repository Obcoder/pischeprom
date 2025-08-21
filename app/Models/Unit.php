<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    protected $with = [
        'buildings',
        'fields',
        'uris',
        'labels',
        'stages',
        'telephones',
        'emails.sendings',
    ];

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
