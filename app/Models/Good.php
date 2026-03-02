<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class Good extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'denominator',
        'ava_image',
        'description',
        'slug',
        'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'denominator' => 'float',
    ];

    protected static function booted()
    {
        static::saving(function (Good $good) {
            if ($good->isDirty('name')) {
                $good->slug = Str::slug($good->name);
            }
        });
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeSearch($q, ?string $search)
    {
        return $q->when($search, function ($qq) use ($search) {
            $qq->where('name', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%");
        });
    }

    public function scopePublished($query, $published)
    {
        if ($published === null) {
            return $query; // ✅ "Все" — без фильтра
        }

        return $query->where('is_published', (bool) $published);
    }

    /*
    |--------------------------------------------------------------------------
    | Relations
    |--------------------------------------------------------------------------
    */

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(Price::class)->latest();
    }

    public function quotations(): HasMany
    {
        return $this->hasMany(Quotation::class)->latest();
    }

    public function sales(): BelongsToMany
    {
        return $this->belongsToMany(Sale::class)
            ->withPivot('price', 'quantity', 'measure_id')
            ->orderByDesc('date');
    }
}
