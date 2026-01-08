<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasFactory;

    // Scope для поиска по имени
    public function scopeSearch($query, $search)
    {
        if ($search) {
            return $query->where('name', 'like', "%$search%");
        }
        return $query;
    }

    // Scope для сортировки (по умолчанию по name)
    public function scopeOrdered($query)
    {
        return $query->orderBy('name');
    }

    public function products()
    {
        return $this->hasMany(Product::class)
            ->where('is_published', 1);
    }
}
