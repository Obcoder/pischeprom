<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Entity extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'entity_classification_id',
        'INN',
        'OGRN',
        'country_id',
    ];

    protected $with = [
        'buildings',
        'classification',
        'country',
    ];

    public function buildings(): BelongsToMany
    {
        return $this->belongsToMany(Building::class, 'building_entities');
    }

    public function chats(): BelongsToMany
    {
        return $this->belongsToMany(Chat::class);
    }

    public function cities(): BelongsToMany
    {
        return $this->belongsToMany(City::class);
    }

    public function classification(): BelongsTo
    {
        return $this->belongsTo(EntityClassification::class, 'entity_classification_id')
            ->withDefault();
    }

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class)->withDefault();
    }

    public function emails(): BelongsToMany
    {
        return $this->belongsToMany(Email::class);
    }

    public function telephones(): BelongsToMany
    {
        return $this->belongsToMany(Telephone::class);
    }

    public function units(): BelongsToMany
    {
        return $this->belongsToMany(Unit::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class)->orderByDesc('date');
    }

    public function scopeBaseRelations(Builder $query): Builder
    {
        return $query->with([
                                'classification',
                                'country',
                                'buildings',
                                'cities',
                                'emails',
                                'telephones',
                                'units',
                                'chats',
                            ]);
    }

    public function scopeWithTableStats(Builder $query): Builder
    {
        return $query
            ->withCount('sales')
            ->withMax('sales', 'date');
    }

    public function scopeSearch(Builder $query, ?string $search): Builder
    {
        if (!$search) {
            return $query;
        }

        return $query->where(function (Builder $q) use ($search) {
            $q->where('name', 'like', "%{$search}%")
                ->orWhere('INN', 'like', "%{$search}%")
                ->orWhere('OGRN', 'like', "%{$search}%")
                ->orWhereHas('classification', fn (Builder $sq) => $sq->where('name', 'like', "%{$search}%"))
                ->orWhereHas('country', fn (Builder $sq) => $sq->where('name', 'like', "%{$search}%"))
                ->orWhereHas('cities', fn (Builder $sq) => $sq->where('name', 'like', "%{$search}%"))
                ->orWhereHas('emails', fn (Builder $sq) => $sq->where('address', 'like', "%{$search}%"))
                ->orWhereHas('telephones', fn (Builder $sq) => $sq->where('number', 'like', "%{$search}%"))
                ->orWhereHas('units', fn (Builder $sq) => $sq->where('name', 'like', "%{$search}%"))
                ->orWhereHas('chats', fn (Builder $sq) => $sq->where('numbers', 'like', "%{$search}%"))
                ->orWhereHas('buildings', fn (Builder $sq) => $sq->where('address', 'like', "%{$search}%"));
        });
    }

    public function scopeFilter(Builder $query, array $filters = []): Builder
    {
        return $query
            ->when(!empty($filters['entity_classification_ids']), function (Builder $q) use ($filters) {
                $q->whereIn('entity_classification_id', (array) $filters['entity_classification_ids']);
            })
            ->when(!empty($filters['country_ids']), function (Builder $q) use ($filters) {
                $q->whereIn('country_id', (array) $filters['country_ids']);
            })
            ->when(!empty($filters['city_ids']), function (Builder $q) use ($filters) {
                $q->whereHas('cities', fn (Builder $sq) => $sq->whereIn('cities.id', (array) $filters['city_ids']));
            })
            ->when(!empty($filters['building_ids']), function (Builder $q) use ($filters) {
                $q->whereHas('buildings', fn (Builder $sq) => $sq->whereIn('buildings.id', (array) $filters['building_ids']));
            })
            ->when(!empty($filters['email_ids']), function (Builder $q) use ($filters) {
                $q->whereHas('emails', fn (Builder $sq) => $sq->whereIn('emails.id', (array) $filters['email_ids']));
            })
            ->when(!empty($filters['telephone_ids']), function (Builder $q) use ($filters) {
                $q->whereHas('telephones', fn (Builder $sq) => $sq->whereIn('telephones.id', (array) $filters['telephone_ids']));
            })
            ->when(!empty($filters['unit_ids']), function (Builder $q) use ($filters) {
                $q->whereHas('units', fn (Builder $sq) => $sq->whereIn('units.id', (array) $filters['unit_ids']));
            })
            ->when(!empty($filters['chat_ids']), function (Builder $q) use ($filters) {
                $q->whereHas('chats', fn (Builder $sq) => $sq->whereIn('chats.id', (array) $filters['chat_ids']));
            });
    }

    public function scopeApplySort(Builder $query, ?string $sortBy = null, bool $sortDesc = true): Builder
    {
        $direction = $sortDesc ? 'desc' : 'asc';

        return match ($sortBy) {
            'name' => $query->orderBy('name', $direction),
            'INN' => $query->orderBy('INN', $direction),
            'OGRN' => $query->orderBy('OGRN', $direction),
            'sales_count' => $query->orderBy('sales_count', $direction),
            'sales_max_date' => $query->orderBy('sales_max_date', $direction),
            default => $query->orderByDesc('sales_count')->orderBy('name'),
        };
    }
}
