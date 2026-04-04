<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Telephone extends Model
{
    protected $fillable = [
        'number',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function entities(): BelongsToMany
    {
        return $this->belongsToMany(Entity::class, 'entity_telephone')
            ->withTimestamps();
    }

    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class, 'telephone_unit')
            ->withTimestamps();
    }

    public function scopeWithRelations(Builder $query): Builder
    {
        return $query->with([
                                'entities:id,name',
                                'units:id,name',
                            ]);
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        $search = trim((string) $search);

        if ($search === '') {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search) {
            $q->where('number', 'like', "%{$search}%")
                ->orWhereHas('entities', function (Builder $eq) use ($search) {
                    $eq->where('name', 'like', "%{$search}%");
                })
                ->orWhereHas('units', function (Builder $uq) use ($search) {
                    $uq->where('name', 'like', "%{$search}%");
                });
        });
    }

    public function scopeFilterEntities(Builder $query, array|string|null $entityIds): Builder
    {
        $entityIds = is_array($entityIds)
            ? array_filter($entityIds)
            : array_filter(explode(',', (string) $entityIds));

        if (empty($entityIds)) {
            return $query;
        }

        return $query->whereHas('entities', function (Builder $q) use ($entityIds) {
            $q->whereIn('entities.id', $entityIds);
        });
    }

    public function scopeFilterUnits(Builder $query, array|string|null $unitIds): Builder
    {
        $unitIds = is_array($unitIds)
            ? array_filter($unitIds)
            : array_filter(explode(',', (string) $unitIds));

        if (empty($unitIds)) {
            return $query;
        }

        return $query->whereHas('units', function (Builder $q) use ($unitIds) {
            $q->whereIn('units.id', $unitIds);
        });
    }

    public function scopeApplySort(
        Builder $query,
        ?string $sortBy = 'id',
        ?string $sortOrder = 'desc'
    ): Builder {
        $allowedSorts = ['id', 'number', 'created_at', 'updated_at'];

        $sortBy = in_array($sortBy, $allowedSorts, true) ? $sortBy : 'id';
        $sortOrder = strtolower((string) $sortOrder) === 'asc' ? 'asc' : 'desc';

        return $query->orderBy($sortBy, $sortOrder);
    }
}
