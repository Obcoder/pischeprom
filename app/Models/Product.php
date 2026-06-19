<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    public const TRANSLATION_COLUMNS = [
        'rus',
        'eng',
        'zh',
        'es',
        'ar',
        'hi',
        'ur',
        'de',
        'fr',
        'po',
        'it',
        'nl',
        'tu',
        'fa',
        'vi',
        'ja',
        'ko',
        'he',
        'idn',
    ];

    protected $fillable = [
        'rus',
        'eng',
        'zh',
        'es',
        'ar',
        'hi',
        'ur',
        'de',
        'fr',
        'po',
        'it',
        'nl',
        'tu',
        'fa',
        'vi',
        'ja',
        'ko',
        'he',
        'idn',
        'category_id',
        'is_published',
    ];

    protected $casts = [
        'is_published' => 'boolean',
    ];

    protected $with = [
        'category',
        'manufacturers',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function components(): BelongsToMany
    {
        return $this->belongsToMany(Component::class);
    }

    public function consumers(): HasMany
    {
        return $this->hasMany(Consumption::class, 'product_id', 'id');
    }

    public function goods(): BelongsToMany
    {
        return $this->belongsToMany(Good::class);
    }

    public function manufacturers(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'manufacturers', 'product_id', 'unit_id');
    }

    public function searchRequests()
    {
        return $this->hasMany(\App\Models\ProductSearchRequest::class);
    }

    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class)
            ->withPivot(['action_id'])
            ->withTimestamps();
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        $search = trim((string) $search);

        if ($search === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search) {
            $like = "%{$search}%";

            if (ctype_digit($search)) {
                $q->where('id', (int) $search);
            } else {
                $q->whereRaw('1 = 0');
            }

            foreach (self::TRANSLATION_COLUMNS as $column) {
                $q->orWhere($column, 'like', $like);
            }

            $q->orWhereHas('category', fn (Builder $categoryQuery) =>
                $categoryQuery->where('name', 'like', $like)
            );
        });
    }

    public function scopeCategory(Builder $query, $categoryId): Builder
    {
        if (!$categoryId) {
            return $query;
        }

        return $query->where('category_id', $categoryId);
    }
}
