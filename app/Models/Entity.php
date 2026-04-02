<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
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
        'emails',
    ];

    protected $appends = [
        'city_names',
        'formatted_phones',
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
        return $this->hasMany(Sale::class)
            ->orderByDesc('date');
    }

    /*
    |--------------------------------------------------------------------------
    | Accessors
    |--------------------------------------------------------------------------
    */

    public function getCityNamesAttribute(): string
    {
        return $this->cities->pluck('name')->filter()->implode(', ');
    }

    public function getFormattedPhonesAttribute(): string
    {
        return $this->telephones
            ->map(function ($phone) {
                $raw = preg_replace('/\D+/', '', $phone->number ?? '');

                // Пример: 79001234567 -> +7 900 123-45-67
                if (mb_strlen($raw) === 11) {
                    $countryCode = mb_substr($raw, 0, 1);
                    $part1 = mb_substr($raw, 1, 3);
                    $part2 = mb_substr($raw, 4, 3);
                    $part3 = mb_substr($raw, 7, 2);
                    $part4 = mb_substr($raw, 9, 2);

                    return "+{$countryCode} {$part1} {$part2}-{$part3}-{$part4}";
                }

                return $phone->number ?? '';
            })
            ->implode(', ');
    }

    /*
    |--------------------------------------------------------------------------
    | Scopes
    |--------------------------------------------------------------------------
    */

    public function scopeBaseRelations(Builder $query): Builder
    {
        return $query->with([
                                'buildings',
                                'classification',
                                'country',
                                'emails',
                                'telephones',
                                'cities',
                                'chats',
                                'units',
                            ]);
    }

    public function scopeWithStats(Builder $query): Builder
    {
        return $query
            ->withCount('sales')
            ->withMax('sales', 'date'); // sales_max_date
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
                ->orWhereHas('telephones', fn (Builder $sq) => $sq->where('number', 'like', "%{$search}%"))
                ->orWhereHas('emails', fn (Builder $sq) => $sq->where('email', 'like', "%{$search}%"))
                ->orWhereHas('units', fn (Builder $sq) => $sq->where('name', 'like', "%{$search}%"))
                ->orWhereHas('chats', fn (Builder $sq) => $sq->where('name', 'like', "%{$search}%"))
                ->orWhereHas('buildings', fn (Builder $sq) => $sq->where('name', 'like', "%{$search}%"));
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

    public function scopeApplySort(Builder $query, ?string $sortBy = null, ?string $sortDesc = null): Builder
    {
        $direction = filter_var($sortDesc, FILTER_VALIDATE_BOOLEAN) ? 'desc' : 'asc';

        return match ($sortBy) {
            'name' => $query->orderBy('name', $direction),
            'INN' => $query->orderBy('INN', $direction),
            'OGRN' => $query->orderBy('OGRN', $direction),
            'sales_count' => $query->orderBy('sales_count', $direction),
            'last_purchase_date' => $query->orderBy('sales_max_date', $direction),
            default => $query->orderByDesc('sales_count')->orderBy('name'),
        };
    }
}
