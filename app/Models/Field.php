<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Field extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'slug',
        'description',
        'is_published',
        'sort_order',
    ];

    protected $casts = [
        'is_published' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = [
        'name',
    ];

    protected static function booted(): void
    {
        static::saving(function (Field $field): void {
            if (!$field->isDirty('title') && !$field->isDirty('slug') && filled($field->slug)) {
                return;
            }

            $source = filled($field->slug) ? $field->slug : $field->title;
            $field->slug = static::uniqueSlug((string) $source, $field->exists ? $field->id : null);
        });
    }

    private static function uniqueSlug(string $source, ?int $exceptId = null): string
    {
        $base = Str::slug($source) ?: 'field';
        $slug = $base;
        $index = 2;

        while (
            static::query()
                ->where('slug', $slug)
                ->when($exceptId, fn (Builder $query) => $query->where('id', '!=', $exceptId))
                ->exists()
        ) {
            $slug = "{$base}-{$index}";
            $index++;
        }

        return $slug;
    }

    public function getNameAttribute(): string
    {
        return (string) $this->title;
    }

    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class);
    }

    public function goods(): BelongsToMany
    {
        return $this->belongsToMany(Good::class)
            ->withTimestamps();
    }

    public function publishedGoods(): BelongsToMany
    {
        return $this->goods()
            ->where('goods.is_published', true);
    }

    public function matches(): HasMany
    {
        return $this->hasMany(FieldMatch::class);
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        $search = trim((string) $search);

        if ($search === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search): void {
            $q->where('title', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%");
        });
    }

    public function scopePublished(Builder $query): Builder
    {
        return $query->where('is_published', true);
    }
}
