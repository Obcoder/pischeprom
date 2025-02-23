<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
    ];

    protected $with = [
        'uris',
        'labels',
    ];

    public function uris()
    {
        return $this->belongsToMany(Uri::class)
            ->using(unit_uri::class);
    }

    public function labels()
    {
        return $this->belongsToMany(Label::class)
            ->using(label_unit::class);
    }
    public function stages()
    {
        return $this->belongsToMany(Stage::class)
            ->using(stage_unit::class)
            ->withPivot('startDate', 'endDate');
    }
    public function consumptions(){
        return $this->hasMany(Consumption::class);
    }
    public function products()
    {
        return $this->belongsToMany(Product::class)
            ->using(product_unit::class)
            ->withPivot('action_id')
            ->withTimestamps();
    }
    public function entities()
    {
        return $this->belongsToMany(Entity::class)
            ->using(entity_unit::class);
    }
}
