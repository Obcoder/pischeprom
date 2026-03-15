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

    protected $fillable = [
        'rus',
        'eng',
        'zh',
        'es',
        'ar',
        'po',
        'de',
        'fr',
        'hi',
        'tu',
        'vi',
        'it',
        'category_id',
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

            $q->where('rus', 'like', $like)
                ->orWhere('eng', 'like', $like)
                ->orWhere('zh', 'like', $like)
                ->orWhere('es', 'like', $like)
                ->orWhere('ar', 'like', $like)
                ->orWhere('po', 'like', $like)
                ->orWhere('de', 'like', $like)
                ->orWhere('fr', 'like', $like)
                ->orWhere('hi', 'like', $like)
                ->orWhere('tu', 'like', $like)
                ->orWhere('vi', 'like', $like)
                ->orWhere('it', 'like', $like);
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
