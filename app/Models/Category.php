<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'image',
        'description',
        'slug',
    ];

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, function (Builder $q) use ($search) {
            $q->where('name', 'like', "%{$search}%");
        });
    }

    public function scopeOrdered(Builder $query, string $column = 'name', string $direction = 'asc'): Builder
    {
        return $query->orderBy($column, $direction);
    }

    public function products()
    {
        return $this->hasMany(Product::class)
            ->where('is_published', 1);
    }
}
