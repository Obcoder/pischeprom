<?php

namespace App\Models\Concerns;

use App\Services\Goods\GoodPriceAudit;
use Illuminate\Support\Facades\DB;

trait TracksGoodPriceHistory
{
    public ?array $auditMeasurementBefore = null;

    public ?array $auditMeasurementAfter = null;

    public static function bootTracksGoodPriceHistory(): void
    {
        static::updating(function ($price): void {
            if ($price->isDirty()) {
                $price->revision = (int) $price->getRawOriginal('revision') + 1;
            }
        });
        static::created(fn ($price) => app(GoodPriceAudit::class)->record($price, 'created'));
        static::updated(fn ($price) => app(GoodPriceAudit::class)->record($price, $price->auditMeasurementAfter ? 'unit_changed' : 'updated'));
        static::deleted(fn ($price) => app(GoodPriceAudit::class)->record($price, 'deleted'));
    }

    public function save(array $options = [])
    {
        return DB::transaction(fn () => parent::save($options));
    }

    public function delete()
    {
        return DB::transaction(fn () => parent::delete());
    }
}
