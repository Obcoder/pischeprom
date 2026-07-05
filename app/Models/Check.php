<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Check extends Model
{
    use HasFactory;

    protected $fillable = [
        'date',
        'entity_id',
        'amount',
    ];

    protected $casts = [
        'date' => 'date',
        'amount' => 'float',
    ];

    protected $with = [
        'entity',
    ];

    public function entity()
    {
        return $this->belongsTo(Entity::class);
    }

    public function commodities()
    {
        return $this->belongsToMany(Commodity::class, 'check_commodity')
            ->using(CheckCommodity::class)
            ->withPivot('id', 'quantity', 'measure_id', 'expense_article_id', 'warehouse_id', 'price', 'total_price')
            ->withTimestamps();
    }

    public function services()
    {
        return $this->belongsToMany(Service::class, 'check_service')
            ->using(CheckService::class)
            ->withPivot('id', 'quantity', 'measure_id', 'expense_article_id', 'price', 'total_price')
            ->withTimestamps();
    }

    public function items(): HasMany
    {
        return $this->hasMany(CheckCommodity::class)
            ->with([
                'commodity.avaMedia',
                'commodity.expenseArticle',
                'commodity.project',
                'expenseArticle',
                'measure',
                'warehouse',
            ])
            ->orderBy('id');
    }

    public function serviceItems(): HasMany
    {
        return $this->hasMany(CheckService::class)
            ->with([
                'service.expenseArticle',
                'service.project',
                'expenseArticle',
                'measure',
            ])
            ->orderBy('id');
    }

    public function refreshAmount(): void
    {
        $commoditiesAmount = CheckCommodity::query()
            ->where('check_id', $this->id)
            ->selectRaw('COALESCE(SUM(quantity * price), 0) as amount')
            ->value('amount') ?? 0;

        $servicesAmount = CheckService::query()
            ->where('check_id', $this->id)
            ->selectRaw('COALESCE(SUM(quantity * price), 0) as amount')
            ->value('amount') ?? 0;

        $this->forceFill([
            'amount' => (float) $commoditiesAmount + (float) $servicesAmount,
        ])->save();
    }

    protected static function booted(): void
    {
        static::deleting(function (Check $check) {
            $itemIds = $check->items()->pluck('id');

            if ($itemIds->isNotEmpty()) {
                DB::table('stock_movements')
                    ->where('source_type', StockMovement::SOURCE_CHECK_COMMODITY)
                    ->whereIn('source_id', $itemIds)
                    ->delete();
            }
        });
    }
}
