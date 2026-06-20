<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Field extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
    ];

    protected $appends = [
        'name',
    ];

    public function getNameAttribute(): string
    {
        return (string) $this->title;
    }

    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(FieldMatch::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }
}
