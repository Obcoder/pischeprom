<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Good extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'ava_image',
        'denominator',
        'description',
        'slug',
    ];

    protected static function boot()
    {
        parent::boot();
        static::saving(function ($model) {
            $model->slug = Str::slug($model->name);
        });
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }
    public function prices(): HasMany
    {
        return $this->hasMany(Price::class)
            ->orderBy('created_at', 'desc');
    }
    public function sales(): BelongsToMany
    {
        return $this->belongsToMany(Sale::class)
            ->withPivot('price', 'quantity', 'measure_id');
    }
}
