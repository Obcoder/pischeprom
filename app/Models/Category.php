<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'local_code',
        'field_id',
        'image',
        'image_alt',
        'is_published',
        'is_featured',
        'sort_order',
        'published_at',
        'h1',
        'short_description',
        'description',
        'seo_text',
        'meta_title',
        'meta_description',
        'keywords',
        'robots',
        'canonical_url',
        'og_title',
        'og_description',
        'og_image',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'is_featured' => 'boolean',
        'keywords' => 'array',
        'published_at' => 'datetime',
        'sort_order' => 'integer',
        'field_id' => 'integer',
    ];

    protected static function booted(): void
    {
        static::saving(function (Category $category): void {
            if (!$category->isDirty('name') && !$category->isDirty('slug')) {
                return;
            }

            $base = Str::slug($category->slug ?: $category->name);

            if ($base === '') {
                $base = 'category';
            }

            $slug = $base;
            $index = 2;

            while (
                static::query()
                    ->where('slug', $slug)
                    ->when($category->exists, fn (Builder $query) => $query->where('id', '!=', $category->id))
                    ->exists()
            ) {
                $slug = "{$base}-{$index}";
                $index++;
            }

            $category->slug = $slug;
        });
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class)
            ->where('is_published', 1);
    }

    public function field(): BelongsTo
    {
        return $this->belongsTo(Field::class);
    }

    public function goods(): BelongsToMany
    {
        return $this->belongsToMany(Good::class)
            ->where('goods.is_published', true);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        return $query->when($search, function (Builder $q) use ($search) {
            $q->where(function (Builder $searchQuery) use ($search): void {
                $searchQuery
                    ->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('local_code', 'like', "%{$search}%")
                    ->orWhere('meta_title', 'like', "%{$search}%");
            });
        });
    }

    public function scopeOrdered(
        Builder $query,
        string $column = 'name',
        string $direction = 'asc'
    ): Builder {
        return $query->orderBy($column, $direction);
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }
}
